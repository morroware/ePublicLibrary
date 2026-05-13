<?php
defined('APP_BOOTED') or exit;

/** @var string $__contents prepared by view.php */
/** @var string|null $pageTitle */
/** @var array|null $bodyAttrs */
/** @var string $pageClass */
$pageTitle = $pageTitle ?? config('app_name', 'ePublicLibrary');
$pageClass = $pageClass ?? '';
$user = current_user();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <title><?= e($pageTitle) ?></title>
    <meta name="app-base" content="<?= e(app_base()) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="<?= e(asset('favicon.ico')) ?>" sizes="any">
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/library.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/discovery.css')) ?>">
    <link rel="preconnect" href="<?= e(asset('fonts/')) ?>" crossorigin>
</head>
<body class="<?= e($pageClass) ?>" data-app-base="<?= e(app_base()) ?>">
    <a class="skip-link" href="#main-content">Skip to content</a>

    <?php partial('header', ['user' => $user]); ?>

    <main id="main-content" tabindex="-1">
        <?php
        // Flash messages
        foreach (['success', 'error', 'info'] as $kind) {
            $msg = flash($kind);
            if ($msg) {
                echo '<div class="flash flash-' . e($kind) . '" role="status">' . e($msg) . '</div>';
            }
        }
        ?>
        <?= $__contents ?>
    </main>

    <?php partial('footer'); ?>

    <script type="module" src="<?= e(asset('js/library.js')) ?>"></script>
</body>
</html>
