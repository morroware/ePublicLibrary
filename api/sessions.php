<?php
/**
 * Reading session lifecycle endpoint.
 *
 *   POST /api/sessions.php   action=start  body={uuid, start_cfi?}
 *     → returns { id }
 *
 *   POST /api/sessions.php   action=heartbeat  body={id, duration_seconds, pages_read?, end_cfi?}
 *     → returns { ok }
 *
 * Both require an authenticated user. The reader.js sessions module pings
 * heartbeat on a 30-second cadence and again on beforeunload (best effort
 * via navigator.sendBeacon).
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

if (!is_authed()) {
    json_error('Sign in to track reading sessions.', 401);
}
$user = current_user();

if (request_method() !== 'POST') {
    json_error('Method not allowed', 405);
}

csrf_verify_or_abort();

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    json_error('Invalid JSON body', 400);
}

$action = (string) ($payload['action'] ?? '');

if ($action === 'start') {
    $bookUuid = (string) ($payload['uuid'] ?? '');
    if (!preg_match('/^[0-9a-f-]{36}$/i', $bookUuid)) {
        json_error('Invalid book UUID', 400);
    }
    $book = BookRepository::findByUuid($bookUuid);
    if (!$book) {
        json_error('Book not found', 404);
    }
    $device = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 80);
    $id = ReadingSessionRepository::start(
        (int) $user['id'],
        (int) $book['id'],
        $payload['start_cfi'] ?? null,
        $device,
    );
    json_response(['id' => $id]);
}

if ($action === 'heartbeat') {
    $id = (int) ($payload['id'] ?? 0);
    if ($id <= 0) {
        json_error('Invalid session id', 400);
    }
    $ok = ReadingSessionRepository::heartbeat((int) $user['id'], $id, [
        'duration_seconds' => $payload['duration_seconds'] ?? 0,
        'pages_read'       => $payload['pages_read']       ?? 0,
        'end_cfi'          => $payload['end_cfi']          ?? null,
    ]);
    json_response(['ok' => $ok]);
}

json_error('Unknown action', 400);
