<?php
/**
 * Per-user bookmarks API.
 *
 *   GET    /api/bookmarks.php?b={uuid}   → list bookmarks for a book
 *   POST   /api/bookmarks.php            → create
 *   DELETE /api/bookmarks.php?id={id}    → delete
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

if (!is_authed()) {
    json_error('Sign in to sync bookmarks.', 401);
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
    json_response(BookmarkRepository::listForBook((int) $user['id'], (int) $book['id']));
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
    $cfi   = trim((string) ($payload['cfi'] ?? ''));
    if ($cfi === '') {
        json_error('Bookmark CFI is required', 400);
    }
    $id = BookmarkRepository::create(
        (int) $user['id'], (int) $book['id'],
        $cfi,
        $payload['chapter'] ?? null,
        $payload['label']   ?? null
    );
    json_response(['ok' => true, 'id' => $id]);
}

if ($method === 'DELETE') {
    csrf_verify_or_abort();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('Invalid bookmark id', 400);
    }
    $ok = BookmarkRepository::delete((int) $user['id'], $id);
    json_response(['ok' => $ok]);
}

json_error('Method not allowed', 405);
