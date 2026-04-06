<?php
/**
 * Security utilities:
 * - AES-256-CBC encryption for stored access tokens
 * - CSRF token generation/validation (HMAC-based, stateless)
 * - JSON response helpers
 */

/**
 * Encrypt a GitHub access token before storing in the database.
 * ENCRYPT_KEY must be a base64-encoded 32-byte value.
 */
function encrypt_token(string $token): string {
    $key = base64_decode($_ENV['ENCRYPT_KEY']);
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($token, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

/**
 * Decrypt a stored access token for use in API calls.
 */
function decrypt_token(string $stored): string {
    $key  = base64_decode($_ENV['ENCRYPT_KEY']);
    $raw  = base64_decode($stored);
    $iv   = substr($raw, 0, 16);
    $enc  = substr($raw, 16);
    return openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * Generate a CSRF token derived from the session token via HMAC.
 * Stateless — no extra storage needed.
 */
function generate_csrf_token(string $session_token): string {
    return hash_hmac('sha256', $session_token, $_ENV['SESSION_SECRET']);
}

/**
 * Validate a CSRF token submitted by the client.
 */
function validate_csrf_token(string $submitted, string $session_token): bool {
    $expected = generate_csrf_token($session_token);
    return hash_equals($expected, $submitted);
}

/**
 * Generate a random hex state string for OAuth flow.
 */
function generate_oauth_state(): string {
    return bin2hex(random_bytes(16));
}

/**
 * Emit a JSON response and halt.
 */
function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Emit a JSON error response and halt.
 */
function json_error(string $message, int $status = 400): never {
    json_response(['error' => $message], $status);
}
