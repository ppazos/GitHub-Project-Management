<?php
/**
 * Database-backed session management.
 * Sessions are identified by a random token stored in an HTTP-only cookie.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

const SESSION_TTL    = 86400 * 7; // 7 days
const SESSION_COOKIE = 'kanban_session';

/**
 * Create a new session for the given user and return the token.
 */
function create_session(int $user_id): string {
    $db         = get_db();
    $token      = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', time() + SESSION_TTL);

    $stmt = $db->prepare(
        'INSERT INTO sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)'
    );
    $stmt->execute([$user_id, $token, $expires_at]);
    return $token;
}

/**
 * Look up a session token and return the associated user row, or null.
 * Decrypts the access token before returning.
 */
function get_session_user(string $token): ?array {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT u.id, u.github_id, u.login, u.access_token
         FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.session_token = ?
           AND s.expires_at > NOW()'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['access_token'] = decrypt_token($row['access_token']);
    return $row;
}

/**
 * Delete a session from the database.
 */
function destroy_session(string $token): void {
    $db   = get_db();
    $stmt = $db->prepare('DELETE FROM sessions WHERE session_token = ?');
    $stmt->execute([$token]);
}

/**
 * Write the session cookie (HTTP-only, SameSite=Lax).
 */
function set_session_cookie(string $token): void {
    setcookie(SESSION_COOKIE, $token, [
        'expires'  => time() + SESSION_TTL,
        'path'     => '/',
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax',
    ]);
}

/**
 * Clear the session cookie.
 */
function clear_session_cookie(): void {
    setcookie(SESSION_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax',
    ]);
}

/**
 * Read the session token from the incoming cookie.
 */
function get_session_token(): ?string {
    return $_COOKIE[SESSION_COOKIE] ?? null;
}
