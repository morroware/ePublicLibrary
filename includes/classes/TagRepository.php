<?php
/**
 * TagRepository — genres / subjects / custom tags attached to books.
 */

defined('APP_BOOTED') or exit;

class TagRepository
{
    public static function findOrCreate(string $name, string $kind = 'genre'): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $slug = self::slugify($name);
        $stmt = db()->prepare("SELECT id FROM tags WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        $ins = db()->prepare("INSERT INTO tags (slug, name, kind, created_at) VALUES (?, ?, ?, ?)");
        $ins->execute([$slug, $name, $kind, now_utc()]);
        return (int) db()->lastInsertId();
    }

    public static function attachToBook(int $bookId, array $tagNames, string $kind = 'genre'): void
    {
        foreach ($tagNames as $name) {
            $tagId = self::findOrCreate($name, $kind);
            if ($tagId > 0) {
                db()->prepare("INSERT IGNORE INTO book_tags (book_id, tag_id) VALUES (?, ?)")
                    ->execute([$bookId, $tagId]);
            }
        }
    }

    public static function detachAll(int $bookId): void
    {
        db()->prepare("DELETE FROM book_tags WHERE book_id = ?")->execute([$bookId]);
    }

    public static function forBook(int $bookId): array
    {
        $stmt = db()->prepare("SELECT t.* FROM tags t
                              JOIN book_tags bt ON bt.tag_id = t.id
                              WHERE bt.book_id = ?
                              ORDER BY t.name");
        $stmt->execute([$bookId]);
        return $stmt->fetchAll();
    }

    public static function listAll(string $kind = 'genre'): array
    {
        $stmt = db()->prepare("SELECT t.*, COUNT(bt.book_id) AS book_count
                              FROM tags t
                              LEFT JOIN book_tags bt ON bt.tag_id = t.id
                              WHERE t.kind = ?
                              GROUP BY t.id
                              ORDER BY t.name");
        $stmt->execute([$kind]);
        return $stmt->fetchAll();
    }

    private static function slugify(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('~[^\pL\d]+~u', '-', $value) ?? '';
        $value = trim($value, '-');
        $value = iconv('utf-8', 'ascii//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('~[^-\w]+~', '', $value) ?? '';
        return substr($value, 0, 80) ?: 'tag';
    }
}
