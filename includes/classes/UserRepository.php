<?php
/**
 * UserRepository — every users-table SQL touchpoint lives here.
 */

defined('APP_BOOTED') or exit;

class UserRepository
{
    /* --------------- queries --------------- */

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([trim($username)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByLogin(string $login): ?array
    {
        // Try email then username
        $u = self::findByEmail($login);
        return $u ?: self::findByUsername($login);
    }

    public static function exists(string $email, string $username): bool
    {
        $stmt = db()->prepare("SELECT 1 FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->execute([strtolower(trim($email)), trim($username)]);
        return (bool) $stmt->fetchColumn();
    }

    public static function count(): int
    {
        return (int) db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    public static function list(int $limit = 50, int $offset = 0): array
    {
        $stmt = db()->prepare("SELECT id, uuid, email, username, display_name, role, status, last_login_at, created_at
                              FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /* --------------- mutations --------------- */

    /**
     * Insert a new user. Returns the new ID.
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO users
                (uuid, email, username, display_name, password_hash, role, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $now = now_utc();
        $stmt = db()->prepare($sql);
        $stmt->execute([
            $data['uuid'] ?? uuid_v4(),
            strtolower(trim($data['email'])),
            trim($data['username']),
            $data['display_name'] ?? null,
            $data['password_hash'],
            $data['role']   ?? 'reader',
            $data['status'] ?? 'active',
            $now, $now,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function updatePassword(int $userId, string $newHash): void
    {
        $stmt = db()->prepare("UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$newHash, now_utc(), $userId]);
    }

    public static function recordLogin(int $userId, ?string $ipBinary): void
    {
        $stmt = db()->prepare("UPDATE users
                              SET last_login_at = CURRENT_TIMESTAMP,
                                  last_login_ip = ?,
                                  failed_login_count = 0,
                                  locked_until = NULL
                              WHERE id = ?");
        $stmt->bindValue(1, $ipBinary, $ipBinary === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public static function recordFailedLogin(int $userId): int
    {
        $pdo = db();
        $pdo->prepare("UPDATE users SET failed_login_count = failed_login_count + 1 WHERE id = ?")
            ->execute([$userId]);
        $count = (int) $pdo->query("SELECT failed_login_count FROM users WHERE id = " . $userId)->fetchColumn();
        $max = (int) config('security.login_user_max', 5);
        if ($count >= $max) {
            $lockWindow = (int) config('security.login_user_window', 900);
            $pdo->prepare("UPDATE users SET locked_until = FROM_UNIXTIME(?) WHERE id = ?")
                ->execute([time() + $lockWindow, $userId]);
        }
        return $count;
    }

    public static function isLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }
        return strtotime($user['locked_until']) > time();
    }

    public static function setStatus(int $userId, string $status): void
    {
        $stmt = db()->prepare("UPDATE users SET status = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$status, now_utc(), $userId]);
    }

    public static function setRole(int $userId, string $role): void
    {
        $stmt = db()->prepare("UPDATE users SET role = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$role, now_utc(), $userId]);
    }
}
