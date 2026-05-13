<?php
/**
 * AuditLogger — append-only event log for security-sensitive actions.
 *
 *   AuditLogger::log('auth.login', 'user', $userId);
 *   AuditLogger::log('book.upload', 'book', $bookId, ['title' => $title]);
 */

defined('APP_BOOTED') or exit;

class AuditLogger
{
    public static function log(
        string $event,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = []
    ): void {
        try {
            $actor = $_SESSION['user_id'] ?? null;
            $actorType = 'guest';
            if ($actor !== null) {
                $role = $_SESSION['user_role'] ?? 'reader';
                $actorType = ($role === 'admin') ? 'admin' : 'user';
            }
            $ipBin = client_ip_binary();
            $ua    = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

            $stmt = db()->prepare(
                "INSERT INTO audit_log
                 (actor_id, actor_type, event, subject_type, subject_id, ip_address, user_agent, metadata, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bindValue(1, $actor, $actor === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(2, $actorType);
            $stmt->bindValue(3, $event);
            $stmt->bindValue(4, $subjectType, $subjectType === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(5, $subjectId, $subjectId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(6, $ipBin, $ipBin === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
            $stmt->bindValue(7, $ua);
            $stmt->bindValue(8, $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null);
            $stmt->bindValue(9, now_utc());
            $stmt->execute();
        } catch (Throwable $e) {
            // Never let audit failures break the calling flow
            log_error($e);
        }
    }

    public static function recent(int $limit = 100, int $offset = 0): array
    {
        $stmt = db()->prepare("SELECT a.*, u.username, u.email
                              FROM audit_log a
                              LEFT JOIN users u ON u.id = a.actor_id
                              ORDER BY a.id DESC
                              LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
