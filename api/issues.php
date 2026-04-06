<?php
/**
 * GET /api/issues?repo=owner/repo&milestone=<number>
 *
 * Returns issues grouped into the four Kanban columns.
 * Status is derived from the status:* label; defaults to "todo".
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/github.php';

$user = require_auth();

$repo      = $_GET['repo']      ?? '';
$milestone = $_GET['milestone'] ?? '';

if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
    json_error('Invalid repo parameter.');
}
if (!ctype_digit($milestone) || (int) $milestone < 1) {
    json_error('Invalid milestone parameter.');
}

[$owner, $name] = explode('/', $repo, 2);

$github = new GitHubClient($user['access_token']);

try {
    $issues = $github->get_issues($owner, $name, (int) $milestone);
} catch (RuntimeException $e) {
    $code = $e->getCode() ?: 502;
    json_error($e->getMessage(), (int) $code);
}

const STATUS_LABELS = ['status:todo', 'status:in-progress', 'status:review', 'status:done'];

/**
 * Extract the status:* label value from an issue's labels array.
 * Falls back to 'todo' if none is present.
 */
function derive_status(array $labels): string {
    foreach ($labels as $label) {
        $ln = strtolower($label['name']);
        if (in_array($ln, STATUS_LABELS, true)) {
            return substr($ln, strlen('status:'));
        }
    }
    return 'todo';
}

$result = array_map(fn($i) => [
    'number'    => $i['number'],
    'title'     => $i['title'],
    'state'     => $i['state'],
    'status'    => derive_status($i['labels']),
    'labels'    => array_map(fn($l) => [
        'name'  => $l['name'],
        'color' => $l['color'],
    ], $i['labels']),
    'assignees' => array_map(fn($a) => [
        'login'      => $a['login'],
        'avatar_url' => $a['avatar_url'],
    ], $i['assignees']),
    'html_url'  => $i['html_url'],
    'created_at'=> $i['created_at'],
    'updated_at'=> $i['updated_at'],
], $issues);

json_response($result);
