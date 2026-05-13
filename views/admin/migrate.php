<?php
defined('APP_BOOTED') or exit;
/** @var array $status */
/** @var array $messages */

$pending = array_filter($status, static fn($s) => !$s['applied']);
?>
<div class="admin-page-header">
    <h1>Migrations</h1>
    <p class="muted">Database schema changes are applied here. Migrations run
       in lexical order and are recorded with a SHA-256 checksum so we can
       detect drift if a file is edited post-deploy.</p>
</div>

<?php foreach ($messages as $m): ?>
    <div class="flash flash-<?= e($m['type']) ?>"><?= e($m['text']) ?></div>
<?php endforeach; ?>

<?php if (!$pending): ?>
    <div class="flash flash-success">All migrations are applied. Nothing to do.</div>
<?php else: ?>
    <form method="post" action="<?= e(url('admin/migrate.php')) ?>" class="migrate-form">
        <?= csrf_field() ?>
        <p><strong><?= count($pending) ?></strong> migration<?= count($pending) !== 1 ? 's' : '' ?> pending.</p>
        <button type="submit" class="btn btn-primary">Apply pending migrations</button>
    </form>
<?php endif; ?>

<table class="admin-table">
    <thead>
        <tr>
            <th>File</th>
            <th>Status</th>
            <th>Applied at</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($status as $s): ?>
            <tr>
                <td><code><?= e($s['name']) ?></code></td>
                <td>
                    <?php if ($s['applied']): ?>
                        <span class="badge badge-published">Applied</span>
                        <?php if ($s['drift']): ?>
                            <span class="badge badge-removed" title="File changed after being applied">drift</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge badge-hidden">Pending</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($s['executed_at']): ?>
                        <time datetime="<?= e($s['executed_at']) ?>"><?= e(date('M j, Y g:i a', strtotime($s['executed_at']))) ?></time>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
