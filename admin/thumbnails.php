<?php
/**
 * Admin: regenerate thumbnails. Replaces the legacy generate_thumbnails.php /
 * thumbnail_admin.php combo.
 */
define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$processed = [];
if (is_post()) {
    csrf_verify_or_abort();
    $verb = (string) ($_POST['verb'] ?? '');

    if ($verb === 'rebuild_missing' || $verb === 'rebuild_all') {
        $where = $verb === 'rebuild_missing' ? "WHERE cover_path IS NULL OR cover_path = ''" : '';
        $rows = db()->query("SELECT id, uuid, title, storage_path FROM books {$where} ORDER BY id")->fetchAll();
        foreach ($rows as $b) {
            $book = BookRepository::findById((int) $b['id']);
            $path = BookFileStorage::resolveForBook($book);
            if ($path === null) {
                $processed[] = ['title' => $b['title'], 'ok' => false, 'reason' => 'file missing'];
                continue;
            }
            $rel = ThumbnailService::generateFromEpub($path, $b['uuid']);
            if ($rel) {
                BookRepository::update((int) $b['id'], ['cover_path' => $rel]);
                $processed[] = ['title' => $b['title'], 'ok' => true, 'reason' => 'cover saved'];
            } else {
                $processed[] = ['title' => $b['title'], 'ok' => false, 'reason' => 'no cover found'];
            }
        }
        AuditLogger::log('thumbnails.rebuild', null, null, ['verb' => $verb, 'count' => count($processed)]);
    }
}

$stats = [
    'total'    => (int) db()->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'missing'  => (int) db()->query("SELECT COUNT(*) FROM books WHERE cover_path IS NULL OR cover_path = ''")->fetchColumn(),
    'covers'   => count(glob(ThumbnailService::coversDir() . '/*.jpg') ?: []),
];

render('admin/thumbnails', [
    'pageTitle' => 'Thumbnails',
    'activeNav' => 'thumbnails',
    'stats'     => $stats,
    'processed' => $processed,
], 'admin');
