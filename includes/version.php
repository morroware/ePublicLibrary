<?php
/**
 * Single source of truth for the application version.
 *
 * Bump this on every deploy. The service worker reads it via the
 * <meta name="app-version"> tag and uses it as part of the cache key,
 * so a version bump transparently invalidates stale cached assets.
 *
 * Format: semver (MAJOR.MINOR.PATCH).
 */

defined('APP_BOOTED') or exit;

const APP_VERSION = '1.3.1';

function app_version(): string
{
    return APP_VERSION;
}
