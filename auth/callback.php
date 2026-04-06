<?php
/**
 * OAuth callback — exchange code for token, create local session.
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/github.php';

session_start();

// Validate state to prevent CSRF during OAuth flow
$state = $_GET['state'] ?? '';
if (
    empty($state)
    || empty($_SESSION['oauth_state'])
    || !hash_equals($_SESSION['oauth_state'], $state)
) {
    http_response_code(400);
    exit('Invalid OAuth state. Please try again. <a href="/auth/login">Login</a>');
}
unset($_SESSION['oauth_state']);

$code = $_GET['code'] ?? '';
if (empty($code)) {
    http_response_code(400);
    exit('Missing authorization code. <a href="/auth/login">Login</a>');
}

// Exchange code for access token
$ch = curl_init('https://github.com/login/oauth/access_token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'client_id'     => $_ENV['GITHUB_CLIENT_ID'],
        'client_secret' => $_ENV['GITHUB_CLIENT_SECRET'],
        'code'          => $code,
        'redirect_uri'  => $_ENV['GITHUB_CALLBACK_URL'],
    ]),
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 10,
]);
$response  = curl_exec($ch);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    http_response_code(500);
    exit('Failed to contact GitHub. Please try again.');
}

$token_data   = json_decode($response, true);
$access_token = $token_data['access_token'] ?? null;

if (!$access_token) {
    $msg = $token_data['error_description'] ?? 'Failed to obtain access token.';
    http_response_code(400);
    exit(htmlspecialchars($msg) . ' <a href="/auth/login">Try again</a>');
}

// Fetch the authenticated GitHub user profile
$github  = new GitHubClient($access_token);
$profile = $github->get_user();

$github_id = (string) $profile['id'];
$login     = $profile['login'];

// Upsert user with encrypted token
$db              = get_db();
$encrypted_token = encrypt_token($access_token);

$stmt = $db->prepare(
    'INSERT INTO users (github_id, login, access_token)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE login = VALUES(login), access_token = VALUES(access_token)'
);
$stmt->execute([$github_id, $login, $encrypted_token]);

// Resolve the user's local ID
$stmt = $db->prepare('SELECT id FROM users WHERE github_id = ?');
$stmt->execute([$github_id]);
$user = $stmt->fetch();

// Create session and set cookie
$session_token = create_session($user['id']);
set_session_cookie($session_token);

header('Location: /app');
exit;
