<?php
/**
 * View a single shelf.
 *
 *   /collection.php?slug={shelf_slug}                     — current user's shelf
 *   /collection.php?slug={shelf_slug}&user={user_uuid}    — public shelf of another user
 *
 * Phase 2: public shelves are read-only by other users. Owners can remove
 * books and reorder via the API in Phase 3.
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

$slug      = trim((string) ($_GET['slug'] ?? ''));
$userUuid  = trim((string) ($_GET['user'] ?? ''));

if ($slug === '') abort(404);

// Resolve owner — either explicit (public view) or the current user
if ($userUuid !== '') {
    $ownerStmt = db()->prepare("SELECT * FROM users WHERE uuid = ? LIMIT 1");
    $ownerStmt->execute([$userUuid]);
    $owner = $ownerStmt->fetch();
    if (!$owner) abort(404);
} else {
    require_auth();
    $owner = current_user();
}

$shelf = CollectionRepository::findBySlug((int) $owner['id'], $slug);
if (!$shelf) abort(404);

$me = current_user();
$isOwner = $me && (int) $me['id'] === (int) $owner['id'];

if (!$isOwner && !$shelf['is_public']) {
    abort(403, 'This shelf is private.');
}

// Owner POST handlers — remove book
if ($isOwner && is_post()) {
    csrf_verify_or_abort();
    $verb = (string) ($_POST['verb'] ?? '');
    if ($verb === 'remove_book') {
        $bookId = (int) ($_POST['book_id'] ?? 0);
        if ($bookId > 0) {
            CollectionRepository::removeBook((int) $shelf['id'], $bookId);
            flash('success', 'Book removed from shelf.');
        }
        redirect('collection.php?slug=' . eurl($slug) . ($userUuid ? '&user=' . eurl($userUuid) : ''));
    }
}

$books = CollectionRepository::booksIn((int) $shelf['id'], 200);

render('library/collection-show', [
    'pageTitle' => $shelf['name'],
    'pageClass' => 'collection-page',
    'shelf'     => $shelf,
    'books'     => $books,
    'isOwner'   => $isOwner,
    'owner'     => $owner,
], 'app');
