<?php
/**
 * BookFileStorage — safe absolute-path resolution for files inside the
 * configured books root.
 *
 * Replaces the strpos('..') guard in the legacy code with a realpath-based
 * check that resists Unicode normalization and symlink tricks.
 */

defined('APP_BOOTED') or exit;

class BookFileStorage
{
    /**
     * Absolute path to the books root, with trailing slash stripped.
     * Uses config('storage.books_path').
     */
    public static function root(): string
    {
        $root = (string) config('storage.books_path', '');
        $real = realpath($root);
        if ($real === false) {
            // Try to create it once
            @mkdir($root, 0750, true);
            $real = realpath($root);
        }
        if ($real === false) {
            throw new RuntimeException('Books storage root is not accessible: ' . $root);
        }
        return rtrim($real, '/\\');
    }

    /** Path for the shard directory of a UUID, created if missing. */
    public static function shardPath(string $uuid): string
    {
        $shard = shard_for($uuid);
        $path = self::root() . '/' . $shard;
        if (!is_dir($path)) {
            @mkdir($path, 0750, true);
        }
        return $path;
    }

    /** Default storage path for a book given its UUID. */
    public static function pathForUuid(string $uuid): string
    {
        return self::shardPath($uuid) . '/' . $uuid . '.epub';
    }

    /**
     * Resolve a book's on-disk path. Validates that the resolved absolute
     * path is inside the books root. Returns null if the file is missing
     * or the path escapes the root.
     */
    public static function resolveForBook(array $book): ?string
    {
        $stored = (string) ($book['storage_path'] ?? '');
        if ($stored === '') {
            return null;
        }
        // Treat as either absolute (already real) or relative to root.
        if ($stored[0] === '/' || preg_match('~^[A-Za-z]:[\\\\/]~', $stored)) {
            $candidate = $stored;
        } else {
            $candidate = self::root() . '/' . ltrim($stored, '/');
        }
        $real = realpath($candidate);
        if ($real === false) {
            return null;
        }
        $rootReal = self::root();
        if (strpos($real, $rootReal) !== 0) {
            return null; // escapes root
        }
        return $real;
    }

    /**
     * Move a temp/quarantined file into the books root under the canonical
     * {shard}/{uuid}.epub name. Returns the relative storage path written
     * to books.storage_path.
     */
    public static function moveIntoStore(string $tempPath, string $uuid): string
    {
        $dest = self::pathForUuid($uuid);
        if (!@rename($tempPath, $dest)) {
            // Fall back to copy + unlink (cross-device rename can fail)
            if (!@copy($tempPath, $dest)) {
                throw new RuntimeException('Failed to move uploaded file into storage.');
            }
            @unlink($tempPath);
        }
        @chmod($dest, 0640);
        return shard_for($uuid) . '/' . $uuid . '.epub';
    }

    /** Delete the EPUB file for a book. Idempotent. */
    public static function deleteForBook(array $book): void
    {
        $path = self::resolveForBook($book);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }
}
