<?php
/**
 * POST /api/issue_update
 *
 * Supported actions (JSON body):
 *   move     — { repo, issue_number, action:"move",     column:"todo|in-progress|review|done" }
 *   close    — { repo, issue_number, action:"close"   }
 *   reopen   — { repo, issue_number, action:"reopen"  }
 *   assign   — { repo, issue_number, action:"assign",   assignees:["login"] }
 *   unassign — { repo, issue_number, action:"unassign", assignees:["login"] }
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

$repo         = $body['repo']         ?? '';
$issue_number = $body['issue_number'] ?? null;
$action       = $body['action']       ?? '';

if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
    json_error('Invalid repo.');
}
if (!is_int($issue_number) || $issue_number < 1) {
    json_error('Invalid issue_number.');
}

[$owner, $name] = explode('/', $repo, 2);
$github = new GitHubClient($user['access_token']);

try {
    switch ($action) {

        case 'move':
            $column = $body['column'] ?? '';
            $allowed = ['todo', 'in-progress', 'review', 'done'];
            if (!in_array($column, $allowed, true)) {
                json_error('Invalid column. Must be one of: ' . implode(', ', $allowed));
            }

            // Fetch current labels, strip existing status:* labels, add the new one
            $current    = $github->get_issue($owner, $name, $issue_number);
            $new_labels = array_values(array_filter(
                array_map(fn($l) => $l['name'], $current['labels']),
                fn($ln) => !str_starts_with(strtolower($ln), 'status:')
            ));
            $new_labels[] = 'status:' . $column;

            $result = $github->update_issue($owner, $name, $issue_number, [
                'labels' => $new_labels,
            ]);
            break;

        case 'close':
            $result = $github->update_issue($owner, $name, $issue_number, ['state' => 'closed']);
            break;

        case 'reopen':
            $result = $github->update_issue($owner, $name, $issue_number, ['state' => 'open']);
            break;

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
            $result = $github->update_issue($owner, $name, $issue_number, [
                'assignees' => $remaining,
            ]);
            break;

        case 'ensure_label':
            // Create the status:* label in the repo if it doesn't already exist.
            $label_name  = $body['label'] ?? '';
            $label_color = preg_replace('/[^a-fA-F0-9]/', '', $body['color'] ?? 'cccccc');
            if (!preg_match('/^status:[a-z-]+$/', $label_name)) {
                json_error('Invalid label name.');
            }
            try {
                $github->create_label($owner, $name, $label_name, $label_color);
                json_response(['ok' => true, 'created' => true]);
            } catch (RuntimeException $e) {
                // 422 = label already exists — that's fine
                if ($e->getCode() === 422) {
                    json_response(['ok' => true, 'created' => false]);
                }
                throw $e;
            }
            break; // unreachable but satisfies the switch

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
