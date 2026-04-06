<?php
/**
 * GET /api/me — return current authenticated user and CSRF token.
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';

$user  = require_auth();
$token = get_session_token();

json_response([
    'id'         => $user['id'],
    'login'      => $user['login'],
    'github_id'  => $user['github_id'],
    'csrf_token' => generate_csrf_token($token),
]);
