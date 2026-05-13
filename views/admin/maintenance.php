<?php
defined('APP_BOOTED') or exit;
/** @var int $authTotal */
/** @var int $authExpired */
/** @var int $rateTotal */
/** @var int $rateExpired */
?>
<div class="admin-page-header">
    <h1>Maintenance</h1>
    <p class="muted">Cleanup tasks that keep the auxiliary tables from growing unbounded.
       For long-running installs, schedule these to run weekly from cron — see <code>INSTALL.md</code>.</p>
</div>

<section class="admin-card health-card">
    <h2>Auth tokens <small class="muted"><?= number_format($authTotal) ?> total · <?= number_format($authExpired) ?> expired</small></h2>
    <p class="muted">
        The <code>auth_tokens</code> table stores remember-me tokens, password-reset tokens, and email-verify
        tokens. Expired rows can be purged safely — they're already unusable.
    </p>
    <form method="post" action="<?= e(url('admin/maintenance.php')) ?>"
          onsubmit="return confirm('Delete <?= number_format($authExpired) ?> expired auth token(s)?');">
        <?= csrf_field() ?>
        <input type="hidden" name="verb" value="purge_auth_tokens">
        <button type="submit" class="btn btn-primary" <?= $authExpired === 0 ? 'disabled' : '' ?>>
            Purge expired auth tokens
        </button>
    </form>
</section>

<section class="admin-card health-card">
    <h2>Rate-limit buckets <small class="muted"><?= number_format($rateTotal) ?> total · <?= number_format($rateExpired) ?> expired</small></h2>
    <p class="muted">
        Sliding-window rate-limit state. Each bucket auto-expires; this purge removes the rows.
    </p>
    <form method="post" action="<?= e(url('admin/maintenance.php')) ?>"
          onsubmit="return confirm('Delete <?= number_format($rateExpired) ?> expired rate-limit bucket(s)?');">
        <?= csrf_field() ?>
        <input type="hidden" name="verb" value="purge_rate_limits">
        <button type="submit" class="btn btn-primary" <?= $rateExpired === 0 ? 'disabled' : '' ?>>
            Purge expired rate limits
        </button>
    </form>
</section>

<section class="admin-card health-card">
    <h2>Automating these purges</h2>
    <p class="muted">
        Add a weekly cron entry that hits this page (admin auth required), or call the underlying
        functions from a CLI script. Example (every Sunday at 03:00):
    </p>
    <pre class="muted" style="white-space: pre-wrap; word-break: break-word;">0 3 * * 0 curl -fsSL --cookie /path/to/admin-session.cookie "<?= e(url('admin/maintenance.php')) ?>" >/dev/null</pre>
</section>
