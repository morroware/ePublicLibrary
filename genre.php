<?php
/**
 * Browse all books tagged with a particular genre / subject.
 *   /genre.php?slug={tag_slug}
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') abort(404);

$tagStmt = db()->prepare("SELECT * FROM tags WHERE slug = ? LIMIT 1");
$tagStmt->execute([$slug]);
$tag = $tagStmt->fetch();
if (!$tag) abort(404);

$page    = max(1, (int) ($_GET['page']  ?? 1));
$sortBy  = (string) ($_GET['sort']  ?? 'title');
$sortDir = (string) ($_GET['order'] ?? 'asc');

$result = BookRepository::paginate([
    'page'     => $page,
    'per_page' => 25,
    'tag_slug' => $slug,
    'sort_by'  => $sortBy,
    'sort_dir' => $sortDir,
]);

render('library/genre', [
    'pageTitle'   => $tag['name'] . ' books',
    'pageClass'   => 'genre-page',
    'tag'         => $tag,
    'books'       => $result['items'],
    'total'       => $result['total'],
    'page'        => $result['page'],
    'pages'       => $result['pages'],
    'sortBy'      => $sortBy,
    'sortDir'     => $sortDir,
    'queryParams' => ['slug' => $slug, 'sort' => $sortBy, 'order' => $sortDir],
], 'app');
