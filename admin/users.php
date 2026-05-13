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
    if ($verb === 'reset_password') {
        $newPass = bin2hex(random_bytes(8));
        UserRepository::updatePassword($id, PasswordHasher::hash($newPass));
        AuthTokenRepository::revokeAllForUser($id);
        AuditLogger::log('user.password_reset', 'user', $id);
        flash('success', "Temporary password for {$target['username']}: {$newPass}");
    }
    redirect('admin/users.php');
}

$users = UserRepository::list(200);

render('admin/users', [
    'pageTitle' => 'Users',
    'activeNav' => 'users',
    'users'     => $users,
], 'admin');
