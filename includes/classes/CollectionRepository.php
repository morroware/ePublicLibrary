<?php
/**
 * CollectionRepository — user shelves (Favorites, Want to Read, custom).
 *
 * Each shelf belongs to exactly one user. System shelves (favorites,
 * want-to-read, finished) are auto-seeded on registration; users can
 * create as many custom shelves as they like.
 */

defined('APP_BOOTED') or exit;

class CollectionRepository
{
    public const SYSTEM_COLLECTIONS = [
        ['slug' => 'favorites',     'name' => 'Favorites',     'description' => 'Books you love.'],
        ['slug' => 'want-to-read',  'name' => 'Want to Read',  'description' => 'On your reading list.'],
        ['slug' => 'finished',      'name' => 'Finished',      'description' => 'Books you have completed.'],
    ];

    /* --------------- seeding --------------- */

    public static function seedSystemCollections(int $userId): void
    {
        foreach (self::SYSTEM_COLLECTIONS as $c) {
            $stmt = db()->prepare("INSERT IGNORE INTO collections
                (user_id, name, slug, description, is_public, is_system, created_at, updated_at)
                VALUES (?, ?, ?, ?, 0, 1, ?, ?)");
            $now = now_utc();
            $stmt->execute([$userId, $c['name'], $c['slug'], $c['description'], $now, $now]);
        }
    }

    /* --------------- single-row queries --------------- */

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare("SELECT * FROM collections WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(int $userId, string $slug): ?array
    {
        $stmt = db()->prepare("SELECT * FROM collections WHERE user_id = ? AND slug = ? LIMIT 1");
        $stmt->execute([$userId, $slug]);
        return $stmt->fetch() ?: null;
    }

    /* --------------- listing --------------- */

    /** All of a user's shelves, with cached book counts, system shelves first. */
    public static function listForUser(int $userId): array
    {
        $stmt = db()->prepare("SELECT c.*, COUNT(cb.book_id) AS book_count
                              FROM collections c
                              LEFT JOIN collection_books cb ON cb.collection_id = c.id
                              WHERE c.user_id = ?
                              GROUP BY c.id
                              ORDER BY c.is_system DESC, c.name ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Books in a shelf, oldest-added first by default; pass position-based to
     * honor manual reordering.
     */
    public static function booksIn(int $collectionId, int $limit = 200, int $offset = 0): array
    {
        $stmt = db()->prepare("SELECT b.*, cb.position, cb.added_at
                              FROM collection_books cb
                              JOIN books b ON b.id = cb.book_id
                              WHERE cb.collection_id = ? AND b.status = 'published'
                              ORDER BY cb.position ASC, cb.added_at ASC
                              LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $collectionId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,        PDO::PARAM_INT);
        $stmt->bindValue(3, $offset,       PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countBooks(int $collectionId): int
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM collection_books cb
                              JOIN books b ON b.id = cb.book_id
                              WHERE cb.collection_id = ? AND b.status = 'published'");
        $stmt->execute([$collectionId]);
        return (int) $stmt->fetchColumn();
    }

    /** Which shelves of $userId contain $bookId (used by the "Add to shelf" UI). */
    public static function forUserAndBook(int $userId, int $bookId): array
    {
        $stmt = db()->prepare("SELECT c.id, c.slug, c.name, c.is_system,
                                     EXISTS (SELECT 1 FROM collection_books cb
                                             WHERE cb.collection_id = c.id AND cb.book_id = ?) AS contains_book
                              FROM collections c
                              WHERE c.user_id = ?
                              ORDER BY c.is_system DESC, c.name ASC");
        $stmt->execute([$bookId, $userId]);
        return $stmt->fetchAll();
    }

    public static function containsBook(int $collectionId, int $bookId): bool
    {
        $stmt = db()->prepare("SELECT 1 FROM collection_books WHERE collection_id = ? AND book_id = ? LIMIT 1");
        $stmt->execute([$collectionId, $bookId]);
        return (bool) $stmt->fetchColumn();
    }

    /* --------------- mutations --------------- */

    public static function create(int $userId, array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Collection name is required.');
        }
        $slug        = self::uniqueSlug($userId, self::slugify($name));
        $description = $data['description'] ?? null;
        $isPublic    = !empty($data['is_public']) ? 1 : 0;

        $stmt = db()->prepare("INSERT INTO collections
            (user_id, name, slug, description, is_public, is_system, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
        $stmt->execute([$userId, mb_substr($name, 0, 120), $slug, $description, $isPublic, now_utc(), now_utc()]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $collectionId, array $fields): void
    {
        $coll = self::findById($collectionId);
        if (!$coll) return;

        $set = []; $params = [];
        if (isset($fields['name'])) {
            $name = trim((string) $fields['name']);
            if ($name !== '') {
                $set[] = 'name = ?';
                $params[] = mb_substr($name, 0, 120);
                // Update slug too if it isn't a system collection
                if (!$coll['is_system']) {
                    $set[] = 'slug = ?';
                    $params[] = self::uniqueSlug((int) $coll['user_id'], self::slugify($name), $collectionId);
                }
            }
        }
        if (array_key_exists('description', $fields)) {
            $set[] = 'description = ?';
            $params[] = $fields['description'] !== null ? (string) $fields['description'] : null;
        }
        if (array_key_exists('is_public', $fields)) {
            $set[] = 'is_public = ?';
            $params[] = $fields['is_public'] ? 1 : 0;
        }
        if (!$set) return;
        $set[] = 'updated_at = ?';
        $params[] = now_utc();
        $params[] = $collectionId;
        $stmt = db()->prepare('UPDATE collections SET ' . implode(', ', $set) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    /** Delete a non-system shelf. Returns true if deleted. */
    public static function delete(int $collectionId): bool
    {
        $coll = self::findById($collectionId);
        if (!$coll || $coll['is_system']) return false;
        db()->prepare("DELETE FROM collections WHERE id = ?")->execute([$collectionId]);
        return true;
    }

    public static function addBook(int $collectionId, int $bookId): void
    {
        // Position defaults to the next slot
        $stmt = db()->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM collection_books WHERE collection_id = ?");
        $stmt->execute([$collectionId]);
        $pos = (int) $stmt->fetchColumn();
        db()->prepare("INSERT IGNORE INTO collection_books (collection_id, book_id, position, added_at)
                       VALUES (?, ?, ?, ?)")
            ->execute([$collectionId, $bookId, $pos, now_utc()]);
    }

    public static function removeBook(int $collectionId, int $bookId): void
    {
        db()->prepare("DELETE FROM collection_books WHERE collection_id = ? AND book_id = ?")
            ->execute([$collectionId, $bookId]);
    }

    /**
     * Reorder books in a shelf. $orderedBookIds is the full new order.
     * Books not in the list are appended after.
     */
    public static function reorder(int $collectionId, array $orderedBookIds): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE collection_books SET position = ? WHERE collection_id = ? AND book_id = ?");
            foreach (array_values($orderedBookIds) as $pos => $bookId) {
                $stmt->execute([(int) $pos, $collectionId, (int) $bookId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /* --------------- helpers --------------- */

    private static function slugify(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('~[^\pL\d]+~u', '-', $value) ?? '';
        $value = trim($value, '-');
        $value = iconv('utf-8', 'ascii//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('~[^-\w]+~', '', $value) ?? '';
        return substr($value, 0, 100) ?: 'shelf';
    }

    private static function uniqueSlug(int $userId, string $base, ?int $excludeId = null): string
    {
        $slug = $base;
        $i = 2;
        while (self::slugTaken($userId, $slug, $excludeId)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private static function slugTaken(int $userId, string $slug, ?int $excludeId): bool
    {
        if ($excludeId !== null) {
            $stmt = db()->prepare("SELECT 1 FROM collections WHERE user_id = ? AND slug = ? AND id <> ? LIMIT 1");
            $stmt->execute([$userId, $slug, $excludeId]);
        } else {
            $stmt = db()->prepare("SELECT 1 FROM collections WHERE user_id = ? AND slug = ? LIMIT 1");
            $stmt->execute([$userId, $slug]);
        }
        return (bool) $stmt->fetchColumn();
    }
}
