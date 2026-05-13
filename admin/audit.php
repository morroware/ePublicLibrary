<?php
/**
 * Admin: view audit log.
 */
define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 100;
$offset = ($page - 1) * $perPage;

$rows = AuditLogger::recent($perPage, $offset);
$total = (int) db()->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();

render('admin/audit', [
    'pageTitle' => 'Audit log',
    'activeNav' => 'audit',
    'rows'      => $rows,
    'total'     => $total,
    'page'      => $page,
    'pages'     => max(1, (int) ceil($total / $perPage)),
], 'admin');
