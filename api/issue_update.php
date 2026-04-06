<?php
/**
 * POST /api/issue_update
 *
 * Supported actions (JSON body):
 *   move             — { repo, issue_number, action:"move", column:"todo|in-progress|review|done" }
 *   close            — { repo, issue_number, action:"close"   }
 *   reopen           — { repo, issue_number, action:"reopen"  }
 *   assign           — { repo, issue_number, action:"assign",   assignees:["login"] }
 *   unassign         — { repo, issue_number, action:"unassign", assignees:["login"] }
 *   assign_milestone — { repo, issue_number, action:"assign_milestone", milestone:<number> }
 *   move_milestone   — { repo, issue_number, action:"move_milestone",   milestone:<number> }
 *   remove_milestone — { repo, issue_number, action:"remove_milestone" }
 *   ensure_label     — { repo, action:"ensure_label", label:"status:...", color:"rrggbb" }
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/github.php';
require_once __DIR__ . '/../lib/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$user = require_auth();
require_csrf($user);

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    json_error('Invalid JSON body.');
}

$repo         = $body['repo']         ?? '';
$issue_number = $body['issue_number'] ?? null;
$action       = $body['action']       ?? '';

if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
    json_error('Invalid repo.');
}

// ensure_label does not require issue_number
if ($action !== 'ensure_label') {
    if (!is_int($issue_number) || $issue_number < 1) {
        json_error('Invalid issue_number.');
    }
}

[$owner, $name] = explode('/', $repo, 2);
$github = new GitHubClient($user['access_token']);

try {
    switch ($action) {

        // -----------------------------------------------------------------
        case 'move':
            $column  = $body['column'] ?? '';
            $allowed = ['todo', 'in-progress', 'review', 'done'];
            if (!in_array($column, $allowed, true)) {
                json_error('Invalid column. Must be one of: ' . implode(', ', $allowed));
            }
            $current    = $github->get_issue($owner, $name, $issue_number);
            $new_labels = array_values(array_filter(
                array_map(fn($l) => $l['name'], $current['labels']),
                fn($ln) => !str_starts_with(strtolower($ln), 'status:')
            ));
            $new_labels[] = 'status:' . $column;
            $result = $github->update_issue($owner, $name, $issue_number, ['labels' => $new_labels]);
            break;

        // -----------------------------------------------------------------
        case 'close':
            $result = $github->update_issue($owner, $name, $issue_number, ['state' => 'closed']);
            break;

        // -----------------------------------------------------------------
        case 'reopen':
            $result = $github->update_issue($owner, $name, $issue_number, ['state' => 'open']);
            break;

        // -----------------------------------------------------------------
        case 'assign':
            $assignees = $body['assignees'] ?? [];
            if (!is_array($assignees) || empty($assignees)) {
                json_error('assignees must be a non-empty array.');
            }
            $assignees = array_map('strval', $assignees);
            $current   = $github->get_issue($owner, $name, $issue_number);
            $existing  = array_map(fn($a) => $a['login'], $current['assignees']);
            $merged    = array_unique(array_merge($existing, $assignees));
            $result    = $github->update_issue($owner, $name, $issue_number, [
                'assignees' => array_values($merged),
            ]);
            break;

        // -----------------------------------------------------------------
        case 'unassign':
            $assignees = $body['assignees'] ?? [];
            if (!is_array($assignees)) {
                json_error('assignees must be an array.');
            }
            $assignees = array_map('strval', $assignees);
            $current   = $github->get_issue($owner, $name, $issue_number);
            $remaining = array_values(array_filter(
                array_map(fn($a) => $a['login'], $current['assignees']),
                fn($l) => !in_array($l, $assignees, true)
            ));
            $result = $github->update_issue($owner, $name, $issue_number, ['assignees' => $remaining]);
            break;

        // -----------------------------------------------------------------
        case 'assign_milestone':
        case 'move_milestone':
            // Assign a backlog issue (or move a board issue) to a milestone.
            // The issue will appear at the top of the TODO column.
            $target_milestone = $body['milestone'] ?? null;
            if (!is_int($target_milestone) || $target_milestone < 1) {
                json_error('milestone must be a positive integer.');
            }

            // Fetch current labels, replace status:* with status:todo
            $current    = $github->get_issue($owner, $name, $issue_number);
            $new_labels = array_values(array_filter(
                array_map(fn($l) => $l['name'], $current['labels']),
                fn($ln) => !str_starts_with(strtolower($ln), 'status:')
            ));
            $new_labels[] = 'status:todo';

            $result = $github->update_issue($owner, $name, $issue_number, [
                'milestone' => $target_milestone,
                'labels'    => $new_labels,
            ]);

            // Position the issue at the top of this milestone's board.
            // Find the current minimum position and subtract 1.
            $db   = get_db();
            $stmt = $db->prepare(
                'SELECT MIN(position) AS min_pos
                 FROM issue_positions
                 WHERE repo = ? AND milestone_number = ?'
            );
            $stmt->execute([$repo, $target_milestone]);
            $min_pos     = $stmt->fetchColumn();
            $new_position = ($min_pos !== null && $min_pos !== false)
                ? ((float) $min_pos) - 1.0
                : 0.0;

            // Clean up any old position record for this issue in a previous milestone
            $old_milestone = $current['milestone']['number'] ?? null;
            if ($old_milestone && $old_milestone !== $target_milestone) {
                $db->prepare(
                    'DELETE FROM issue_positions WHERE repo = ? AND milestone_number = ? AND issue_number = ?'
                )->execute([$repo, $old_milestone, $issue_number]);
            }

            $db->prepare(
                'INSERT INTO issue_positions (repo, milestone_number, issue_number, position)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE position = VALUES(position)'
            )->execute([$repo, $target_milestone, $issue_number, $new_position]);

            break;

        // -----------------------------------------------------------------
        case 'remove_milestone':
            // Send a board issue back to the backlog (remove its milestone).
            $current    = $github->get_issue($owner, $name, $issue_number);
            $new_labels = array_values(array_filter(
                array_map(fn($l) => $l['name'], $current['labels']),
                fn($ln) => !str_starts_with(strtolower($ln), 'status:')
            ));
            $result = $github->update_issue($owner, $name, $issue_number, [
                'milestone' => null,
                'labels'    => $new_labels,
            ]);

            // Clean up stored position
            $old_milestone = $current['milestone']['number'] ?? null;
            if ($old_milestone) {
                $db = get_db();
                $db->prepare(
                    'DELETE FROM issue_positions WHERE repo = ? AND milestone_number = ? AND issue_number = ?'
                )->execute([$repo, $old_milestone, $issue_number]);
            }
            break;

        // -----------------------------------------------------------------
        case 'ensure_label':
            $label_name  = $body['label'] ?? '';
            $label_color = preg_replace('/[^a-fA-F0-9]/', '', $body['color'] ?? 'cccccc');
            if (!preg_match('/^status:[a-z-]+$/', $label_name)) {
                json_error('Invalid label name.');
            }
            try {
                $github->create_label($owner, $name, $label_name, $label_color);
                json_response(['ok' => true, 'created' => true]);
            } catch (RuntimeException $e) {
                if ($e->getCode() === 422) {
                    json_response(['ok' => true, 'created' => false]);
                }
                throw $e;
            }
            break;

        // -----------------------------------------------------------------
        default:
            json_error("Unknown action: {$action}");
    }

} catch (RuntimeException $e) {
    $code = $e->getCode() ?: 502;
    json_error($e->getMessage(), (int) $code);
}

json_response([
    'ok'    => true,
    'issue' => [
        'number'    => $result['number'],
        'state'     => $result['state'],
        'milestone' => $result['milestone'] ? [
            'number' => $result['milestone']['number'],
            'title'  => $result['milestone']['title'],
        ] : null,
        'labels'    => array_map(
            fn($l) => ['name' => $l['name'], 'color' => $l['color']],
            $result['labels']
        ),
        'assignees' => array_map(
            fn($a) => ['login' => $a['login'], 'avatar_url' => $a['avatar_url']],
            $result['assignees']
        ),
    ],
]);
