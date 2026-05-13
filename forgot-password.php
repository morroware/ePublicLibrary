<?php
/**
 * "I forgot my password" — request a reset link.
 *
 * Always shows "If that address is registered, we sent a link" regardless
 * of whether the email exists, so it can't be used to probe for accounts.
 * Rate-limited per IP to deter abuse.
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

if (is_authed()) {
    redirect('account.php');
}

$errors = [];
$emailValue = '';
$submitted = false;

if (is_post()) {
    csrf_verify_or_abort();
    $emailValue = trim((string) ($_POST['email'] ?? ''));

    // Per-IP rate limit
    $bucket = 'pwreset:ip:' . (client_ip() ?: 'unknown');
    if (!rate_limit_check($bucket, 5, 60 * 60)) {
        $errors[] = 'Too many password-reset requests from this network. Try again later.';
    }

    if (!$errors) {
        if ($emailValue === '' || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
    }

    if (!$errors) {
        rate_limit_hit($bucket, 60 * 60);
        $user = UserRepository::findByEmail($emailValue);
        if ($user && $user['status'] === 'active') {
            // Issue a one-time split-token; cookie value never stored.
            $selector  = bin2hex(random_bytes(8));
            $verifier  = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $verifier);
            AuthTokenRepository::create([
                'user_id'    => (int) $user['id'],
                'purpose'    => 'password_reset',
                'selector'   => $selector,
                'token_hash' => $tokenHash,
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 60 * 60),  // 1 hour
            ]);
            $link = (request_is_https() ? 'https://' : 'http://')
                  . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                  . url('reset-password.php?token=' . eurl($selector . ':' . $verifier));
            $body = "Hi {$user['username']},\n\n"
                  . "Someone asked to reset the password for your "
                  . config('app_name', 'ePublicLibrary') . " account. "
                  . "If that was you, click the link below within the next hour:\n\n"
                  . $link . "\n\n"
                  . "If it wasn't you, you can safely ignore this email — your password is unchanged.\n\n"
                  . "— " . config('app_name', 'ePublicLibrary');
            Mailer::send($user['email'], 'Reset your password', $body);
            AuditLogger::log('auth.password_reset_requested', 'user', (int) $user['id']);
        }
        $submitted = true;
    }
}

render('auth/forgot-password', [
    'pageTitle' => 'Forgot password',
    'errors'    => $errors,
    'email'     => $emailValue,
    'submitted' => $submitted,
], 'auth');
