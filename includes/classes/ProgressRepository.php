<?php
/**
 * ProgressRepository — server-synced reading position per (user, book).
 */

defined('APP_BOOTED') or exit;

class ProgressRepository
{
    public static function get(int $userId, int $bookId): ?array
    {
        $stmt = db()->prepare("SELECT * FROM reading_progress WHERE user_id = ? AND book_id = ? LIMIT 1");
        $stmt->execute([$userId, $bookId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Upsert progress. Used by api/progress.php on a debounced cadence (~5s).
     */
    public static function upsert(int $userId, int $bookId, array $data): void
    {
        $cfi        = (string) ($data['cfi'] ?? '');
        $percentage = max(0, min(100, (float) ($data['percentage'] ?? 0)));
        $chapter    = $data['current_chapter'] ?? null;
        $finished   = !empty($data['finished']) ? now_utc() : null;

        $sql = "INSERT INTO reading_progress
                (user_id, book_id, cfi, percentage, current_chapter, last_read_at, started_at, finished_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?)
                ON DUPLICATE KEY UPDATE
                    cfi             = VALUES(cfi),
                    percentage      = VALUES(percentage),
                    current_chapter = VALUES(current_chapter),
                    last_read_at    = CURRENT_TIMESTAMP,
                    finished_at     = CASE WHEN VALUES(finished_at) IS NOT NULL THEN VALUES(finished_at) ELSE finished_at END";
        $stmt = db()->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $bookId, PDO::PARAM_INT);
        $stmt->bindValue(3, $cfi);
        $stmt->bindValue(4, $percentage);
        $stmt->bindValue(5, $chapter, $chapter === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(6, $finished, $finished === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
    }

    public static function continueReading(int $userId, int $limit = 5): array
    {
        $stmt = db()->prepare("SELECT p.*, b.uuid AS book_uuid, b.title, b.author, b.cover_path
                              FROM reading_progress p
                              JOIN books b ON b.id = p.book_id
                              WHERE p.user_id = ? AND p.finished_at IS NULL
                              ORDER BY p.last_read_at DESC
                              LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function deleteForBook(int $userId, int $bookId): void
    {
        $stmt = db()->prepare("DELETE FROM reading_progress WHERE user_id = ? AND book_id = ?");
        $stmt->execute([$userId, $bookId]);
    }
}
