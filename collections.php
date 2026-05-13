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
        CollectionRepository::update($id, [
            'name'        => $_POST['name'] ?? null,
            'description' => $_POST['description'] ?? null,
            'is_public'   => !empty($_POST['is_public']),
        ]);
        AuditLogger::log('collection.update', 'collection', $id);
        flash('success', 'Shelf updated.');
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

// For each shelf, peek at the first 4 covers to use as a preview mosaic.
foreach ($shelves as &$s) {
    $books = CollectionRepository::booksIn((int) $s['id'], 4);
    $s['preview_covers'] = array_map(static fn($b) => $b['cover_path'] ?: null, $books);
}
unset($s);

render('library/collections', [
    'pageTitle' => 'Your shelves',
    'pageClass' => 'collections-page',
    'shelves'   => $shelves,
], 'app');
