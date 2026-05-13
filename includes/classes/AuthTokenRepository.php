<?php
/**
 * AuthTokenRepository — remember-me, password reset, email verify, invite tokens.
 *
 * Split-token: cookie = "selector:verifier"; DB stores selector + sha256(verifier).
 */

defined('APP_BOOTED') or exit;

class AuthTokenRepository
{
    public static function create(array $data): int
    {
        $sql = "INSERT INTO auth_tokens (user_id, purpose, selector, token_hash, expires_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            (int) $data['user_id'],
            $data['purpose'],
            $data['selector'],
            $data['token_hash'],
            $data['expires_at'],
            now_utc(),
        ]);
        return (int) db()->lastInsertId();
    }

    public static function findBySelector(string $selector, string $purpose): ?array
    {
        $stmt = db()->prepare("SELECT * FROM auth_tokens
                              WHERE selector = ? AND purpose = ?
                                AND revoked_at IS NULL
                                AND used_at IS NULL
                                AND expires_at > CURRENT_TIMESTAMP
                              LIMIT 1");
        $stmt->execute([$selector, $purpose]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function deleteBySelector(string $selector): void
    {
        $stmt = db()->prepare("DELETE FROM auth_tokens WHERE selector = ?");
        $stmt->execute([$selector]);
    }

    public static function revokeBySelector(string $selector): void
    {
        $stmt = db()->prepare("UPDATE auth_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE selector = ?");
        $stmt->execute([$selector]);
    }

    public static function revokeAllForUser(int $userId, ?string $purpose = null): void
    {
        if ($purpose === null) {
            $stmt = db()->prepare("UPDATE auth_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE user_id = ? AND revoked_at IS NULL");
            $stmt->execute([$userId]);
        } else {
            $stmt = db()->prepare("UPDATE auth_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE user_id = ? AND purpose = ? AND revoked_at IS NULL");
            $stmt->execute([$userId, $purpose]);
        }
    }

    public static function markUsed(int $id): void
    {
        $stmt = db()->prepare("UPDATE auth_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);
    }

    public static function purgeExpired(): int
    {
        $stmt = db()->query("DELETE FROM auth_tokens WHERE expires_at < CURRENT_TIMESTAMP");
        return $stmt->rowCount();
    }
}
