<?php
defined('APP_BOOTED') or exit;
/** @var int $page */
/** @var int $pages */
/** @var array $queryParams */
/** @var string|null $baseUrl  e.g. 'index.php', 'genre.php', 'search.php' */
if ($pages <= 1) {
    return;
}
$baseUrl = $baseUrl ?? 'index.php';
$baseQuery = $queryParams;
unset($baseQuery['page']);
$linkFor = static function (int $p) use ($baseQuery, $baseUrl) {
    $baseQuery['page'] = $p;
    return url($baseUrl . '?' . http_build_query($baseQuery));
};
$range = 2;
$start = max($page - $range, 1);
$end   = min($page + $range, $pages);
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a href="<?= e($linkFor(1)) ?>" aria-label="First page">« First</a>
        <a href="<?= e($linkFor($page - 1)) ?>" rel="prev" aria-label="Previous page">‹ Prev</a>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php if ($i === $page): ?>
            <span aria-current="page"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= e($linkFor($i)) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $pages): ?>
        <a href="<?= e($linkFor($page + 1)) ?>" rel="next" aria-label="Next page">Next ›</a>
        <a href="<?= e($linkFor($pages)) ?>" aria-label="Last page">Last »</a>
    <?php endif; ?>
</nav>
