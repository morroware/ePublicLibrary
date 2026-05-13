<?php
define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

if (is_authed()) {
    redirect('index.php');
}

$errors = [];
$loginValue = '';

if (is_post()) {
    csrf_verify_or_abort();
    $loginValue = trim((string) ($_POST['login'] ?? ''));
    $password   = (string) ($_POST['password'] ?? '');
    $remember   = !empty($_POST['remember']);

    // Per-IP throttle
    $ipBucket = 'login:ip:' . (client_ip() ?: 'unknown');
    if (!rate_limit_check($ipBucket, (int) config('security.login_ip_max', 10), (int) config('security.login_ip_window', 900))) {
        $errors[] = 'Too many sign-in attempts from this network. Try again in a few minutes.';
    }

    if (!$errors && $loginValue === '') {
        $errors[] = 'Please enter your email or username.';
    }
    if (!$errors && $password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (!$errors) {
        $user = UserRepository::findByLogin($loginValue);
        if (!$user) {
            // Constant-time dummy verify to prevent username enumeration
            PasswordHasher::dummyVerify($password);
            rate_limit_hit($ipBucket, (int) config('security.login_ip_window', 900));
            $errors[] = 'Invalid credentials.';
            AuditLogger::log('auth.login_failed', null, null, ['login' => $loginValue]);
        } elseif ($user['status'] !== 'active') {
            $errors[] = 'This account is not active. Contact an administrator.';
            AuditLogger::log('auth.login_blocked', 'user', (int) $user['id'], ['status' => $user['status']]);
        } elseif (UserRepository::isLocked($user)) {
            $errors[] = 'This account is temporarily locked due to too many failed sign-ins. Try again later.';
            AuditLogger::log('auth.login_locked', 'user', (int) $user['id']);
        } elseif (!PasswordHasher::verify($password, $user['password_hash'])) {
            UserRepository::recordFailedLogin((int) $user['id']);
            rate_limit_hit($ipBucket, (int) config('security.login_ip_window', 900));
            $errors[] = 'Invalid credentials.';
            AuditLogger::log('auth.login_failed', 'user', (int) $user['id']);
        } else {
            // Success — silently upgrade hash if needed
            if (PasswordHasher::needsRehash($user['password_hash'])) {
                UserRepository::updatePassword((int) $user['id'], PasswordHasher::hash($password));
            }
            rate_limit_reset($ipBucket);
            login_user($user, $remember);

            $intended = $_SESSION['_intended'] ?? null;
            unset($_SESSION['_intended']);
            redirect($intended ?: 'index.php');
        }
    }
}

render('auth/login', [
    'pageTitle' => 'Sign in',
    'errors'    => $errors,
    'loginValue' => $loginValue,
], 'auth');
