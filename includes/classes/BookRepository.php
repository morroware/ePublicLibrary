<?php
/**
 * BookRepository — all books-table SQL.
 *
 * Phase 1 queries the DB directly. The legacy filesystem-scanning approach
 * (recursive iterator + regex OPF parsing on every request) is gone.
 */

defined('APP_BOOTED') or exit;

class BookRepository
{
    /* --------------- single-row queries --------------- */

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare("SELECT * FROM books WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByUuid(string $uuid): ?array
    {
        $stmt = db()->prepare("SELECT * FROM books WHERE uuid = ? LIMIT 1");
        $stmt->execute([$uuid]);
        return $stmt->fetch() ?: null;
    }

    public static function findByHash(string $hash): ?array
    {
        $stmt = db()->prepare("SELECT * FROM books WHERE file_hash = ? LIMIT 1");
        $stmt->execute([$hash]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = db()->prepare("SELECT * FROM books WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /* --------------- listing / search --------------- */

    /**
     * Paginated list with optional search + sort.
     *
     * Returns [
     *   'items' => array of rows,
     *   'total' => int,
     *   'page'  => int,
     *   'pages' => int,
     * ]
     */
    public static function paginate(array $opts = []): array
    {
        $page    = max(1, (int) ($opts['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($opts['per_page'] ?? 25)));
        $search  = trim((string) ($opts['search'] ?? ''));
        $field   = (string) ($opts['field'] ?? 'all');
        $sortBy  = (string) ($opts['sort_by'] ?? 'title');
        $sortDir = strtolower((string) ($opts['sort_dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $allowedSort = ['title' => 'title', 'author' => 'author', 'published' => 'published_date',
                        'created' => 'created_at', 'updated' => 'updated_at'];
        $sortCol = $allowedSort[$sortBy] ?? 'title';

        $where  = ["status = 'published'"];
        $params = [];

        if ($search !== '') {
            $like = '%' . self::escapeLike($search) . '%';
            switch ($field) {
                case 'title':
                    $where[] = 'title LIKE ?';
                    $params[] = $like;
                    break;
                case 'author':
                    $where[] = 'author LIKE ?';
                    $params[] = $like;
                    break;
                case 'genre':
                    // Genre filtering goes via tags — fall through to subquery
                    $where[] = 'id IN (SELECT bt.book_id FROM book_tags bt
                                       JOIN tags t ON t.id = bt.tag_id
                                       WHERE t.name LIKE ? OR t.slug LIKE ?)';
                    $params[] = $like;
                    $params[] = $like;
                    break;
                case 'all':
                default:
                    $where[] = '(title LIKE ? OR author LIKE ? OR description LIKE ?)';
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                    break;
            }
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = db()->prepare("SELECT COUNT(*) FROM books WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $listSql = "SELECT * FROM books WHERE {$whereSql}
                    ORDER BY {$sortCol} {$sortDir}, id ASC
                    LIMIT ? OFFSET ?";
        $listStmt = db()->prepare($listSql);
        $i = 1;
        foreach ($params as $p) {
            $listStmt->bindValue($i++, $p);
        }
        $listStmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $listStmt->bindValue($i,   $offset,  PDO::PARAM_INT);
        $listStmt->execute();

        return [
            'items' => $listStmt->fetchAll(),
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /**
     * Autocomplete suggestions — titles, authors, genres matching a prefix.
     */
    public static function autocomplete(string $term, int $limit = 8): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }
        $like = self::escapeLike($term) . '%';
        $suggestions = [];

        $titleStmt = db()->prepare("SELECT DISTINCT title FROM books
                                    WHERE status='published' AND title LIKE ?
                                    ORDER BY title LIMIT ?");
        $titleStmt->bindValue(1, $like);
        $titleStmt->bindValue(2, $limit, PDO::PARAM_INT);
        $titleStmt->execute();
        foreach ($titleStmt->fetchAll() as $r) { $suggestions[] = $r['title']; }

        $authorStmt = db()->prepare("SELECT DISTINCT author FROM books
                                     WHERE status='published' AND author LIKE ?
                                     ORDER BY author LIMIT ?");
        $authorStmt->bindValue(1, $like);
        $authorStmt->bindValue(2, $limit, PDO::PARAM_INT);
        $authorStmt->execute();
        foreach ($authorStmt->fetchAll() as $r) { $suggestions[] = $r['author']; }

        return array_values(array_unique($suggestions));
    }

    /* --------------- mutations --------------- */

    public static function create(array $data): int
    {
        $cols = ['uuid','slug','title','subtitle','author','language','publisher','published_date',
                 'isbn','description','description_html','storage_path','cover_path',
                 'file_size','file_hash','mime_type','status','uploaded_by'];
        $values = [];
        foreach ($cols as $c) {
            $values[$c] = $data[$c] ?? null;
        }
        $values['uuid']   = $values['uuid']   ?? uuid_v4();
        $values['status'] = $values['status'] ?? 'published';

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO books (' . implode(', ', $cols) . ', created_at, updated_at)
                VALUES (' . $placeholders . ', ?, ?)';
        $stmt = db()->prepare($sql);
        $stmt->execute(array_merge(array_values($values), [now_utc(), now_utc()]));
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $fields): void
    {
        $allowed = ['title','subtitle','author','language','publisher','published_date',
                    'isbn','description','description_html','cover_path','status','slug'];
        $set = [];
        $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $set[] = "{$col} = ?";
                $params[] = $fields[$col];
            }
        }
        if ($set === []) {
            return;
        }
        $set[] = "updated_at = ?";
        $params[] = now_utc();
        $params[] = $id;
        $sql = "UPDATE books SET " . implode(', ', $set) . " WHERE id = ?";
        db()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        db()->prepare("DELETE FROM books WHERE id = ?")->execute([$id]);
    }

    public static function incrementDownloadCount(int $id): void
    {
        db()->prepare("UPDATE books SET download_count = download_count + 1 WHERE id = ?")->execute([$id]);
    }

    public static function incrementReadCount(int $id): void
    {
        db()->prepare("UPDATE books SET read_count = read_count + 1 WHERE id = ?")->execute([$id]);
    }

    /* --------------- helpers --------------- */

    public static function totalCount(): int
    {
        return (int) db()->query("SELECT COUNT(*) FROM books")->fetchColumn();
    }

    public static function makeSlug(string $title, ?int $excludeId = null): string
    {
        $base = self::slugify($title);
        if ($base === '') {
            $base = 'book';
        }
        $candidate = $base;
        $i = 2;
        while (self::slugExists($candidate, $excludeId)) {
            $candidate = $base . '-' . $i++;
        }
        return $candidate;
    }

    private static function slugExists(string $slug, ?int $excludeId): bool
    {
        if ($excludeId !== null) {
            $stmt = db()->prepare("SELECT 1 FROM books WHERE slug = ? AND id <> ? LIMIT 1");
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = db()->prepare("SELECT 1 FROM books WHERE slug = ? LIMIT 1");
            $stmt->execute([$slug]);
        }
        return (bool) $stmt->fetchColumn();
    }

    private static function slugify(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('~[^\pL\d]+~u', '-', $value) ?? '';
        $value = trim($value, '-');
        $value = iconv('utf-8', 'ascii//TRANSLIT//IGNORE', $value);
        $value = preg_replace('~[^-\w]+~', '', $value) ?? '';
        return substr($value, 0, 200);
    }

    private static function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
