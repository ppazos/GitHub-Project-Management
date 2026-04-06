<?php
/**
 * GET /api/milestones?repo=owner/repo&state=open|closed|all
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/github.php';

$user = require_auth();

$repo  = $_GET['repo']  ?? '';
$state = $_GET['state'] ?? 'open';

if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
    json_error('Invalid repo parameter.');
}

if (!in_array($state, ['open', 'closed', 'all'], true)) {
    $state = 'open';
}

[$owner, $name] = explode('/', $repo, 2);

$github = new GitHubClient($user['access_token']);

try {
    $milestones = $github->get_milestones($owner, $name, $state);
} catch (RuntimeException $e) {
    $code = $e->getCode() ?: 502;
    json_error($e->getMessage(), (int) $code);
}

$result = array_map(fn($m) => [
    'number'       => $m['number'],
    'title'        => $m['title'],
    'description'  => $m['description'] ?? '',
    'state'        => $m['state'],
    'open_issues'  => $m['open_issues'],
    'closed_issues'=> $m['closed_issues'],
    'due_on'       => $m['due_on'],
    'html_url'     => $m['html_url'],
], $milestones);

json_response($result);
