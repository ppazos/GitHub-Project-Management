<?php
/**
 * POST /api/issue_order
 *
 * Saves the display order for every issue currently visible on the board.
 * The frontend sends the full ordered list of issue numbers as they appear
 * in the DOM top-to-bottom across all columns.
 *
 * Body: { "repo": "owner/name", "numbers": [42, 7, 15, 3, ...] }
 *
 * Positions are stored as integers 0, 1, 2 … so future inserts can use
 * fractional values to slot a card between two existing ones without
 * rewriting the whole column.
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';
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

$repo    = $body['repo']    ?? '';
$numbers = $body['numbers'] ?? [];

if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
    json_error('Invalid repo.');
}
if (!is_array($numbers) || empty($numbers)) {
    json_error('numbers must be a non-empty array.');
}

// Validate every element is a positive integer
foreach ($numbers as $n) {
    if (!is_int($n) || $n < 1) {
        json_error('Each number must be a positive integer.');
    }
}

$db = get_db();

// Upsert all positions in a single transaction
$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'INSERT INTO issue_positions (repo, issue_number, position)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE position = VALUES(position)'
    );
    foreach ($numbers as $position => $issue_number) {
        $stmt->execute([$repo, $issue_number, $position]);
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    json_error('Failed to save order: ' . $e->getMessage(), 500);
}

json_response(['ok' => true]);
