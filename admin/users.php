<?php
/**
 * Admin: list and manage users.
 */
define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

if (is_post()) {
    csrf_verify_or_abort();
    $verb = (string) ($_POST['verb'] ?? '');
    $id   = (int) ($_POST['id'] ?? 0);
    $target = $id > 0 ? UserRepository::findById($id) : null;
    if (!$target) {
        flash('error', 'User not found.');
        redirect('admin/users.php');
    }

    if ($verb === 'set_status') {
        $status = in_array($_POST['status'] ?? '', ['active','suspended','pending'], true) ? $_POST['status'] : 'active';
        UserRepository::setStatus($id, $status);
        AuditLogger::log('user.status_changed', 'user', $id, ['status' => $status]);
        flash('success', 'User status updated.');
    }
    if ($verb === 'set_role') {
        $role = in_array($_POST['role'] ?? '', ['reader','admin'], true) ? $_POST['role'] : 'reader';
        UserRepository::setRole($id, $role);
        AuditLogger::log('user.role_changed', 'user', $id, ['role' => $role]);
        flash('success', 'User role updated.');
    }
    if ($verb === 'send_reset_link') {
        // Issue a one-time password-reset token and email the user the same
        // link they'd get from the public "forgot password" flow. Avoids
        // disclosing a plaintext password back through the admin UI.
        $selector  = bin2hex(random_bytes(8));
        $verifier  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $verifier);
        AuthTokenRepository::create([
            'user_id'    => $id,
            'purpose'    => 'password_reset',
            'selector'   => $selector,
            'token_hash' => $tokenHash,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 60 * 60),
        ]);
        $link = (request_is_https() ? 'https://' : 'http://')
              . ($_SERVER['HTTP_HOST'] ?? 'localhost')
              . url('reset-password.php?token=' . eurl($selector . ':' . $verifier));
        $body = "Hi {$target['username']},\n\n"
              . "An administrator of " . config('app_name', 'ePublicLibrary')
              . " has issued a password-reset link for your account. "
              . "Click the link below within the next hour to choose a new password:\n\n"
              . $link . "\n\n"
              . "If you weren't expecting this, contact your administrator.\n\n"
              . "— " . config('app_name', 'ePublicLibrary');
        $sent = Mailer::send($target['email'], 'Password reset', $body);
        AuditLogger::log('user.password_reset', 'user', $id, ['driver' => config('mail.driver', 'log')]);
        if ($sent) {
            flash('success', "Reset link sent to {$target['email']}.");
        } else {
            flash('error', "Could not send email; check storage/logs/mail.log for the link.");
        }
    }
    redirect('admin/users.php');
}

$users = UserRepository::list(200);

render('admin/users', [
    'pageTitle' => 'Users',
    'activeNav' => 'users',
    'users'     => $users,
], 'admin');
