<?php
define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_verify_or_abort();
    logout_user();
    redirect('index.php');
}

// GET shows a confirmation page so accidental link visits don't sign out.
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign out · <?= e(config('app_name', 'ePublicLibrary')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/auth.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-shell">
    <div class="auth-card">
        <h1>Sign out?</h1>
        <p class="muted">You'll need to sign in again to sync your reading progress.</p>
        <form method="post" action="<?= e(url('logout.php')) ?>" class="auth-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary btn-block">Sign out</button>
            <a href="<?= e(url('index.php')) ?>" class="btn btn-ghost btn-block">Cancel</a>
        </form>
    </div>
</main>
</body>
</html>
