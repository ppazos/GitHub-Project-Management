<?php
/**
 * POST /api/milestone_create
 *
 * Body: { "repo": "owner/name", "title": "v1.0", "description": "...", "due_on": "2026-06-01" }
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/github.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$user = require_auth();
require_csrf($user);

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    json_error('Invalid JSON body.');
}

$repo        = $body['repo']        ?? '';
$title       = trim($body['title']       ?? '');
$description = trim($body['description'] ?? '');
$due_on      = trim($body['due_on']      ?? '');

if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
    json_error('Invalid repo.');
}
if ($title === '') {
    json_error('Title is required.');
}

$data = ['title' => $title];
if ($description !== '') {
    $data['description'] = $description;
}
if ($due_on !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_on)) {
    // GitHub expects ISO 8601 with time component
    $data['due_on'] = $due_on . 'T00:00:00Z';
}

[$owner, $name] = explode('/', $repo, 2);
$github = new GitHubClient($user['access_token']);

try {
    $m = $github->create_milestone($owner, $name, $data);
} catch (RuntimeException $e) {
    json_error($e->getMessage(), $e->getCode() ?: 502);
}

json_response([
    'ok'        => true,
    'milestone' => [
        'number'        => $m['number'],
        'title'         => $m['title'],
        'description'   => $m['description'] ?? '',
        'state'         => $m['state'],
        'open_issues'   => $m['open_issues'],
        'closed_issues' => $m['closed_issues'],
        'due_on'        => $m['due_on'],
        'html_url'      => $m['html_url'],
    ],
]);
