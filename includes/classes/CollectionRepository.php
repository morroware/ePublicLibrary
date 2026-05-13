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

    /**
     * Batched preview-cover fetch for the shelves index. Replaces an
     * N+1 loop of booksIn() calls — one round-trip regardless of shelf
     * count. Returns ['collectionId' => [cover_path|null, ...]].
     */
    public static function previewCoversFor(array $collectionIds, int $perShelf = 4): array
    {
        $previews = [];
        $collectionIds = array_values(array_unique(array_map('intval', $collectionIds)));
        if (!$collectionIds) {
            return $previews;
        }
        foreach ($collectionIds as $cid) {
            $previews[$cid] = [];
        }
        $placeholders = implode(',', array_fill(0, count($collectionIds), '?'));
        $stmt = db()->prepare("SELECT cb.collection_id, b.cover_path
                              FROM collection_books cb
                              JOIN books b ON b.id = cb.book_id
                              WHERE cb.collection_id IN ($placeholders)
                                AND b.status = 'published'
                              ORDER BY cb.collection_id, cb.position ASC, cb.added_at ASC");
        $stmt->execute($collectionIds);
        foreach ($stmt->fetchAll() as $row) {
            $cid = (int) $row['collection_id'];
            if (count($previews[$cid]) < $perShelf) {
                $previews[$cid][] = $row['cover_path'] ?: null;
            }
        }
        return $previews;
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

    public const NAME_MAX        = 120;
    public const DESCRIPTION_MAX = 500;

    public static function create(int $userId, array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Collection name is required.');
        }
        if (mb_strlen($name) > self::NAME_MAX) {
            throw new InvalidArgumentException('Collection name is too long (max ' . self::NAME_MAX . ' characters).');
        }
        $description = isset($data['description']) ? (string) $data['description'] : null;
        if ($description !== null) {
            $description = trim($description);
            if (mb_strlen($description) > self::DESCRIPTION_MAX) {
                throw new InvalidArgumentException('Description is too long (max ' . self::DESCRIPTION_MAX . ' characters).');
            }
            if ($description === '') $description = null;
        }
        $isPublic = !empty($data['is_public']) ? 1 : 0;

        // Slug uniqueness is enforced by a UNIQUE index; retry on the rare race.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $slug = self::uniqueSlug($userId, self::slugify($name));
            try {
                $stmt = db()->prepare("INSERT INTO collections
                    (user_id, name, slug, description, is_public, is_system, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
                $stmt->execute([$userId, mb_substr($name, 0, self::NAME_MAX), $slug, $description, $isPublic, now_utc(), now_utc()]);
                return (int) db()->lastInsertId();
            } catch (PDOException $e) {
                // 23000 = integrity constraint (duplicate slug). Retry with a new suffix.
                if ($e->getCode() !== '23000' || $attempt === 2) {
                    throw $e;
                }
            }
        }
        throw new RuntimeException('Could not create shelf (slug collision).');
    }

    public static function update(int $collectionId, array $fields): void
    {
        $coll = self::findById($collectionId);
        if (!$coll) return;

        $set = []; $params = [];
        if (isset($fields['name'])) {
            $name = trim((string) $fields['name']);
            if ($name !== '') {
                if (mb_strlen($name) > self::NAME_MAX) {
                    throw new InvalidArgumentException('Collection name is too long (max ' . self::NAME_MAX . ' characters).');
                }
                $set[] = 'name = ?';
                $params[] = mb_substr($name, 0, self::NAME_MAX);
                // Update slug too if it isn't a system collection
                if (!$coll['is_system']) {
                    $set[] = 'slug = ?';
                    $params[] = self::uniqueSlug((int) $coll['user_id'], self::slugify($name), $collectionId);
                }
            }
        }
        if (array_key_exists('description', $fields)) {
            $desc = $fields['description'];
            if ($desc !== null) {
                $desc = trim((string) $desc);
                if (mb_strlen($desc) > self::DESCRIPTION_MAX) {
                    throw new InvalidArgumentException('Description is too long (max ' . self::DESCRIPTION_MAX . ' characters).');
                }
                if ($desc === '') $desc = null;
            }
            $set[] = 'description = ?';
            $params[] = $desc;
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
