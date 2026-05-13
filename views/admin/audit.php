<?php
defined('APP_BOOTED') or exit;
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
?>
<div class="admin-page-header">
    <h1>Audit log</h1>
    <p class="muted"><?= number_format($total) ?> events · showing newest first</p>
</div>

<table class="admin-table audit-table">
    <thead>
        <tr>
            <th>When</th>
            <th>Actor</th>
            <th>Event</th>
            <th>Subject</th>
            <th>IP</th>
            <th>Details</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><time datetime="<?= e($r['created_at']) ?>"><?= e(date('M j, Y g:i:s a', strtotime($r['created_at']))) ?></time></td>
                <td>
                    <?php if ($r['username']): ?>
                        <strong><?= e($r['username']) ?></strong>
                    <?php else: ?>
                        <span class="muted"><?= e($r['actor_type']) ?></span>
                    <?php endif; ?>
                </td>
                <td><code><?= e($r['event']) ?></code></td>
                <td>
                    <?php if ($r['subject_type']): ?>
                        <?= e($r['subject_type']) ?>#<?= e((string) $r['subject_id']) ?>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td><code><?= e($r['ip_address'] ? inet_ntop($r['ip_address']) : '—') ?></code></td>
                <td>
                    <?php if ($r['metadata']): ?>
                        <details><summary>view</summary><pre><?= e(json_encode(json_decode($r['metadata'], true), JSON_PRETTY_PRINT)) ?></pre></details>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Audit pagination">
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <?php if ($i === $page): ?>
                <span aria-current="page"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= e(url('admin/audit.php?page=' . $i)) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
