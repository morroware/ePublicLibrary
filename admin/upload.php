<?php
/**
 * Admin EPUB upload.
 *
 * Replaces the legacy upload_epub.php (hardcoded SHA256 password) and the
 * completely unauthenticated upload.php. Validates the file thoroughly,
 * parses metadata via DOMDocument, generates a thumbnail, and inserts the
 * book row in a transaction.
 */
define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$messages = [];

if (is_post()) {
    csrf_verify_or_abort();
    if (empty($_FILES['epub']['name']) || !is_array($_FILES['epub']['name'])) {
        $messages[] = ['type' => 'error', 'text' => 'No files were selected.'];
    } else {
        $names    = $_FILES['epub']['name'];
        $tmpNames = $_FILES['epub']['tmp_name'];
        $errors   = $_FILES['epub']['error'];
        $sizes    = $_FILES['epub']['size'];

        for ($i = 0, $n = count($names); $i < $n; $i++) {
            $name = (string) $names[$i];
            if (empty($name)) { continue; }

            try {
                $msg = upload_one_epub($name, (string) $tmpNames[$i], (int) $errors[$i], (int) $sizes[$i]);
                $messages[] = $msg;
            } catch (Throwable $e) {
                log_error($e);
                $messages[] = ['type' => 'error', 'text' => $name . ': ' . $e->getMessage()];
            }
        }
    }
}

render('admin/upload', [
    'pageTitle' => 'Upload books',
    'activeNav' => 'upload',
    'messages'  => $messages,
    'maxMb'     => (int) config('storage.max_upload_mb', 100),
], 'admin');


function upload_one_epub(string $originalName, string $tmpPath, int $error, int $size): array
{
    if ($error !== UPLOAD_ERR_OK) {
        return ['type' => 'error', 'text' => $originalName . ': upload failed (PHP error ' . $error . ').'];
    }
    if (!is_uploaded_file($tmpPath)) {
        return ['type' => 'error', 'text' => $originalName . ': not a valid upload.'];
    }

    // 1. Validate
    $validation = EpubValidator::validate($tmpPath, $originalName);
    if (!$validation['ok']) {
        return ['type' => 'error', 'text' => $originalName . ': ' . $validation['error']];
    }

    // 2. Hash for dedup
    $hash = EpubValidator::sha256($tmpPath);
    if ($hash === '') {
        return ['type' => 'error', 'text' => $originalName . ': could not compute file hash.'];
    }
    $existing = BookRepository::findByHash($hash);
    if ($existing) {
        return ['type' => 'info', 'text' => $originalName . ': already in library as “' . $existing['title'] . '”.'];
    }

    // 3. Parse OPF metadata + extract cover
    $meta = EpubParser::parse($tmpPath);

    // 4. Move file into the books store
    $uuid = uuid_v4();
    $relStoragePath = BookFileStorage::moveIntoStore($tmpPath, $uuid);

    // 5. Save cover image (if available)
    $coverPath = null;
    if (!empty($meta['cover_data'])) {
        $coverPath = ThumbnailService::saveFromBytes($meta['cover_data'], $uuid);
    }

    // 6. Build slug & insert
    $title  = $meta['title']  ?: pathinfo($originalName, PATHINFO_FILENAME);
    $author = $meta['author'] ?: 'Unknown';
    $slug   = BookRepository::makeSlug($title);

    $bookId = BookRepository::create([
        'uuid'             => $uuid,
        'slug'             => $slug,
        'title'            => mb_substr($title,  0, 500),
        'author'           => mb_substr($author, 0, 500),
        'language'         => $meta['language']  ?? null,
        'publisher'        => $meta['publisher'] ?? null,
        'published_date'   => $meta['published'] ?? null,
        'isbn'             => $meta['isbn']      ?? null,
        'description'      => $meta['description'] ?? null,
        'description_html' => $meta['description'] ? nl2br(e($meta['description'])) : null,
        'storage_path'     => $relStoragePath,
        'cover_path'       => $coverPath,
        'file_size'        => $size,
        'file_hash'        => $hash,
        'mime_type'        => 'application/epub+zip',
        'status'           => 'published',
        'uploaded_by'      => current_user()['id'],
    ]);

    // 7. Attach genre tags from <dc:subject>
    if (!empty($meta['subjects'])) {
        TagRepository::attachToBook($bookId, $meta['subjects'], 'genre');
    }

    AuditLogger::log('book.upload', 'book', $bookId, [
        'title' => $title, 'author' => $author, 'size' => $size,
    ]);

    return ['type' => 'success', 'text' => $originalName . ': uploaded as “' . $title . '”.'];
}
