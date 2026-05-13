<?php
/**
 * Admin: list, edit, and delete books.
 */
define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$action = (string) ($_GET['action'] ?? 'list');

if (is_post()) {
    csrf_verify_or_abort();
    $verb = (string) ($_POST['verb'] ?? '');

    if ($verb === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $book = $id > 0 ? BookRepository::findById($id) : null;
        if (!$book) {
            flash('error', 'Book not found.');
            redirect('admin/books.php');
        }
        $fields = [
            'title'          => trim((string) ($_POST['title'] ?? '')) ?: $book['title'],
            'subtitle'       => trim((string) ($_POST['subtitle'] ?? '')) ?: null,
            'author'         => trim((string) ($_POST['author'] ?? '')) ?: 'Unknown',
            'language'       => trim((string) ($_POST['language'] ?? '')) ?: null,
            'publisher'      => trim((string) ($_POST['publisher'] ?? '')) ?: null,
            'published_date' => trim((string) ($_POST['published_date'] ?? '')) ?: null,
            'isbn'           => trim((string) ($_POST['isbn'] ?? '')) ?: null,
            'description'    => trim((string) ($_POST['description'] ?? '')) ?: null,
            'status'         => in_array($_POST['status'] ?? '', ['published','hidden','removed'], true) ? $_POST['status'] : $book['status'],
        ];
        $fields['description_html'] = $fields['description'] ? nl2br(e($fields['description'])) : null;
        if ($fields['title'] !== $book['title']) {
            $fields['slug'] = BookRepository::makeSlug($fields['title'], $id);
        }

        BookRepository::update($id, $fields);

        // Tags
        if (isset($_POST['genres'])) {
            $genres = array_filter(array_map('trim', explode(',', (string) $_POST['genres'])));
            TagRepository::detachAll($id);
            TagRepository::attachToBook($id, $genres, 'genre');
        }

        AuditLogger::log('book.update', 'book', $id);
        flash('success', '“' . $fields['title'] . '” updated.');
        redirect('admin/books.php?action=edit&id=' . $id);
    }

    if ($verb === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $book = $id > 0 ? BookRepository::findById($id) : null;
        if (!$book) {
            flash('error', 'Book not found.');
            redirect('admin/books.php');
        }
        BookFileStorage::deleteForBook($book);
        ThumbnailService::delete($book['uuid']);
        BookRepository::delete($id);
        AuditLogger::log('book.delete', 'book', $id, ['title' => $book['title']]);
        flash('success', '“' . $book['title'] . '” deleted.');
        redirect('admin/books.php');
    }
}

if ($action === 'edit') {
    $id = (int) ($_GET['id'] ?? 0);
    $book = $id > 0 ? BookRepository::findById($id) : null;
    if (!$book) {
        flash('error', 'Book not found.');
        redirect('admin/books.php');
    }
    $tags = TagRepository::forBook($id);
    render('admin/book-edit', [
        'pageTitle' => 'Edit: ' . $book['title'],
        'activeNav' => 'books',
        'book'      => $book,
        'tags'      => $tags,
    ], 'admin');
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim((string) ($_GET['q'] ?? ''));
$result = BookRepository::paginate([
    'page' => $page,
    'per_page' => 50,
    'search' => $search,
    'sort_by' => 'created',
    'sort_dir' => 'desc',
]);

render('admin/books', [
    'pageTitle' => 'Books',
    'activeNav' => 'books',
    'books'     => $result['items'],
    'total'     => $result['total'],
    'page'      => $result['page'],
    'pages'     => $result['pages'],
    'searchTerm'=> $search,
], 'admin');
