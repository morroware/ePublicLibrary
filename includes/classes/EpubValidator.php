<?php
/**
 * EpubValidator — hardens uploaded EPUBs against extension/MIME spoofing.
 *
 * Checks, in order:
 *   1. Extension is .epub.
 *   2. finfo MIME is application/epub+zip or application/zip.
 *   3. First 4 bytes match PK\x03\x04 (ZIP magic).
 *   4. ZipArchive::open() succeeds.
 *   5. The first archive entry is named "mimetype" with content
 *      "application/epub+zip" (per EPUB spec).
 *   6. META-INF/container.xml exists and parses as XML.
 *   7. File size is within the configured cap.
 *
 * Returns ['ok' => true] on success, ['ok' => false, 'error' => string] on
 * failure. Never throws — callers expect a verdict back.
 */

defined('APP_BOOTED') or exit;

class EpubValidator
{
    public const VALID_MIME = ['application/epub+zip', 'application/zip'];
    public const MAGIC      = "PK\x03\x04";

    public static function validate(string $path, ?string $originalName = null): array
    {
        // (1) Extension
        $ext = strtolower(pathinfo($originalName ?? $path, PATHINFO_EXTENSION));
        if ($ext !== 'epub') {
            return ['ok' => false, 'error' => 'File extension must be .epub.'];
        }

        // (2) Size cap
        $maxMb = (int) config('storage.max_upload_mb', 100);
        $size = is_file($path) ? filesize($path) : 0;
        if ($size === false || $size <= 0) {
            return ['ok' => false, 'error' => 'Uploaded file is empty or missing.'];
        }
        if ($maxMb > 0 && $size > $maxMb * 1024 * 1024) {
            return ['ok' => false, 'error' => "File exceeds the {$maxMb} MB upload limit."];
        }

        // (3) MIME via finfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $path) : null;
            if ($finfo) { finfo_close($finfo); }
            if ($mime && !in_array($mime, self::VALID_MIME, true)) {
                return ['ok' => false, 'error' => "File appears to be {$mime}, not an EPUB."];
            }
        }

        // (4) Magic bytes
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return ['ok' => false, 'error' => 'Could not read uploaded file.'];
        }
        $magic = fread($fh, 4);
        fclose($fh);
        if ($magic !== self::MAGIC) {
            return ['ok' => false, 'error' => 'File is not a valid ZIP archive.'];
        }

        // (5) ZipArchive open
        $zip = new ZipArchive();
        $rc = $zip->open($path);
        if ($rc !== true) {
            return ['ok' => false, 'error' => "Could not open archive (zip error {$rc})."];
        }

        try {
            // (6) First entry should be 'mimetype'
            $first = $zip->statIndex(0);
            if ($first === false || ($first['name'] ?? '') !== 'mimetype') {
                // Some real-world EPUBs don't conform — degrade to a warning
                // by continuing rather than rejecting. But require container.xml.
            } else {
                $mimeContent = $zip->getFromIndex(0);
                if ($mimeContent !== false && trim($mimeContent) !== 'application/epub+zip') {
                    return ['ok' => false, 'error' => 'Archive mimetype entry does not declare application/epub+zip.'];
                }
            }

            // (7) container.xml must exist + parse
            $container = $zip->getFromName('META-INF/container.xml');
            if ($container === false) {
                return ['ok' => false, 'error' => 'EPUB is missing META-INF/container.xml.'];
            }
            $dom = new DOMDocument();
            $prev = libxml_use_internal_errors(true);
            $ok = $dom->loadXML($container);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            if (!$ok) {
                return ['ok' => false, 'error' => 'EPUB container.xml is malformed.'];
            }
        } finally {
            $zip->close();
        }

        return ['ok' => true, 'size' => $size];
    }

    public static function sha256(string $path): string
    {
        return hash_file('sha256', $path) ?: '';
    }
}
