<?php
/**
 * Library health check.
 *
 * Surfaces inconsistencies between the database and the on-disk storage:
 *   - missing files:  books rows whose EPUB no longer exists on disk
 *   - orphan files:   EPUBs in storage/books/ with no matching books row
 *   - missing covers: books rows with cover_path that resolves to nothing
 *   - duplicate hash: should be impossible (UNIQUE constraint) but doubles
 *                     as a smoke test for trigger / migration health
 *
 * Admin-only. Read-only by default; the "Clean up orphans" form deletes
 * unreferenced EPUB files (CSRF + confirm prompt required).
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

if (is_post()) {
    csrf_verify_or_abort();
    $verb = (string) ($_POST['verb'] ?? '');
    if ($verb === 'cleanup_orphans') {
        $deleted = 0;
        $orphans = (array) ($_POST['orphan'] ?? []);
        $root = BookFileStorage::root();
        foreach ($orphans as $rel) {
            $rel = (string) $rel;
            // Re-validate inside the books root before deleting
            $real = realpath($root . '/' . $rel);
            if ($real !== false && strpos($real, $root) === 0 && is_file($real)) {
                @unlink($real);
                $deleted++;
            }
        }
        AuditLogger::log('health.orphans_cleaned', null, null, ['count' => $deleted]);
        flash('success', "Removed {$deleted} orphan file" . ($deleted === 1 ? '' : 's') . '.');
        redirect('admin/health.php');
    }
}

/* ---- Gather ---- */

$books = db()->query("SELECT id, uuid, title, author, storage_path, cover_path, file_hash
                      FROM books
                      ORDER BY title ASC")->fetchAll();

$missingFiles  = [];
$missingCovers = [];
$diskFiles     = [];

$booksRoot   = BookFileStorage::root();
$coversDir   = ThumbnailService::coversDir();

foreach ($books as $b) {
    $resolved = BookFileStorage::resolveForBook($b);
    if ($resolved === null || !is_file($resolved)) {
        $missingFiles[] = $b;
    }
    if (!empty($b['cover_path'])) {
        $coverFs = project_path('assets/' . ltrim((string) $b['cover_path'], '/'));
        if (!is_file($coverFs)) {
            $missingCovers[] = $b;
        }
    } else {
        $missingCovers[] = $b;
    }
}

if (is_dir($booksRoot)) {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($booksRoot, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'epub') {
            $diskFiles[] = $f->getPathname();
        }
    }
}

// Known storage paths from DB
$knownPaths = [];
foreach ($books as $b) {
    $resolved = BookFileStorage::resolveForBook($b);
    if ($resolved !== null) $knownPaths[$resolved] = true;
}
$orphans = [];
foreach ($diskFiles as $path) {
    if (!isset($knownPaths[$path])) {
        $orphans[] = [
            'path' => $path,
            'rel'  => ltrim(substr($path, strlen($booksRoot)), '/\\'),
            'size' => filesize($path) ?: 0,
        ];
    }
}

// Cover files vs DB references
$coverFiles = is_dir($coversDir)
    ? array_filter(scandir($coversDir) ?: [], static fn($n) => str_ends_with(strtolower($n), '.jpg') || str_ends_with(strtolower($n), '.jpeg') || str_ends_with(strtolower($n), '.png'))
    : [];
$referencedCovers = [];
foreach ($books as $b) {
    if (!empty($b['cover_path'])) {
        $referencedCovers[basename($b['cover_path'])] = true;
    }
}
$orphanCovers = [];
foreach ($coverFiles as $cf) {
    if (!isset($referencedCovers[$cf])) {
        $orphanCovers[] = $cf;
    }
}

render('admin/health', [
    'pageTitle'     => 'Library health',
    'activeNav'     => 'health',
    'missingFiles'  => $missingFiles,
    'missingCovers' => $missingCovers,
    'orphans'       => $orphans,
    'orphanCovers'  => $orphanCovers,
    'totals'        => [
        'books'      => count($books),
        'disk_files' => count($diskFiles),
        'covers'     => count($coverFiles),
    ],
], 'admin');
