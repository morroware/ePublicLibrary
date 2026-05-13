<?php
defined('APP_BOOTED') or exit;
/** @var array $errors */
/** @var string $loginValue */
?>
<div class="auth-card">
    <h1>Welcome back</h1>
    <p class="muted">Sign in to sync your library and reading progress.</p>

    <?php if ($errors): ?>
        <div class="form-errors" role="alert">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login.php')) ?>" class="auth-form" novalidate>
        <?= csrf_field() ?>

        <label>
            <span>Email or username</span>
            <input type="text" name="login" value="<?= e($loginValue) ?>" required autofocus autocomplete="username">
        </label>

        <label>
            <span>Password</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>

        <label class="checkbox">
            <input type="checkbox" name="remember" value="1">
            <span>Keep me signed in on this device</span>
        </label>

        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
    </form>

    <p class="auth-alt">
        New here? <a href="<?= e(url('register.php')) ?>">Create an account</a>
    </p>
    <p class="auth-alt">
        <a href="<?= e(url('index.php')) ?>">Continue as a guest</a>
    </p>
</div>
