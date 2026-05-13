<?php
/**
 * Library home page.
 *
 * Replaces the legacy index.php which walked the filesystem on every request.
 * Books are now queried from the DB via BookRepository.
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

if (!config_exists()) {
    // bootstrap already redirected; safety net.
    redirect('setup.php');
}

// JSON autocomplete endpoint kept on this URL for backwards compat with bookmarks.
// New code should hit /api/search.php instead.
if (isset($_GET['autocomplete'])) {
    $term = (string) $_GET['autocomplete'];
    json_response(BookRepository::autocomplete($term, 8));
}

$searchTerm  = trim((string) ($_GET['search'] ?? ''));
$searchField = (string) ($_GET['field'] ?? 'all');
$sortBy      = (string) ($_GET['sort']   ?? 'title');
$sortDir     = (string) ($_GET['order']  ?? 'asc');
$page        = max(1, (int) ($_GET['page'] ?? 1));

$result = BookRepository::paginate([
    'page'     => $page,
    'per_page' => 25,
    'search'   => $searchTerm,
    'field'    => $searchField,
    'sort_by'  => $sortBy,
    'sort_dir' => $sortDir,
]);

render('library/index', [
    'pageTitle'    => $searchTerm !== ''
        ? 'Search: ' . $searchTerm
        : config('app_name', 'ePublicLibrary'),
    'pageClass'    => 'library-page',
    'books'        => $result['items'],
    'total'        => $result['total'],
    'page'         => $result['page'],
    'pages'        => $result['pages'],
    'searchTerm'   => $searchTerm,
    'queryParams'  => array_filter([
        'search' => $searchTerm,
        'field'  => $searchField !== 'all' ? $searchField : null,
        'sort'   => $sortBy   !== 'title' ? $sortBy : null,
        'order'  => $sortDir  !== 'asc' ? $sortDir : null,
    ]),
]);
