<?php
/**
 * Sliding-window rate limiting backed by the rate_limits table.
 *
 * Usage:
 *   if (!rate_limit_check('login:ip:' . client_ip(), 10, 900)) {
 *       abort(429, 'Too many attempts — try again later.');
 *   }
 *   rate_limit_hit('login:ip:' . client_ip(), 900);
 */

defined('APP_BOOTED') or exit;

/**
 * Return true if the bucket is still under the `max` hits in the window.
 * Does NOT increment; call rate_limit_hit() after handling the request.
 */
function rate_limit_check(string $bucket, int $max, int $window): bool
{
    try {
        $pdo = db();
    } catch (Throwable $e) {
        // Without DB we cannot rate-limit; fail-open is safer for setup.
        return true;
    }
    $stmt = $pdo->prepare("SELECT hits FROM " . table('rate_limits') . " WHERE bucket = ? AND expires_at > ?");
    $stmt->execute([$bucket, time()]);
    $row = $stmt->fetch();
    return !$row || (int) $row['hits'] < $max;
}

/**
 * Record a hit. Sets expires_at = now + window on first hit; resets when window
 * has elapsed. Returns the new hit count.
 */
function rate_limit_hit(string $bucket, int $window): int
{
    try {
        $pdo = db();
    } catch (Throwable $e) {
        return 0;
    }
    $now = time();
    $expires = $now + $window;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT hits, expires_at FROM " . table('rate_limits') . " WHERE bucket = ? FOR UPDATE");
        $stmt->execute([$bucket]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['expires_at'] < $now) {
            $ins = $pdo->prepare("REPLACE INTO " . table('rate_limits') . " (bucket, hits, expires_at) VALUES (?, 1, ?)");
            $ins->execute([$bucket, $expires]);
            $pdo->commit();
            return 1;
        }
        $hits = (int) $row['hits'] + 1;
        $upd = $pdo->prepare("UPDATE " . table('rate_limits') . " SET hits = ? WHERE bucket = ?");
        $upd->execute([$hits, $bucket]);
        $pdo->commit();
        return $hits;
    } catch (Throwable $e) {
        $pdo->rollBack();
        log_error($e);
        return 0;
    }
}

function rate_limit_reset(string $bucket): void
{
    try {
        $pdo = db();
        $pdo->prepare("DELETE FROM " . table('rate_limits') . " WHERE bucket = ?")->execute([$bucket]);
    } catch (Throwable $e) {
        log_error($e);
    }
}
