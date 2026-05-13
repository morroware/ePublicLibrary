<?php
/**
 * ReviewRepository — per-user, per-book ratings + optional text reviews.
 *
 * Aggregates (review_count, avg_rating on the books row) are maintained
 * by AFTER INSERT/UPDATE/DELETE triggers from migration 0014.
 */

defined('APP_BOOTED') or exit;

class ReviewRepository
{
    /* --------------- single-row queries --------------- */

    public static function findForUserBook(int $userId, int $bookId): ?array
    {
        $stmt = db()->prepare("SELECT * FROM reviews WHERE user_id = ? AND book_id = ? LIMIT 1");
        $stmt->execute([$userId, $bookId]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare("SELECT * FROM reviews WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /* --------------- listing --------------- */

    /**
     * Reviews for a book, joined with user display info, newest first.
     */
    public static function listForBook(int $bookId, int $limit = 25, int $offset = 0): array
    {
        $stmt = db()->prepare("SELECT r.*, u.username, u.display_name, u.uuid AS user_uuid
                              FROM reviews r
                              JOIN users u ON u.id = r.user_id
                              WHERE r.book_id = ? AND r.status = 'published'
                              ORDER BY r.created_at DESC
                              LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $bookId,  PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,   PDO::PARAM_INT);
        $stmt->bindValue(3, $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countForBook(int $bookId): int
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM reviews WHERE book_id = ? AND status = 'published'");
        $stmt->execute([$bookId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Rating distribution (1–5 counts) for a book. Returns array keyed 1..5.
     */
    public static function distributionForBook(int $bookId): array
    {
        $dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $stmt = db()->prepare("SELECT rating, COUNT(*) AS c FROM reviews
                              WHERE book_id = ? AND status = 'published'
                              GROUP BY rating");
        $stmt->execute([$bookId]);
        foreach ($stmt->fetchAll() as $row) {
            $r = (int) $row['rating'];
            if (isset($dist[$r])) $dist[$r] = (int) $row['c'];
        }
        return $dist;
    }

    /* --------------- mutations --------------- */

    /**
     * Upsert a user's review for a book. Returns the review id.
     * Aggregates on books are refreshed by trigger; we just persist the row.
     */
    public static function upsert(int $userId, int $bookId, array $data): int
    {
        $rating = max(1, min(5, (int) ($data['rating'] ?? 0)));
        $title  = $data['title'] ?? null;
        $body   = $data['body']  ?? null;
        $title  = $title ? mb_substr(trim($title), 0, 200) : null;
        $body   = $body  ? trim($body) : null;

        $existing = self::findForUserBook($userId, $bookId);
        if ($existing) {
            $stmt = db()->prepare("UPDATE reviews
                                  SET rating = ?, title = ?, body = ?, status = 'published'
                                  WHERE id = ?");
            $stmt->execute([$rating, $title, $body, (int) $existing['id']]);
            return (int) $existing['id'];
        }
        $stmt = db()->prepare("INSERT INTO reviews
            (user_id, book_id, rating, title, body, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'published', ?, ?)");
        $stmt->execute([$userId, $bookId, $rating, $title, $body, now_utc(), now_utc()]);
        return (int) db()->lastInsertId();
    }

    public static function delete(int $userId, int $reviewId): bool
    {
        $stmt = db()->prepare("DELETE FROM reviews WHERE id = ? AND user_id = ?");
        $stmt->execute([$reviewId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Admin-only: hide / flag / restore a review.
     */
    public static function setStatus(int $reviewId, string $status): void
    {
        if (!in_array($status, ['published', 'hidden', 'flagged'], true)) {
            return;
        }
        $stmt = db()->prepare("UPDATE reviews SET status = ? WHERE id = ?");
        $stmt->execute([$status, $reviewId]);
    }
}
