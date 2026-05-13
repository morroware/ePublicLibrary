<?php defined('APP_BOOTED') or exit; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Too many requests · <?= e(config('app_name', 'ePublicLibrary')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
</head>
<body>
<main style="max-width:540px; margin:8vh auto; padding:2rem; text-align:center;">
    <h1 style="font-size:6rem; line-height:1; color:var(--accent-500); margin-bottom:1rem;">429</h1>
    <h2 style="margin-bottom:0.5rem;">Slow down</h2>
    <p class="muted" style="margin-bottom:2rem;">
        <?= e($message ?? 'You\'ve made too many requests in a short time. Please wait a moment and try again.') ?>
    </p>
    <a class="btn btn-ghost" href="<?= e(url('index.php')) ?>">Back to library</a>
</main>
</body>
</html>
