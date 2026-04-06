<?php
/**
 * GET /api/issues?repo=owner/repo&milestone=<number>
 *
 * Returns issues for a milestone with their saved display positions.
 * Issues are sorted by position (ascending); issues with no saved position
 * sort after those that have one, preserving GitHub's default order among them.
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/github.php';
require_once __DIR__ . '/../lib/db.php';

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

// Load saved positions for this repo from the database
$db   = get_db();
$stmt = $db->prepare(
    'SELECT issue_number, position FROM issue_positions WHERE repo = ?'
);
$stmt->execute([$repo]);
$positions = [];
foreach ($stmt->fetchAll() as $row) {
    $positions[(int) $row['issue_number']] = (float) $row['position'];
}

const STATUS_LABELS = ['status:todo', 'status:in-progress', 'status:review', 'status:done'];

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
    // Issues without a saved position get PHP_INT_MAX so they sort to the bottom
    'position'  => $positions[$i['number']] ?? PHP_INT_MAX,
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

// Sort by position so the frontend can render in order directly
usort($result, fn($a, $b) => $a['position'] <=> $b['position']);

json_response($result);
