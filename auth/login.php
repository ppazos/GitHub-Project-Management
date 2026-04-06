<?php
/**
 * OAuth login — redirect the user to GitHub's authorization page.
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/security.php';

session_start();

$state                  = generate_oauth_state();
$_SESSION['oauth_state'] = $state;

$params = http_build_query([
    'client_id'    => $_ENV['GITHUB_CLIENT_ID'],
    'redirect_uri' => $_ENV['GITHUB_CALLBACK_URL'],
    'scope'        => 'repo read:user',
    'state'        => $state,
]);

header('Location: https://github.com/login/oauth/authorize?' . $params);
exit;
