<?php
defined('APP_BOOTED') or exit;
/** @var array $stats */
/** @var array $processed */
?>
<div class="admin-page-header">
    <h1>Thumbnails</h1>
    <p class="muted">Regenerate cover images extracted from the EPUB files.</p>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-value"><?= number_format($stats['total']) ?></div><div class="stat-label">Books</div></div>
    <div class="stat-card"><div class="stat-value"><?= number_format($stats['covers']) ?></div><div class="stat-label">Covers on disk</div></div>
    <div class="stat-card"><div class="stat-value"><?= number_format($stats['missing']) ?></div><div class="stat-label">Missing cover</div></div>
</div>

<form method="post" action="<?= e(url('admin/thumbnails.php')) ?>" class="form admin-form">
    <?= csrf_field() ?>
    <div class="form-actions">
        <button type="submit" name="verb" value="rebuild_missing" class="btn btn-primary">Build missing only</button>
        <button type="submit" name="verb" value="rebuild_all" class="btn btn-ghost"
                onclick="return confirm('Re-extract covers for ALL books? This can take a while.');">Rebuild all</button>
    </div>
</form>

<?php if ($processed): ?>
    <h2 class="results-heading">Last run</h2>
    <table class="admin-table">
        <thead><tr><th>Book</th><th>Result</th><th>Note</th></tr></thead>
        <tbody>
            <?php foreach ($processed as $p): ?>
                <tr>
                    <td><?= e($p['title']) ?></td>
                    <td><span class="badge badge-<?= $p['ok'] ? 'published' : 'removed' ?>"><?= $p['ok'] ? 'ok' : 'skip' ?></span></td>
                    <td><?= e($p['reason']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
