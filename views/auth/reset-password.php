<?php
defined('APP_BOOTED') or exit;
/** @var array $errors */
/** @var string $token */
/** @var bool $haveUser */
?>
<div class="auth-card">
    <h1>Set a new password</h1>

    <?php if (!$haveUser): ?>
        <p class="muted">This reset link is invalid or expired.</p>
        <?php if ($errors): ?>
            <div class="form-errors" role="alert">
                <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <p>
            <a class="btn btn-primary btn-block" href="<?= e(url('forgot-password.php')) ?>">Request a new link</a>
        </p>
    <?php else: ?>
        <p class="muted">Choose a strong password — minimum 12 characters.
           After saving, you'll be signed out everywhere on this device.</p>

        <?php if ($errors): ?>
            <div class="form-errors" role="alert">
                <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('reset-password.php')) ?>" class="auth-form" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <label>
                <span>New password</span>
                <input type="password" name="password" required minlength="12" autofocus autocomplete="new-password">
                <small>At least 12 characters.</small>
            </label>
            <label>
                <span>Confirm new password</span>
                <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Save new password</button>
        </form>
    <?php endif; ?>
</div>
