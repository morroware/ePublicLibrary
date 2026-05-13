<?php
/**
 * CollectionRepository — user shelves (Favorites, Want to Read, custom).
 */

defined('APP_BOOTED') or exit;

class CollectionRepository
{
    public const SYSTEM_COLLECTIONS = [
        ['slug' => 'favorites',     'name' => 'Favorites',     'description' => 'Books you love.'],
        ['slug' => 'want-to-read',  'name' => 'Want to Read',  'description' => 'On your reading list.'],
        ['slug' => 'finished',      'name' => 'Finished',      'description' => 'Books you have completed.'],
    ];

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

    public static function addBook(int $collectionId, int $bookId): void
    {
        db()->prepare("INSERT IGNORE INTO collection_books (collection_id, book_id, added_at)
                       VALUES (?, ?, ?)")
            ->execute([$collectionId, $bookId, now_utc()]);
    }

    public static function removeBook(int $collectionId, int $bookId): void
    {
        db()->prepare("DELETE FROM collection_books WHERE collection_id = ? AND book_id = ?")
            ->execute([$collectionId, $bookId]);
    }

    public static function findBySlug(int $userId, string $slug): ?array
    {
        $stmt = db()->prepare("SELECT * FROM collections WHERE user_id = ? AND slug = ? LIMIT 1");
        $stmt->execute([$userId, $slug]);
        return $stmt->fetch() ?: null;
    }
}
