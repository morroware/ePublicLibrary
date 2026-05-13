<?php
/**
 * Admin dashboard — quick stats and shortcuts.
 */
define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$stats = [
    'books'   => BookRepository::totalCount(),
    'users'   => UserRepository::count(),
    'storage_bytes' => (int) db()->query("SELECT COALESCE(SUM(file_size), 0) FROM books")->fetchColumn(),
    'recent_uploads' => db()->query("SELECT id, uuid, title, author, created_at
                                     FROM books ORDER BY created_at DESC LIMIT 5")->fetchAll(),
];
$recentAudit = AuditLogger::recent(10);

render('admin/dashboard', [
    'pageTitle' => 'Dashboard',
    'activeNav' => 'dashboard',
    'stats'     => $stats,
    'audit'     => $recentAudit,
], 'admin');
