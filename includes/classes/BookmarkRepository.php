<?php
/**
 * BookmarkRepository — per-user bookmarks at EPUB CFI locations.
 */

defined('APP_BOOTED') or exit;

class BookmarkRepository
{
    public static function listForBook(int $userId, int $bookId): array
    {
        $stmt = db()->prepare("SELECT * FROM bookmarks
                              WHERE user_id = ? AND book_id = ?
                              ORDER BY created_at DESC");
        $stmt->execute([$userId, $bookId]);
        return $stmt->fetchAll();
    }

    public static function create(int $userId, int $bookId, string $cfi, ?string $chapter = null, ?string $label = null): int
    {
        $stmt = db()->prepare("INSERT INTO bookmarks (user_id, book_id, cfi, chapter, label, created_at)
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $bookId, $cfi, $chapter, $label, now_utc()]);
        return (int) db()->lastInsertId();
    }

    public static function delete(int $userId, int $id): bool
    {
        $stmt = db()->prepare("DELETE FROM bookmarks WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function find(int $userId, int $id): ?array
    {
        $stmt = db()->prepare("SELECT * FROM bookmarks WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }
}
