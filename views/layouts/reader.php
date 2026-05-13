<?php
defined('APP_BOOTED') or exit;
/** @var string $__contents */
/** @var string|null $pageTitle */
/** @var array $book */
$pageTitle = $pageTitle ?? ($book['title'] ?? 'Reading');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="app-base" content="<?= e(app_base()) ?>">
    <meta name="app-version" content="<?= e(app_version()) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="book-uuid" content="<?= e($book['uuid']) ?>">
    <meta name="book-title" content="<?= e($book['title']) ?>">
    <meta name="book-author" content="<?= e($book['author']) ?>">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="<?= e(asset('favicon.ico')) ?>" sizes="any">
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/reader.css')) ?>">
    <?php
    // Prefer locally vendored libs (see assets/vendor/README.md); fall back to
    // CDN. This block is the only place we touch external origins — the rest
    // of the app is same-origin only.
    $jszipLocal = project_path('assets/vendor/jszip.min.js');
    $epubLocal  = project_path('assets/vendor/epub.min.js');
    ?>
    <?php if (is_file($jszipLocal)): ?>
        <script src="<?= e(asset('vendor/jszip.min.js')) ?>" defer></script>
    <?php else: ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.5/jszip.min.js"
                crossorigin="anonymous" defer></script>
    <?php endif; ?>
    <?php if (is_file($epubLocal)): ?>
        <script src="<?= e(asset('vendor/epub.min.js')) ?>" defer></script>
    <?php else: ?>
        <script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"
                crossorigin="anonymous" defer></script>
    <?php endif; ?>
</head>
<body class="reader-page">
    <?= $__contents ?>
    <script type="module" src="<?= e(asset('js/reader.js')) ?>"></script>
</body>
</html>
