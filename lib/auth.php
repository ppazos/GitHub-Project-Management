<?php
/**
 * Auth middleware helpers.
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/security.php';

/**
 * Require an authenticated session.
 * For API requests, returns 401 JSON. For page requests, redirects to login.
 * Returns the user row on success.
 */
function require_auth(): array {
    $token = get_session_token();

    if ($token) {
        $user = get_session_user($token);
        if ($user) {
            return $user;
        }
        clear_session_cookie();
    }

    if (is_api_request()) {
        json_error('Unauthorized', 401);
    }

    header('Location: ' . APP_BASE . '/auth/login');
    exit;
}

/**
 * Require a valid CSRF token for state-changing requests.
 * Expects the token in the X-CSRF-Token header.
 */
function require_csrf(array $user): void {
    $token          = get_session_token();
    $submitted      = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$submitted || !validate_csrf_token($submitted, $token)) {
        json_error('CSRF token mismatch', 403);
    }
}

/**
 * Returns true when the current request is under /api/.
 */
function is_api_request(): bool {
    return str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
}
