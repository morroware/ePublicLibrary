<?php
/**
 * Reviews API.
 *
 *   GET    /api/reviews.php?b={book_uuid}   — list reviews for a book
 *   POST   /api/reviews.php  body={uuid, rating, title?, body?}   — upsert your review
 *   DELETE /api/reviews.php?id={review_id}                        — delete your review
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

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
    $limit  = max(1, min(100, (int) ($_GET['limit'] ?? 25)));
    $total  = ReviewRepository::countForBook((int) $book['id']);
    // Ceiling the offset against the total prevents an attacker from
    // forcing huge OFFSET scans (DoS) with arbitrary page numbers.
    $offset = max(0, min((int) ($_GET['offset'] ?? 0), max(0, $total - 1)));
    json_response([
        'items'        => ReviewRepository::listForBook((int) $book['id'], $limit, $offset),
        'total'        => $total,
        'distribution' => ReviewRepository::distributionForBook((int) $book['id']),
        'avg_rating'   => (float) $book['avg_rating'],
    ]);
}

if (!is_authed()) {
    json_error('Sign in to review books.', 401);
}
$user = current_user();

if ($method === 'POST') {
    csrf_verify_or_abort();
    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($payload)) {
        json_error('Invalid JSON body', 400);
    }
    $bookUuid = (string) ($payload['uuid'] ?? '');
    $rating   = (int)    ($payload['rating'] ?? 0);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $bookUuid) || $rating < 1 || $rating > 5) {
        json_error('Invalid request', 400);
    }
    $title = isset($payload['title']) ? (string) $payload['title'] : null;
    $body  = isset($payload['body'])  ? (string) $payload['body']  : null;
    if ($title !== null && mb_strlen($title) > ReviewRepository::TITLE_MAX) {
        json_error('Title is too long (max ' . ReviewRepository::TITLE_MAX . ' characters).', 422);
    }
    if ($body !== null && mb_strlen($body) > ReviewRepository::BODY_MAX) {
        json_error('Review is too long (max ' . ReviewRepository::BODY_MAX . ' characters).', 422);
    }
    $book = BookRepository::findByUuid($bookUuid);
    if (!$book) {
        json_error('Book not found', 404);
    }
    $id = ReviewRepository::upsert((int) $user['id'], (int) $book['id'], [
        'rating' => $rating,
        'title'  => $title,
        'body'   => $body,
    ]);
    AuditLogger::log('review.submit', 'book', (int) $book['id'], ['rating' => $rating]);
    json_response(['ok' => true, 'id' => $id]);
}

if ($method === 'DELETE') {
    csrf_verify_or_abort();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('Invalid review id', 400);
    }
    $review = ReviewRepository::findById($id);
    if (!$review || (int) $review['user_id'] !== (int) $user['id']) {
        json_error('Review not found', 404);
    }
    if (ReviewRepository::delete((int) $user['id'], $id)) {
        AuditLogger::log('review.delete', 'book', (int) $review['book_id']);
        json_response(['ok' => true]);
    }
    json_error('Could not delete', 500);
}

json_error('Method not allowed', 405);
