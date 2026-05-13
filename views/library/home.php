<?php
defined('APP_BOOTED') or exit;
/** @var array $continueReading */
/** @var array $recentlyAdded */
/** @var array $topRated */
/** @var int $totalBooks */
?>
<div class="content-wrapper home-content">
    <?php if (is_guest()): ?>
        <div class="guest-banner" role="region" aria-label="Sign in benefits">
            <div class="guest-banner-text">
                <strong>Welcome.</strong> Sign in to keep your place across devices, build
                shelves, and rate the books you've read.
            </div>
            <div class="guest-banner-actions">
                <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Sign in</a>
                <a class="btn btn-ghost" href="<?= e(url('register.php')) ?>">Create account</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($continueReading)): ?>
        <?php partial('continue-reading-rail', ['items' => $continueReading]); ?>
    <?php endif; ?>

    <?php partial('book-rail', [
        'title'     => 'Recently added',
        'books'     => $recentlyAdded,
        'seeAllUrl' => url('index.php?sort=created&order=desc'),
        'emptyText' => 'No books yet — your library will fill in as books are uploaded.',
    ]); ?>

    <?php if (!empty($topRated)): ?>
        <?php partial('book-rail', [
            'title'     => 'Top rated',
            'books'     => $topRated,
            'seeAllUrl' => url('index.php?sort=rating&order=desc'),
        ]); ?>
    <?php endif; ?>

    <?php if (is_authed()): ?>
        <section class="home-shortcut-row">
            <a class="home-shortcut" href="<?= e(url('collections.php')) ?>">
                <span class="home-shortcut-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2H7a2 2 0 00-2 2v2m12-4V5a2 2 0 00-2-2H9a2 2 0 00-2 2v2"/></svg>
                </span>
                <div>
                    <strong>Your shelves</strong>
                    <small>Organize what you've read, want to read, and love.</small>
                </div>
            </a>
            <a class="home-shortcut" href="<?= e(url('search.php')) ?>">
                <span class="home-shortcut-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <div>
                    <strong>Advanced search</strong>
                    <small>Filter by genre, language, year, or rating.</small>
                </div>
            </a>
        </section>
    <?php endif; ?>

    <p class="home-stat">
        <span class="home-stat-count"><?= number_format($totalBooks) ?></span>
        <span class="home-stat-label">book<?= $totalBooks !== 1 ? 's' : '' ?> in the library</span>
        <a class="home-stat-link" href="<?= e(url('index.php?sort=title&order=asc')) ?>">Browse all →</a>
    </p>
</div>
