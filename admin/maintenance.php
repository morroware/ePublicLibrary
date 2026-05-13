<?php
/**
 * Admin: scheduled-maintenance triggers.
 *
 * Both auth_tokens and rate_limits accumulate rows that age out logically
 * (expires_at < now) but stay on disk. This page exposes the two purge
 * functions that already exist in the codebase so a human can run them
 * on demand. Recommended to also wire a weekly cron — see INSTALL.md.
 */
define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

if (is_post()) {
    csrf_verify_or_abort();
    $verb = (string) ($_POST['verb'] ?? '');

    if ($verb === 'purge_auth_tokens') {
        $removed = AuthTokenRepository::purgeExpired();
        AuditLogger::log('maintenance.purge_auth_tokens', null, null, ['removed' => $removed]);
        flash('success', "Purged {$removed} expired auth token" . ($removed === 1 ? '' : 's') . '.');
        redirect('admin/maintenance.php');
    }

    if ($verb === 'purge_rate_limits') {
        $removed = rate_limit_purge_expired();
        AuditLogger::log('maintenance.purge_rate_limits', null, null, ['removed' => $removed]);
        flash('success', "Purged {$removed} expired rate-limit bucket" . ($removed === 1 ? '' : 's') . '.');
        redirect('admin/maintenance.php');
    }
}

$pdo = db();
$authTotal     = (int) $pdo->query("SELECT COUNT(*) FROM auth_tokens")->fetchColumn();
$authExpired   = (int) $pdo->query("SELECT COUNT(*) FROM auth_tokens WHERE expires_at < CURRENT_TIMESTAMP")->fetchColumn();
$rateTotal     = (int) $pdo->query("SELECT COUNT(*) FROM " . table('rate_limits'))->fetchColumn();
$rateExpired   = (int) $pdo->query("SELECT COUNT(*) FROM " . table('rate_limits') . " WHERE expires_at < " . (int) time())->fetchColumn();

render('admin/maintenance', [
    'pageTitle'   => 'Maintenance',
    'activeNav'   => 'maintenance',
    'authTotal'   => $authTotal,
    'authExpired' => $authExpired,
    'rateTotal'   => $rateTotal,
    'rateExpired' => $rateExpired,
], 'admin');
