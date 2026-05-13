<?php
/**
 * Book detail page.
 *
 * Phase 1: minimal "card view" — title, author, cover, description, read
 * and download CTAs. Phase 2 expands this with reviews, related books, and
 * "add to shelf" actions.
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

$uuid = (string) ($_GET['b'] ?? '');
if (!preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
    abort(404);
}
$book = BookRepository::findByUuid($uuid);
if (!$book || $book['status'] !== 'published') {
    abort(404);
}
$tags = TagRepository::forBook((int) $book['id']);

render('book/show', [
    'pageTitle' => $book['title'] . ' by ' . $book['author'],
    'pageClass' => 'book-page',
    'book'      => $book,
    'tags'      => $tags,
], 'app');
