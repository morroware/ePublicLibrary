<?php
/**
 * One-shot importer for the legacy books/ directory.
 *
 * Walks the old books/ tree, parses each EPUB, inserts into the DB, moves
 * the file to storage/books/{shard}/{uuid}.epub, and migrates covers.
 *
 * Idempotent — re-running is safe; existing books (by file_hash) are skipped.
 */
define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$legacyDir = project_path('books');
$results = [];

if (is_post()) {
    csrf_verify_or_abort();
    if (!is_dir($legacyDir)) {
        flash('error', 'No legacy books/ directory found at ' . $legacyDir);
        redirect('admin/scan-books.php');
    }

    @set_time_limit(0);
    @ignore_user_abort(true);

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($legacyDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if (!$file->isFile()) { continue; }
        if (strtolower($file->getExtension()) !== 'epub') { continue; }

        $path = $file->getPathname();
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            $results[] = ['path' => $path, 'ok' => false, 'reason' => 'hash failed'];
            continue;
        }
        $existing = BookRepository::findByHash($hash);
        if ($existing) {
            $results[] = ['path' => $path, 'ok' => true, 'reason' => "already imported as “{$existing['title']}”"];
            continue;
        }

        $validation = EpubValidator::validate($path, $file->getFilename());
        if (!$validation['ok']) {
            $results[] = ['path' => $path, 'ok' => false, 'reason' => $validation['error']];
            continue;
        }
        try {
            $meta = EpubParser::parse($path);
        } catch (Throwable $e) {
            $results[] = ['path' => $path, 'ok' => false, 'reason' => $e->getMessage()];
            continue;
        }

        $uuid = uuid_v4();
        // Copy (don't move) so legacy dir stays intact until admin deletes it
        $dest = BookFileStorage::pathForUuid($uuid);
        if (!@copy($path, $dest)) {
            $results[] = ['path' => $path, 'ok' => false, 'reason' => 'copy failed'];
            continue;
        }
        @chmod($dest, 0640);

        $coverRel = !empty($meta['cover_data'])
            ? ThumbnailService::saveFromBytes($meta['cover_data'], $uuid)
            : null;

        $title  = $meta['title']  ?: pathinfo($file->getFilename(), PATHINFO_FILENAME);
        $author = $meta['author'] ?: 'Unknown';

        $bookId = BookRepository::create([
            'uuid'             => $uuid,
            'slug'             => BookRepository::makeSlug($title),
            'title'            => mb_substr($title, 0, 500),
            'author'           => mb_substr($author, 0, 500),
            'language'         => $meta['language']  ?? null,
            'publisher'        => $meta['publisher'] ?? null,
            'published_date'   => $meta['published'] ?? null,
            'isbn'             => $meta['isbn']      ?? null,
            'description'      => $meta['description'] ?? null,
            'description_html' => $meta['description'] ? nl2br(e($meta['description'])) : null,
            'storage_path'     => shard_for($uuid) . '/' . $uuid . '.epub',
            'cover_path'       => $coverRel,
            'file_size'        => $validation['size'],
            'file_hash'        => $hash,
            'mime_type'        => 'application/epub+zip',
            'status'           => 'published',
            'uploaded_by'      => current_user()['id'],
        ]);

        if (!empty($meta['subjects'])) {
            TagRepository::attachToBook($bookId, $meta['subjects'], 'genre');
        }

        AuditLogger::log('book.imported_legacy', 'book', $bookId, ['source' => $path]);
        $results[] = ['path' => $path, 'ok' => true, 'reason' => "imported as “{$title}”"];
    }
}

render('admin/scan-books', [
    'pageTitle'  => 'Import legacy books',
    'activeNav'  => 'books',
    'legacyDir'  => $legacyDir,
    'legacyHas'  => is_dir($legacyDir),
    'results'    => $results,
], 'admin');
