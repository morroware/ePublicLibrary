<?php
/**
 * Shelves API.
 *
 *   GET    /api/shelves.php?b={book_uuid}      — list current user's shelves with
 *                                                contains_book flag for the given book
 *   POST   /api/shelves.php   body={uuid, collection_id, action: 'add' | 'remove' | 'toggle'}
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

if (!is_authed()) {
    json_error('Sign in to manage shelves.', 401);
}
$user = current_user();

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
    json_response(CollectionRepository::forUserAndBook((int) $user['id'], (int) $book['id']));
}

if ($method === 'POST') {
    csrf_verify_or_abort();
    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($payload)) {
        json_error('Invalid JSON body', 400);
    }
    $bookUuid     = (string) ($payload['uuid'] ?? '');
    $collectionId = (int)    ($payload['collection_id'] ?? 0);
    $action       = (string) ($payload['action'] ?? 'toggle');

    if (!preg_match('/^[0-9a-f-]{36}$/i', $bookUuid) || $collectionId <= 0) {
        json_error('Invalid request', 400);
    }
    $book = BookRepository::findByUuid($bookUuid);
    if (!$book) {
        json_error('Book not found', 404);
    }
    $coll = CollectionRepository::findById($collectionId);
    if (!$coll || (int) $coll['user_id'] !== (int) $user['id']) {
        json_error('Shelf not found', 404);
    }

    $contains = CollectionRepository::containsBook($collectionId, (int) $book['id']);
    $next = match ($action) {
        'add'    => true,
        'remove' => false,
        default  => !$contains,  // toggle
    };

    if ($next && !$contains) {
        CollectionRepository::addBook($collectionId, (int) $book['id']);
    } elseif (!$next && $contains) {
        CollectionRepository::removeBook($collectionId, (int) $book['id']);
    }

    AuditLogger::log('shelf.toggle', 'collection', $collectionId, [
        'book_id'  => (int) $book['id'],
        'state'    => $next ? 'added' : 'removed',
    ]);

    json_response(['ok' => true, 'contains_book' => $next]);
}

json_error('Method not allowed', 405);
