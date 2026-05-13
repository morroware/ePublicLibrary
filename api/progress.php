<?php
/**
 * Per-user reading progress sync.
 *
 *   GET  /api/progress.php?b={uuid}   → returns the user's progress for that book
 *   POST /api/progress.php            → upsert progress
 *
 * Guests are served politely with 401 — the client falls back to localStorage.
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

if (!is_authed()) {
    json_error('Sign in to sync progress.', 401);
}
$user = current_user();

$method = request_method();

if ($method === 'GET') {
    $uuid = (string) ($_GET['b'] ?? '');
    if (!preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
        json_error('Invalid book UUID', 400);
    }
    $book = BookRepository::findByUuid($uuid);
    if (!$book) {
        json_error('Book not found', 404);
    }
    $row = ProgressRepository::get((int) $user['id'], (int) $book['id']);
    json_response($row ?: null);
}

if ($method === 'POST') {
    csrf_verify_or_abort();

    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($payload)) {
        json_error('Invalid JSON body', 400);
    }

    $uuid = (string) ($payload['uuid'] ?? '');
    if (!preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
        json_error('Invalid book UUID', 400);
    }
    $book = BookRepository::findByUuid($uuid);
    if (!$book) {
        json_error('Book not found', 404);
    }

    // Generous rate-limit because the reader debounces to ~5s already.
    $bucket = 'progress:user:' . $user['id'];
    if (!rate_limit_check($bucket, 60, 60)) {
        json_error('Rate limit exceeded', 429);
    }
    rate_limit_hit($bucket, 60);

    ProgressRepository::upsert((int) $user['id'], (int) $book['id'], [
        'cfi'             => (string) ($payload['cfi'] ?? ''),
        'percentage'      => (float)  ($payload['percentage'] ?? 0),
        'current_chapter' => $payload['current_chapter'] ?? null,
        'finished'        => !empty($payload['finished']),
    ]);
    json_response(['ok' => true]);
}

json_error('Method not allowed', 405);
