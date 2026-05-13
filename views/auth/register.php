<?php
defined('APP_BOOTED') or exit;
/** @var array $errors */
/** @var array $input */
?>
<div class="auth-card">
    <h1>Create your account</h1>
    <p class="muted">Your account syncs reading progress, bookmarks, and shelves across devices.</p>

    <?php if ($errors): ?>
        <div class="form-errors" role="alert">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('register.php')) ?>" class="auth-form" novalidate autocomplete="off">
        <?= csrf_field() ?>

        <label>
            <span>Email</span>
            <input type="email" name="email" value="<?= e($input['email']) ?>" required autofocus autocomplete="email">
        </label>

        <label>
            <span>Username</span>
            <input type="text" name="username" value="<?= e($input['username']) ?>" required
                   minlength="3" maxlength="40" pattern="[a-zA-Z0-9_.\-]{3,40}"
                   autocomplete="username">
            <small>3–40 characters. Letters, numbers, ".", "_", "-".</small>
        </label>

        <label>
            <span>Display name <small>(optional)</small></span>
            <input type="text" name="display_name" value="<?= e($input['display_name']) ?>" maxlength="120">
        </label>

        <label>
            <span>Password</span>
            <input type="password" name="password" required minlength="12" autocomplete="new-password">
            <small>Minimum 12 characters. Choose something memorable.</small>
        </label>

        <label>
            <span>Confirm password</span>
            <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
        </label>

        <button type="submit" class="btn btn-primary btn-block">Create account</button>
    </form>

    <p class="auth-alt">
        Already have an account? <a href="<?= e(url('login.php')) ?>">Sign in</a>
    </p>
</div>
