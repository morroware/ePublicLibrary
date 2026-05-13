<?php
/**
 * Highlights API.
 *
 *   GET    /api/highlights.php?b={book_uuid}
 *       → list current user's highlights for a book
 *
 *   POST   /api/highlights.php
 *       body: { uuid, cfi_range, text, color?, note?, chapter? }
 *       → create
 *
 *   PATCH  /api/highlights.php?id={id}
 *       body: { color?, note? }
 *       → update note or color
 *
 *   DELETE /api/highlights.php?id={id}
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

if (!is_authed()) {
    json_error('Sign in to manage highlights.', 401);
}
$user   = current_user();
$method = request_method();

if ($method === 'GET') {
    $bookUuid = (string) ($_GET['b'] ?? '');
    if (!preg_match('/^[0-9a-f-]{36}$/i', $bookUuid)) {
        json_error('Invalid book UUID', 400);
    }
    $book = BookRepository::findByUuid($bookUuid);
    if (!$book) {
        json_error('Book not found', 404);
    }
    json_response(HighlightRepository::listForBook((int) $user['id'], (int) $book['id']));
}

if ($method === 'POST') {
    csrf_verify_or_abort();
    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($payload)) {
        json_error('Invalid JSON body', 400);
    }
    $bookUuid = (string) ($payload['uuid'] ?? '');
    $cfiRange = (string) ($payload['cfi_range'] ?? '');
    $text     = (string) ($payload['text'] ?? '');
    if (!preg_match('/^[0-9a-f-]{36}$/i', $bookUuid) || $cfiRange === '' || $text === '') {
        json_error('uuid, cfi_range, and text are required', 400);
    }
    $book = BookRepository::findByUuid($bookUuid);
    if (!$book) {
        json_error('Book not found', 404);
    }
    $id = HighlightRepository::create((int) $user['id'], (int) $book['id'], [
        'cfi_range' => $cfiRange,
        'text'      => mb_substr($text, 0, 8000),
        'color'     => $payload['color']   ?? 'yellow',
        'note'      => $payload['note']    ?? null,
        'chapter'   => $payload['chapter'] ?? null,
    ]);
    AuditLogger::log('highlight.create', 'book', (int) $book['id'], ['highlight_id' => $id]);
    json_response(['ok' => true, 'id' => $id]);
}

if ($method === 'PATCH' || ($method === 'POST' && !empty($_GET['_method']) && strtoupper((string) $_GET['_method']) === 'PATCH')) {
    csrf_verify_or_abort();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('Invalid id', 400);
    }
    $payload = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
    $ok = HighlightRepository::update((int) $user['id'], $id, [
        'color' => $payload['color'] ?? null,
        'note'  => array_key_exists('note', $payload) ? $payload['note'] : null,
    ]);
    if (!$ok) {
        json_error('Highlight not found or no changes', 404);
    }
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    csrf_verify_or_abort();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('Invalid id', 400);
    }
    $ok = HighlightRepository::delete((int) $user['id'], $id);
    if (!$ok) {
        json_error('Highlight not found', 404);
    }
    AuditLogger::log('highlight.delete', null, $id);
    json_response(['ok' => true]);
}

json_error('Method not allowed', 405);
