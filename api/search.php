<?php
/**
 * Library search / autocomplete JSON endpoint.
 *
 *   GET /api/search.php?q=...        → autocomplete suggestions (titles, authors)
 *   GET /api/search.php?q=...&full=1 → full book list (subset of fields)
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '') {
    json_response([]);
}

if (!empty($_GET['full'])) {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $result = BookRepository::paginate([
        'page'     => $page,
        'per_page' => 20,
        'search'   => $q,
        'field'    => $_GET['field'] ?? 'all',
    ]);
    $items = array_map(static function (array $b): array {
        return [
            'uuid'   => $b['uuid'],
            'title'  => $b['title'],
            'author' => $b['author'],
            'cover'  => $b['cover_path'] ? asset($b['cover_path']) : null,
            'url'    => url('read.php?b=' . $b['uuid']),
        ];
    }, $result['items']);
    json_response([
        'items' => $items,
        'total' => $result['total'],
        'page'  => $result['page'],
        'pages' => $result['pages'],
    ]);
}

// Default: lightweight autocomplete
json_response(BookRepository::autocomplete($q, 8));
