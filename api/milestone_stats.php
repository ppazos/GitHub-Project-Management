<?php
/**
 * GET /api/milestone_stats?repo=owner/name&milestone=<number>
 *
 * Returns issue counts broken down by status label for one milestone.
 * Used to render the segmented progress bar on the milestone list screen.
 *
 * Response:
 * {
 *   "total": 12,
 *   "todo": 3,
 *   "in_progress": 2,
 *   "review": 1,
 *   "done": 6
 * }
 *
 * Note: closed issues that carry no status:done label are counted as done.
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/github.php';

$user = require_auth();

$repo      = $_GET['repo']      ?? '';
$milestone = $_GET['milestone'] ?? '';

if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
    json_error('Invalid repo.');
}
if (!ctype_digit($milestone) || (int) $milestone < 1) {
    json_error('Invalid milestone.');
}

[$owner, $name] = explode('/', $repo, 2);
$github = new GitHubClient($user['access_token']);

try {
    $issues = $github->get_issues($owner, $name, (int) $milestone);
} catch (RuntimeException $e) {
    json_error($e->getMessage(), $e->getCode() ?: 502);
}

$stats = ['total' => count($issues), 'todo' => 0, 'in_progress' => 0, 'review' => 0, 'done' => 0];

foreach ($issues as $issue) {
    $status = null;
    foreach ($issue['labels'] as $label) {
        $ln = strtolower($label['name']);
        if (str_starts_with($ln, 'status:')) {
            $status = substr($ln, 7); // e.g. "todo", "in-progress", "review", "done"
            break;
        }
    }

    // Closed issues with no status label count as done
    if ($status === null) {
        $status = ($issue['state'] === 'closed') ? 'done' : 'todo';
    }

    switch ($status) {
        case 'todo':        $stats['todo']++;        break;
        case 'in-progress': $stats['in_progress']++; break;
        case 'review':      $stats['review']++;      break;
        case 'done':        $stats['done']++;        break;
        default:            $stats['todo']++;        // unknown status → todo
    }
}

json_response($stats);
