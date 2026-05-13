<?php
/**
 * CSRF protection.
 *
 * Token lives in the session and rotates on privilege change (login/logout).
 * Forms include csrf_field(); XHR sends X-CSRF-Token header from
 * <meta name="csrf-token">.
 */

defined('APP_BOOTED') or exit;

const CSRF_SESSION_KEY = '_csrf_token';

function csrf_token(): string
{
    if (!isset($_SESSION)) {
        return '';
    }
    if (empty($_SESSION[CSRF_SESSION_KEY])) {
        $_SESSION[CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_SESSION_KEY];
}

function csrf_rotate(): void
{
    if (isset($_SESSION)) {
        $_SESSION[CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    }
}

/** HTML <input> for forms. Pair with `<form method="post">`. */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * Endpoints that may legitimately receive their CSRF token in the query
 * string. Browsers don't let navigator.sendBeacon set custom headers, and
 * a beacon with a JSON body bypasses $_POST, so heartbeat-style endpoints
 * fall back to `?_token=...`.
 *
 * Keep this list as small as possible — tokens carried in URLs can leak via
 * Referer headers, browser history, and access logs.
 */
const CSRF_QUERY_STRING_ALLOW = [
    'api/sessions.php',
];

/** Return the token from the current request (POST form, X-CSRF-Token, or
 *  the `_token` query string).
 *
 *  Query-string fallback is restricted to beacon endpoints listed in
 *  CSRF_QUERY_STRING_ALLOW. Only relevant for POST/PATCH/PUT/DELETE — GET
 *  skips CSRF altogether in csrf_verify_or_abort.
 */
function csrf_request_token(): ?string
{
    if (!empty($_POST['_token'])) {
        return (string) $_POST['_token'];
    }
    $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if ($hdr !== null && $hdr !== '') {
        return (string) $hdr;
    }
    if (!empty($_GET['_token']) && csrf_query_string_allowed()) {
        return (string) $_GET['_token'];
    }
    return null;
}

/** True if the current script is on the beacon-endpoint allow-list. */
function csrf_query_string_allowed(): bool
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    foreach (CSRF_QUERY_STRING_ALLOW as $suffix) {
        if (str_ends_with($script, '/' . $suffix)) {
            return true;
        }
    }
    return false;
}

/** Verify or abort with 419. Call from any POST/PUT/PATCH/DELETE handler. */
function csrf_verify_or_abort(): void
{
    $method = request_method();
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    $given = csrf_request_token();
    $known = $_SESSION[CSRF_SESSION_KEY] ?? '';
    if ($given === null || $known === '' || !hash_equals($known, $given)) {
        abort(419, 'CSRF token mismatch — please reload and try again.');
    }
}
