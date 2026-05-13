<?php
/**
 * Argon2id password hashing with NIST 800-63B-aligned validation.
 */

defined('APP_BOOTED') or exit;

class PasswordHasher
{
    private const ALGO = PASSWORD_ARGON2ID;
    private const OPTIONS = [
        'memory_cost' => 65536,
        'time_cost'   => 4,
        'threads'     => 2,
    ];

    /** Constant-time dummy verify against a fixed hash; used for unknown-user login. */
    public const DUMMY_HASH = DUMMY_ARGON2;

    public static function hash(string $password): string
    {
        return password_hash($password, self::ALGO, self::OPTIONS);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::ALGO, self::OPTIONS);
    }

    /**
     * Constant-time dummy verify when the user doesn't exist; prevents
     * username enumeration via timing.
     */
    public static function dummyVerify(string $password): void
    {
        @password_verify($password, self::DUMMY_HASH);
    }

    /**
     * Validate a password against NIST 800-63B rules + a small breach list.
     * Returns [] if valid, or an array of human-readable errors.
     */
    public static function validate(string $password, ?string $username = null, ?string $email = null): array
    {
        $errors = [];
        $len = strlen($password);
        if ($len < 12) {
            $errors[] = 'Password must be at least 12 characters.';
        }
        if ($len > 1024) {
            $errors[] = 'Password is too long.';
        }
        if ($username !== null && $username !== '' && stripos($password, $username) !== false) {
            $errors[] = 'Password may not contain your username.';
        }
        if ($email !== null && $email !== '') {
            $local = strstr($email, '@', true) ?: '';
            if ($local !== '' && stripos($password, $local) !== false) {
                $errors[] = 'Password may not contain part of your email address.';
            }
        }
        if (self::isCommon($password)) {
            $errors[] = 'This password is on a list of common/breached passwords. Choose another.';
        }
        return $errors;
    }

    /** Tiny check against a bundled top-of-mind common password list. */
    private static function isCommon(string $password): bool
    {
        static $set = null;
        if ($set === null) {
            $set = [];
            $file = __DIR__ . '/../data/common-passwords.txt';
            if (is_file($file)) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $set[strtolower(trim($line))] = true;
                }
            }
        }
        return isset($set[strtolower($password)]);
    }
}
