<?php defined('APP_BOOTED') or exit; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Not found · <?= e(config('app_name', 'ePublicLibrary')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
</head>
<body>
<main style="max-width:540px; margin:8vh auto; padding:2rem; text-align:center;">
    <h1 style="font-size:6rem; line-height:1; color:var(--accent-500); margin-bottom:1rem;">404</h1>
    <h2 style="margin-bottom:0.5rem;">Not found</h2>
    <p class="muted" style="margin-bottom:2rem;">
        <?= e($message ?? "We couldn't find that book or page.") ?>
    </p>
    <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Back to the library</a>
</main>
</body>
</html>
