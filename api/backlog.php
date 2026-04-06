<?php
/**
 * GET /api/backlog?repo=owner/name[&page=1][&per_page=25][&q=search+term]
 *
 * Returns paginated open issues with no milestone for a repo.
 * Uses GitHub's Search API so we get total_count for pagination.
 *
 * Response:
 * {
 *   "issues":      [...],
 *   "total_count": 123,
 *   "page":        1,
 *   "per_page":    25,
 *   "total_pages": 5
 * }
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/github.php';

$user = require_auth();

$repo     = $_GET['repo']     ?? '';
$page     = max(1, (int) ($_GET['page']     ?? 1));
$per_page = min(50, max(10, (int) ($_GET['per_page'] ?? 25)));
$q        = trim($_GET['q'] ?? '');

if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
    json_error('Invalid repo.');
}

[$owner, $name] = explode('/', $repo, 2);
$github = new GitHubClient($user['access_token']);

try {
    $result = $github->search_backlog($owner, $name, $q, $page, $per_page);
} catch (RuntimeException $e) {
    json_error($e->getMessage(), $e->getCode() ?: 502);
}

$total_count = (int) ($result['total_count'] ?? 0);
$total_pages = max(1, (int) ceil($total_count / $per_page));

$issues = array_map(fn($i) => [
    'number'    => $i['number'],
    'title'     => $i['title'],
    'state'     => $i['state'],
    'labels'    => array_map(fn($l) => [
        'name'  => $l['name'],
        'color' => $l['color'],
    ], $i['labels'] ?? []),
    'assignees' => array_map(fn($a) => [
        'login'      => $a['login'],
        'avatar_url' => $a['avatar_url'],
    ], $i['assignees'] ?? []),
    'html_url'   => $i['html_url'],
    'created_at' => $i['created_at'],
], $result['items'] ?? []);

json_response([
    'issues'      => $issues,
    'total_count' => $total_count,
    'page'        => $page,
    'per_page'    => $per_page,
    'total_pages' => $total_pages,
]);
