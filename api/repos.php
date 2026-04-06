<?php
/**
 * GET /api/repos — list repositories accessible to the authenticated user.
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/github.php';

$user   = require_auth();
$github = new GitHubClient($user['access_token']);

try {
    $repos = $github->get_repos();
} catch (RuntimeException $e) {
    $code = $e->getCode() ?: 502;
    json_error($e->getMessage(), (int) $code);
}

// Return only the fields the frontend needs
$result = array_map(fn($r) => [
    'full_name'   => $r['full_name'],
    'name'        => $r['name'],
    'owner'       => $r['owner']['login'],
    'private'     => $r['private'],
    'description' => $r['description'] ?? '',
    'html_url'    => $r['html_url'],
    'updated_at'  => $r['updated_at'],
], $repos);

json_response($result);
