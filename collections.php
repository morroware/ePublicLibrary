<?php
/**
 * Collections / shelves index. Auth required.
 *
 *   GET  /collections.php             → list user's shelves with covers
 *   POST verb=create                  → create a new shelf
 *   POST verb=update id=...           → rename / change visibility / description
 *   POST verb=delete id=...           → delete a custom shelf (not system)
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

require_auth();
$user = current_user();

if (is_post()) {
    csrf_verify_or_abort();
    $verb = (string) ($_POST['verb'] ?? '');

    if ($verb === 'create') {
        try {
            $id = CollectionRepository::create((int) $user['id'], [
                'name'        => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? null,
                'is_public'   => !empty($_POST['is_public']),
            ]);
            AuditLogger::log('collection.create', 'collection', $id);
            flash('success', 'Shelf created.');
        } catch (Throwable $e) {
            flash('error', 'Could not create shelf: ' . $e->getMessage());
        }
        redirect('collections.php');
    }

    if ($verb === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $coll = $id > 0 ? CollectionRepository::findById($id) : null;
        if (!$coll || (int) $coll['user_id'] !== (int) $user['id']) {
            flash('error', 'Shelf not found.');
            redirect('collections.php');
        }
        try {
            CollectionRepository::update($id, [
                'name'        => $_POST['name'] ?? null,
                'description' => $_POST['description'] ?? null,
                'is_public'   => !empty($_POST['is_public']),
            ]);
            AuditLogger::log('collection.update', 'collection', $id);
            flash('success', 'Shelf updated.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('collections.php');
    }

    if ($verb === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $coll = $id > 0 ? CollectionRepository::findById($id) : null;
        if (!$coll || (int) $coll['user_id'] !== (int) $user['id']) {
            flash('error', 'Shelf not found.');
            redirect('collections.php');
        }
        if ($coll['is_system']) {
            flash('error', 'System shelves cannot be deleted.');
            redirect('collections.php');
        }
        if (CollectionRepository::delete($id)) {
            AuditLogger::log('collection.delete', 'collection', $id);
            flash('success', 'Shelf deleted.');
        }
        redirect('collections.php');
    }
}

$shelves = CollectionRepository::listForUser((int) $user['id']);

// First-4 cover preview per shelf in a single round-trip (was N+1).
$shelfIds = array_map(static fn($s) => (int) $s['id'], $shelves);
$previewMap = CollectionRepository::previewCoversFor($shelfIds, 4);
foreach ($shelves as &$s) {
    $s['preview_covers'] = $previewMap[(int) $s['id']] ?? [];
}
unset($s);

render('library/collections', [
    'pageTitle' => 'Your shelves',
    'pageClass' => 'collections-page',
    'shelves'   => $shelves,
], 'app');
