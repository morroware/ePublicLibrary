<?php
/**
 * Reader page. Renders the epub.js shell; all reading logic is in JS.
 *
 * URL: /read.php?b={book_uuid}
 *
 * Public: guests can read. Reading progress and bookmarks sync to the server
 * only when a user is logged in; otherwise they fall back to localStorage.
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

$uuid = (string) ($_GET['b'] ?? '');
if ($uuid === '' || !preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
    abort(404, 'Book not found.');
}

$book = BookRepository::findByUuid($uuid);
if (!$book || $book['status'] !== 'published') {
    abort(404, 'Book not found.');
}

BookRepository::incrementReadCount((int) $book['id']);

$user = current_user();
$progress = null;
$bookmarks = [];
if ($user) {
    $progress = ProgressRepository::get((int) $user['id'], (int) $book['id']);
    $bookmarks = BookmarkRepository::listForBook((int) $user['id'], (int) $book['id']);
}

render('reader/show', [
    'pageTitle' => $book['title'],
    'book'      => $book,
    'progress'  => $progress,
    'bookmarks' => $bookmarks,
    'isGuest'   => $user === null,
], 'reader');
