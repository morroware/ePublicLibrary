<?php
/**
 * ReadingSessionRepository — start/end reading-session brackets and
 * aggregation queries for the stats dashboard.
 *
 * Backed by the `reading_sessions` table (migration 0009).
 *
 * Lifecycle: a session "starts" when the user opens the reader page and
 * "ends" when they leave or after an idle threshold. We accept best-effort
 * heartbeats from the client to update `ended_at` + `duration_seconds`
 * incrementally so a crashed tab doesn't lose the whole session.
 */

defined('APP_BOOTED') or exit;

class ReadingSessionRepository
{
    /* --------------- lifecycle --------------- */

    public static function start(int $userId, int $bookId, ?string $startCfi = null, ?string $deviceLabel = null): int
    {
        $stmt = db()->prepare("INSERT INTO reading_sessions
            (user_id, book_id, started_at, start_cfi, device_label)
            VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?)");
        $stmt->execute([$userId, $bookId, $startCfi, $deviceLabel]);
        return (int) db()->lastInsertId();
    }

    public static function heartbeat(int $userId, int $sessionId, array $fields): bool
    {
        $sess = self::findOwned($userId, $sessionId);
        if (!$sess) return false;
        $duration = max(0, min(60 * 60 * 12, (int) ($fields['duration_seconds'] ?? 0)));
        $pages    = max(0, (int) ($fields['pages_read'] ?? 0));
        $endCfi   = $fields['end_cfi'] ?? null;
        $stmt = db()->prepare("UPDATE reading_sessions
                              SET ended_at = CURRENT_TIMESTAMP,
                                  duration_seconds = ?,
                                  pages_read = ?,
                                  end_cfi = ?
                              WHERE id = ?");
        $stmt->execute([$duration, $pages, $endCfi, $sessionId]);
        return $stmt->rowCount() > 0;
    }

    public static function findOwned(int $userId, int $sessionId): ?array
    {
        $stmt = db()->prepare("SELECT * FROM reading_sessions WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$sessionId, $userId]);
        return $stmt->fetch() ?: null;
    }

    /* --------------- aggregations for the stats dashboard --------------- */

    /**
     * Top-line totals for a user.
     * Returns array with: total_seconds, sessions_count, books_started,
     * books_finished, words_read_estimate, day_streak.
     */
    public static function totals(int $userId): array
    {
        $pdo = db();

        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(duration_seconds), 0) AS total_seconds,
                                         COUNT(*) AS sessions_count
                                  FROM reading_sessions WHERE user_id = ?");
        $sumStmt->execute([$userId]);
        $sum = $sumStmt->fetch();

        $progStmt = $pdo->prepare("SELECT
                                    COUNT(*) AS started,
                                    SUM(CASE WHEN finished_at IS NOT NULL THEN 1 ELSE 0 END) AS finished
                                  FROM reading_progress WHERE user_id = ?");
        $progStmt->execute([$userId]);
        $prog = $progStmt->fetch();

        // Rough words-read estimate: pages_read summed × ~250 words / page.
        $wordsStmt = $pdo->prepare("SELECT COALESCE(SUM(pages_read), 0) FROM reading_sessions WHERE user_id = ?");
        $wordsStmt->execute([$userId]);
        $pages = (int) $wordsStmt->fetchColumn();

        return [
            'total_seconds'    => (int) ($sum['total_seconds'] ?? 0),
            'sessions_count'   => (int) ($sum['sessions_count'] ?? 0),
            'books_started'    => (int) ($prog['started'] ?? 0),
            'books_finished'   => (int) ($prog['finished'] ?? 0),
            'words_read_est'   => $pages * 250,
            'day_streak'       => self::dayStreak($userId),
        ];
    }

    /**
     * Daily totals over the last N days, ordered oldest-first.
     * Returns [{ day: 'YYYY-MM-DD', seconds: int }].
     */
    public static function dailyTotals(int $userId, int $days = 30): array
    {
        $stmt = db()->prepare("SELECT DATE(started_at) AS day,
                                      COALESCE(SUM(duration_seconds), 0) AS seconds
                              FROM reading_sessions
                              WHERE user_id = ? AND started_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
                              GROUP BY DATE(started_at)
                              ORDER BY day ASC");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $days,   PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Fill missing days with zero
        $byDay = [];
        foreach ($rows as $r) { $byDay[$r['day']] = (int) $r['seconds']; }
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', strtotime("-{$i} days"));
            $out[] = ['day' => $day, 'seconds' => $byDay[$day] ?? 0];
        }
        return $out;
    }

    /**
     * Consecutive day-streak ending today (today inclusive if there's at
     * least one session today; otherwise from yesterday backwards).
     */
    public static function dayStreak(int $userId): int
    {
        $stmt = db()->prepare("SELECT DISTINCT DATE(started_at) AS day
                              FROM reading_sessions
                              WHERE user_id = ?
                              ORDER BY day DESC
                              LIMIT 400");
        $stmt->execute([$userId]);
        $days = array_map(static fn($r) => $r['day'], $stmt->fetchAll());
        if (empty($days)) return 0;

        $today     = gmdate('Y-m-d');
        $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
        $cursor = ($days[0] === $today) ? $today : ($days[0] === $yesterday ? $yesterday : null);
        if ($cursor === null) return 0;

        $streak = 0;
        $expected = $cursor;
        foreach ($days as $d) {
            if ($d !== $expected) break;
            $streak++;
            $expected = gmdate('Y-m-d', strtotime("{$d} -1 day"));
        }
        return $streak;
    }

    /**
     * Top books by minutes read. Returns rows joined with the book.
     */
    public static function topBooks(int $userId, int $limit = 5): array
    {
        $stmt = db()->prepare("SELECT rs.book_id,
                                      COALESCE(SUM(rs.duration_seconds), 0) AS seconds,
                                      MAX(rs.ended_at)                       AS last_read_at,
                                      b.uuid, b.title, b.author, b.cover_path
                              FROM reading_sessions rs
                              JOIN books b ON b.id = rs.book_id
                              WHERE rs.user_id = ?
                              GROUP BY rs.book_id, b.uuid, b.title, b.author, b.cover_path
                              ORDER BY seconds DESC
                              LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Genres ranked by minutes read. Helpful for "you mostly read X" insights.
     */
    public static function topGenres(int $userId, int $limit = 5): array
    {
        $stmt = db()->prepare("SELECT t.name, t.slug,
                                      COALESCE(SUM(rs.duration_seconds), 0) AS seconds
                              FROM reading_sessions rs
                              JOIN book_tags bt ON bt.book_id = rs.book_id
                              JOIN tags t ON t.id = bt.tag_id
                              WHERE rs.user_id = ? AND t.kind = 'genre'
                              GROUP BY t.id, t.name, t.slug
                              HAVING seconds > 0
                              ORDER BY seconds DESC
                              LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function recentSessions(int $userId, int $limit = 10): array
    {
        $stmt = db()->prepare("SELECT rs.*, b.uuid AS book_uuid, b.title, b.author, b.cover_path
                              FROM reading_sessions rs
                              JOIN books b ON b.id = rs.book_id
                              WHERE rs.user_id = ?
                              ORDER BY rs.started_at DESC
                              LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
