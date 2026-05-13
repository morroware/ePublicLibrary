<?php
defined('APP_BOOTED') or exit;
/** @var array $errors */
/** @var string $email */
/** @var bool $submitted */
?>
<div class="auth-card">
    <h1>Forgot your password?</h1>
    <p class="muted">Enter your email and we'll send you a link to set a new one.
       The link is valid for one hour.</p>

    <?php if ($errors): ?>
        <div class="form-errors" role="alert">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($submitted): ?>
        <div class="flash flash-success" role="status">
            If <strong><?= e($email) ?></strong> is registered, a reset link is on its way.
            Check your inbox (and spam folder).
        </div>
        <p class="auth-alt"><a href="<?= e(url('login.php')) ?>">Back to sign in</a></p>
    <?php else: ?>
        <form method="post" action="<?= e(url('forgot-password.php')) ?>" class="auth-form" novalidate>
            <?= csrf_field() ?>
            <label>
                <span>Email</span>
                <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
        </form>
        <p class="auth-alt"><a href="<?= e(url('login.php')) ?>">Back to sign in</a></p>
    <?php endif; ?>
</div>
