<?php
/**
 * Logout — destroy the server-side session and clear the cookie.
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/session.php';

$token = get_session_token();
if ($token) {
    destroy_session($token);
}
clear_session_cookie();

header('Location: /');
exit;
