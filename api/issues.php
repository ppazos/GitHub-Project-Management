<?php
/**
 * GET /api/issues?repo=owner/repo&milestone=<number>|none
 *
 * milestone=<number> — issues assigned to that milestone (Kanban board).
 * milestone=none     — issues with no milestone assigned (backlog).
 *
 * Board issues are returned sorted by saved position (ascending).
 * Backlog issues are returned sorted by creation date (newest first).
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

$is_backlog = ($milestone === 'none');
if (!$is_backlog && (!ctype_digit($milestone) || (int) $milestone < 1)) {
    json_error('Invalid milestone parameter. Use a positive integer or "none".');
}

[$owner, $name] = explode('/', $repo, 2);
$github = new GitHubClient($user['access_token']);

try {
    $issues = $github->get_issues($owner, $name, $milestone);
} catch (RuntimeException $e) {
    $code = $e->getCode() ?: 502;
    json_error($e->getMessage(), (int) $code);
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

// For board issues, load saved positions scoped to this milestone
$positions = [];
if (!$is_backlog) {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT issue_number, position
         FROM issue_positions
         WHERE repo = ? AND milestone_number = ?'
    );
    $stmt->execute([$repo, (int) $milestone]);
    foreach ($stmt->fetchAll() as $row) {
        $positions[(int) $row['issue_number']] = (float) $row['position'];
    }
}

$result = array_map(fn($i) => [
    'number'    => $i['number'],
    'title'     => $i['title'],
    'state'     => $i['state'],
    'status'    => derive_status($i['labels']),
    'position'  => $positions[$i['number']] ?? PHP_INT_MAX,
    'labels'    => array_map(fn($l) => [
        'name'  => $l['name'],
        'color' => $l['color'],
    ], $i['labels']),
    'assignees' => array_map(fn($a) => [
        'login'      => $a['login'],
        'avatar_url' => $a['avatar_url'],
    ], $i['assignees']),
    'html_url'   => $i['html_url'],
    'created_at' => $i['created_at'],
    'updated_at' => $i['updated_at'],
    'milestone'  => $i['milestone'] ? [
        'number' => $i['milestone']['number'],
        'title'  => $i['milestone']['title'],
    ] : null,
], $issues);

// Board: sort by saved position. Backlog: newest first (GitHub default).
if (!$is_backlog) {
    usort($result, fn($a, $b) => $a['position'] <=> $b['position']);
}

json_response($result);
