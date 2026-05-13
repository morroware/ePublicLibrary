<?php
defined('APP_BOOTED') or exit;
/** @var string $legacyDir */
/** @var bool $legacyHas */
/** @var array $results */
$ok = count(array_filter($results, fn($r) => $r['ok']));
$bad = count($results) - $ok;
?>
<div class="admin-page-header">
    <h1>Import legacy books</h1>
    <p class="muted">One-shot importer for the original <code>books/</code> directory.
       Each EPUB is validated, metadata extracted, and inserted into the
       database. Files are copied (not moved) so your original tree stays
       intact until you delete it manually.</p>
</div>

<?php if (!$legacyHas): ?>
    <div class="flash flash-info">
        No <code>books/</code> directory found at <code><?= e($legacyDir) ?></code>.
        Nothing to import.
    </div>
<?php else: ?>
    <form method="post" action="<?= e(url('admin/scan-books.php')) ?>" class="form admin-form">
        <?= csrf_field() ?>
        <p>Scan <code><?= e($legacyDir) ?></code> for EPUBs and import them.
           This is safe to re-run — already-imported files (matched by hash) are skipped.</p>
        <button type="submit" class="btn btn-primary">Start import</button>
    </form>
<?php endif; ?>

<?php if ($results): ?>
    <h2 class="results-heading">Results <small class="muted"><?= number_format($ok) ?> imported · <?= number_format($bad) ?> skipped</small></h2>
    <table class="admin-table">
        <thead><tr><th>File</th><th>Result</th><th>Note</th></tr></thead>
        <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><code><?= e(basename($r['path'])) ?></code></td>
                    <td><span class="badge badge-<?= $r['ok'] ? 'published' : 'removed' ?>"><?= $r['ok'] ? 'ok' : 'skip' ?></span></td>
                    <td><?= e($r['reason']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
