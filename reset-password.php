<?php
/**
 * Land here from the email link to set a new password.
 *   /reset-password.php?token=selector:verifier
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
[$selector, $verifier] = array_pad(explode(':', $token, 2), 2, '');

$errors = [];
$success = false;
$user = null;

if ($selector === '' || $verifier === '') {
    $errors[] = 'This reset link is missing data. Request a new one.';
} else {
    $row = AuthTokenRepository::findBySelector($selector, 'password_reset');
    if (!$row || !hash_equals($row['token_hash'], hash('sha256', $verifier))) {
        $errors[] = 'This reset link is invalid or expired. Request a new one.';
    } else {
        $user = UserRepository::findById((int) $row['user_id']);
        if (!$user) {
            $errors[] = 'This account is no longer available.';
        }
    }
}

if (!$errors && is_post()) {
    csrf_verify_or_abort();
    $new = (string) ($_POST['password'] ?? '');
    $cnf = (string) ($_POST['password_confirmation'] ?? '');
    if ($new !== $cnf) {
        $errors[] = 'Password confirmation does not match.';
    }
    $errors = array_merge($errors, PasswordHasher::validate($new, $user['username'], $user['email']));

    if (!$errors) {
        // Single-use atomic claim. If a concurrent request already consumed
        // the token (race) or it was revoked / expired in the meantime,
        // bail out before mutating the password.
        if (!AuthTokenRepository::claimSingleUse((int) $row['id'])) {
            $errors[] = 'This reset link is no longer valid. Request a new one.';
        } else {
            UserRepository::updatePassword((int) $user['id'], PasswordHasher::hash($new));
            AuthTokenRepository::revokeAllForUser((int) $user['id'], 'remember');
            AuditLogger::log('auth.password_reset_completed', 'user', (int) $user['id']);
            flash('success', 'Password updated. Sign in with your new password.');
            redirect('login.php');
        }
    }
}

render('auth/reset-password', [
    'pageTitle' => 'Reset password',
    'errors'    => $errors,
    'token'     => $token,
    'haveUser'  => $user !== null,
], 'auth');
