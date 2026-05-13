<?php
/**
 * Book detail page.
 *
 * Public: anyone can view title/author/cover/description.
 * Authed users additionally see "Add to shelf" controls, their own review,
 * and a write-review form.
 *
 * POST handles inline review submit/delete (server-rendered fallback for
 * the JS-driven review form on the page).
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

$user = current_user();

// ---- POST handlers ----
if (is_post()) {
    csrf_verify_or_abort();
    if (!$user) {
        flash('error', 'Please sign in first.');
        redirect('login.php');
    }
    $verb = (string) ($_POST['verb'] ?? '');

    if ($verb === 'review_submit') {
        $rating = (int) ($_POST['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            flash('error', 'Pick a rating between 1 and 5 stars.');
            redirect('book.php?b=' . $uuid);
        }
        $title = isset($_POST['title']) ? (string) $_POST['title'] : null;
        $body  = isset($_POST['body'])  ? (string) $_POST['body']  : null;
        if ($title !== null && mb_strlen($title) > ReviewRepository::TITLE_MAX) {
            flash('error', 'Title is too long (max ' . ReviewRepository::TITLE_MAX . ' characters).');
            redirect('book.php?b=' . $uuid);
        }
        if ($body !== null && mb_strlen($body) > ReviewRepository::BODY_MAX) {
            flash('error', 'Review is too long (max ' . ReviewRepository::BODY_MAX . ' characters).');
            redirect('book.php?b=' . $uuid);
        }
        ReviewRepository::upsert((int) $user['id'], (int) $book['id'], [
            'rating' => $rating,
            'title'  => $title,
            'body'   => $body,
        ]);
        AuditLogger::log('review.submit', 'book', (int) $book['id'], ['rating' => $rating]);
        flash('success', 'Thanks for your review.');
        redirect('book.php?b=' . $uuid);
    }
    if ($verb === 'review_delete') {
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        if ($reviewId > 0 && ReviewRepository::delete((int) $user['id'], $reviewId)) {
            AuditLogger::log('review.delete', 'book', (int) $book['id']);
            flash('success', 'Review removed.');
        }
        redirect('book.php?b=' . $uuid);
    }
    if ($verb === 'shelf_toggle') {
        $collectionId = (int) ($_POST['collection_id'] ?? 0);
        $coll = $collectionId > 0 ? CollectionRepository::findById($collectionId) : null;
        if (!$coll || (int) $coll['user_id'] !== (int) $user['id']) {
            flash('error', 'Shelf not found.');
            redirect('book.php?b=' . $uuid);
        }
        if (CollectionRepository::containsBook($collectionId, (int) $book['id'])) {
            CollectionRepository::removeBook($collectionId, (int) $book['id']);
            flash('success', 'Removed from ' . $coll['name'] . '.');
        } else {
            CollectionRepository::addBook($collectionId, (int) $book['id']);
            flash('success', 'Added to ' . $coll['name'] . '.');
        }
        redirect('book.php?b=' . $uuid);
    }
}

// ---- Render data ----
$tags = TagRepository::forBook((int) $book['id']);

$progress = $user ? ProgressRepository::get((int) $user['id'], (int) $book['id']) : null;

$reviews = ReviewRepository::listForBook((int) $book['id'], 25);
$myReview = $user ? ReviewRepository::findForUserBook((int) $user['id'], (int) $book['id']) : null;
$distribution = ReviewRepository::distributionForBook((int) $book['id']);

$related = BookRepository::relatedTo((int) $book['id'], 6);

$shelves = $user ? CollectionRepository::forUserAndBook((int) $user['id'], (int) $book['id']) : [];

render('book/show', [
    'pageTitle'    => $book['title'] . ' — ' . $book['author'],
    'pageClass'    => 'book-detail-page',
    'book'         => $book,
    'tags'         => $tags,
    'progress'     => $progress,
    'reviews'      => $reviews,
    'myReview'     => $myReview,
    'distribution' => $distribution,
    'related'      => $related,
    'shelves'      => $shelves,
    'user'         => $user,
], 'app');
