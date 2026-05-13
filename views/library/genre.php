<?php
defined('APP_BOOTED') or exit;
/** @var array $tag */
/** @var array $books */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var array $queryParams */
?>
<div class="content-wrapper">
    <a href="<?= e(url('index.php')) ?>" class="book-back-link">← Library</a>

    <header class="page-header">
        <h1 class="genre-title"><?= e($tag['name']) ?></h1>
        <p class="muted"><?= number_format($total) ?> book<?= $total !== 1 ? 's' : '' ?> in this <?= e($tag['kind']) ?></p>
    </header>

    <?php if (empty($books)): ?>
        <div class="empty-state">
            <h2>No books in this <?= e($tag['kind']) ?> yet</h2>
            <p>Try <a href="<?= e(url('index.php')) ?>">browsing the full library</a>.</p>
        </div>
    <?php else: ?>
        <div class="book-grid">
            <?php foreach ($books as $b): ?>
                <?php partial('book-card', ['book' => $b]); ?>
            <?php endforeach; ?>
        </div>
        <?php partial('pagination', [
            'page' => $page, 'pages' => $pages,
            'queryParams' => $queryParams,
            'baseUrl' => 'genre.php',
        ]); ?>
    <?php endif; ?>
</div>
