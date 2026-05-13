<?php
define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

require_auth();
$user = current_user();
$errors = [];
$success = null;

if (is_post()) {
    csrf_verify_or_abort();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'profile') {
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $stmt = db()->prepare("UPDATE users SET display_name = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$displayName ?: null, now_utc(), (int) $user['id']]);
        AuditLogger::log('account.profile_updated', 'user', (int) $user['id']);
        flash('success', 'Profile updated.');
        redirect('account.php');
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirmation'] ?? '');

        if (!PasswordHasher::verify($current, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New password confirmation does not match.';
        } else {
            $errors = PasswordHasher::validate($new, $user['username'], $user['email']);
            if (!$errors) {
                UserRepository::updatePassword((int) $user['id'], PasswordHasher::hash($new));
                AuditLogger::log('account.password_changed', 'user', (int) $user['id']);
                flash('success', 'Password updated.');
                redirect('account.php');
            }
        }
    }
}

render('auth/account', [
    'pageTitle' => 'Your account',
    'pageClass' => 'account-page',
    'user'      => $user,
    'errors'    => $errors,
], 'app');
