<?php
/**
 * Authentication helpers — current user resolution, login/logout, role gates.
 *
 * Uses session for primary auth and remember-me cookies for persistence.
 * The remember-me cookie uses split-token: cookie value is "selector:verifier",
 * DB stores selector + sha256(verifier).
 */

defined('APP_BOOTED') or exit;

const REMEMBER_COOKIE = 'elib_remember';
const REMEMBER_LIFETIME = 60 * 60 * 24 * 60;  // 60 days
/** Constant-time dummy hash for nonexistent-user login attempts. */
const DUMMY_ARGON2 = '$argon2id$v=19$m=65536,t=4,p=2$YWFhYWFhYWFhYWFhYWFhYQ$KKqfTKgfNbBgWlOFKMtkZmZQUJ4Z9oN03Q3rH8nzhi8';

/* ----------- Current-user resolution -------------------------------------- */

function current_user(): ?array
{
    static $cached = false;
    static $user = null;
    if ($cached) {
        return $user;
    }
    $cached = true;

    $uid = $_SESSION['user_id'] ?? null;
    if ($uid !== null) {
        try {
            $user = UserRepository::findById((int) $uid);
            if ($user && ($user['status'] !== 'active')) {
                logout_user();
                $user = null;
            }
        } catch (Throwable $e) {
            log_error($e);
            $user = null;
        }
        return $user;
    }

    // Try remember-me cookie
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $user = remember_login_attempt($_COOKIE[REMEMBER_COOKIE]);
    }
    return $user;
}

function is_guest(): bool { return current_user() === null; }
function is_authed(): bool { return current_user() !== null; }

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && ($u['role'] ?? '') === 'admin';
}

function require_auth(): void
{
    if (!is_authed()) {
        flash('error', 'Please sign in to continue.');
        // Preserve the intended destination so we can redirect back after login.
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? '/';
        redirect('login.php');
    }
}

function require_role(string $role): void
{
    require_auth();
    $u = current_user();
    if (($u['role'] ?? '') !== $role) {
        abort(403, 'You do not have permission to view this page.');
    }
}

/* ----------- Login / logout ----------------------------------------------- */

/**
 * Set the user as logged in. Regenerates session ID, rotates CSRF, sets remember
 * cookie if requested.
 */
function login_user(array $user, bool $remember = false): void
{
    session_regenerate_id(true);
    csrf_rotate();

    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_uuid']  = $user['uuid'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['login_time'] = time();

    UserRepository::recordLogin((int) $user['id'], client_ip_binary());

    if ($remember) {
        remember_issue((int) $user['id']);
    } else {
        remember_clear();
    }

    AuditLogger::log('auth.login', 'user', (int) $user['id']);
}

function logout_user(): void
{
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid !== null) {
        AuditLogger::log('auth.logout', 'user', (int) $uid);
    }
    remember_clear();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/* ----------- Remember-me (split-token) ------------------------------------ */

function remember_issue(int $userId): void
{
    $selector  = bin2hex(random_bytes(8));   // 16 hex chars
    $verifier  = bin2hex(random_bytes(32));  // 64 hex chars
    $tokenHash = hash('sha256', $verifier);
    $expires   = now_utc_plus(REMEMBER_LIFETIME);

    AuthTokenRepository::create([
        'user_id'    => $userId,
        'purpose'    => 'remember',
        'selector'   => $selector,
        'token_hash' => $tokenHash,
        'expires_at' => $expires,
    ]);

    $cookieValue = $selector . ':' . $verifier;
    $base = app_base();
    setcookie(REMEMBER_COOKIE, $cookieValue, [
        'expires'  => time() + REMEMBER_LIFETIME,
        'path'     => $base !== '' ? $base . '/' : '/',
        'secure'   => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function remember_clear(): void
{
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $value = (string) $_COOKIE[REMEMBER_COOKIE];
        [$selector] = array_pad(explode(':', $value, 2), 2, '');
        if ($selector !== '') {
            AuthTokenRepository::revokeBySelector($selector);
        }
    }
    $base = app_base();
    setcookie(REMEMBER_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => $base !== '' ? $base . '/' : '/',
        'secure'   => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function remember_login_attempt(string $cookie): ?array
{
    [$selector, $verifier] = array_pad(explode(':', $cookie, 2), 2, '');
    if ($selector === '' || $verifier === '') {
        remember_clear();
        return null;
    }
    $row = AuthTokenRepository::findBySelector($selector, 'remember');
    if (!$row) {
        remember_clear();
        return null;
    }
    $expected = $row['token_hash'];
    $actual   = hash('sha256', $verifier);
    if (!hash_equals($expected, $actual)) {
        // Possible token theft — revoke all remember tokens for this user
        AuthTokenRepository::revokeAllForUser((int) $row['user_id'], 'remember');
        AuditLogger::log('auth.remember.mismatch', 'user', (int) $row['user_id']);
        remember_clear();
        return null;
    }
    $user = UserRepository::findById((int) $row['user_id']);
    if (!$user || $user['status'] !== 'active') {
        remember_clear();
        return null;
    }
    // Rotate: delete old, issue new
    AuthTokenRepository::deleteBySelector($selector);
    login_user($user, true);
    return $user;
}

function now_utc_plus(int $seconds): string
{
    return gmdate('Y-m-d H:i:s', time() + $seconds);
}
