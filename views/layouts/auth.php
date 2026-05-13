<?php
defined('APP_BOOTED') or exit;
/** @var string $__contents */
/** @var string|null $pageTitle */
$pageTitle = $pageTitle ?? 'Sign in';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(config('app_name', 'ePublicLibrary')) ?></title>
    <meta name="app-base" content="<?= e(app_base()) ?>">
    <meta name="app-version" content="<?= e(app_version()) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="<?= e(asset('favicon.ico')) ?>" sizes="any">
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/auth.css')) ?>">
</head>
<body class="auth-page">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <main id="main-content" class="auth-shell">
        <a class="auth-logo" href="<?= e(url('index.php')) ?>">
            <span class="auth-logo-mark">B</span>
            <span class="auth-logo-text"><?= e(config('app_name', 'ePublicLibrary')) ?></span>
        </a>
        <?php
        foreach (['success', 'error', 'info'] as $kind) {
            $msg = flash($kind);
            if ($msg) {
                echo '<div class="flash flash-' . e($kind) . '" role="status">' . e($msg) . '</div>';
            }
        }
        ?>
        <?= $__contents ?>
    </main>
    <script nonce="<?= e(csp_nonce()) ?>">
        // After a server-side validation failure, surface the error container
        // to keyboard users (the role="alert" already announces it for screen
        // readers; this moves visual focus too).
        document.addEventListener('DOMContentLoaded', () => {
            const err = document.querySelector('.form-errors[role="alert"]');
            if (err) {
                err.setAttribute('tabindex', '-1');
                err.focus({ preventScroll: false });
            }
        });
    </script>
</body>
</html>
