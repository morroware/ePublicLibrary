<?php
/**
 * ePublicLibrary bootstrap.
 *
 * The "one require" every page starts with:
 *
 *     <?php
 *     define('APP_BOOTED', true);
 *     require __DIR__ . '/includes/bootstrap.php';
 *
 * Order of operations matters here — helpers before config before DB before
 * session before auth.
 */

if (!defined('APP_BOOTED')) {
    define('APP_BOOTED', true);
}

/* ----------- Helpers, autoload, error handling first --------------------- */

require __DIR__ . '/helpers.php';
require __DIR__ . '/autoload.php';
require __DIR__ . '/security.php';
require __DIR__ . '/db.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/session.php';
require __DIR__ . '/ratelimit.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/view.php';

/* ----------- Config + error reporting ------------------------------------ */

if (!config_exists()) {
    // Pre-setup: route everything to setup.php unless we're already there.
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script !== 'setup.php') {
        // Build URL with the auto-detected base.
        $target = (rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') ?: '') . '/setup.php';
        // Strip likely subdir suffixes from path for clean redirect
        foreach (['/admin', '/api', '/views', '/includes', '/database'] as $sub) {
            if (substr($target, -strlen($sub) - 11) === $sub . '/setup.php') {
                $target = substr($target, 0, -strlen($sub) - 11) . '/setup.php';
            }
        }
        header('Location: ' . $target);
        exit;
    }
    // setup.php handles its own minimal bootstrap below.
    date_default_timezone_set('UTC');
    return;
}

date_default_timezone_set((string) config('timezone', 'UTC'));
mb_internal_encoding('UTF-8');

$debug = (bool) config('debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
register_error_handler();

/* ----------- Session + security headers ---------------------------------- */

init_session();
send_security_headers();

/* ----------- That's it. Page files take over from here. ------------------ */
