<?php
defined('APP_BOOTED') or exit;
/** @var array $totals */
/** @var array $daily */
/** @var array $topBooks */
/** @var array $topGenres */
/** @var array $recent */
/** @var int   $reviewCount */
/** @var int   $highlightCount */

$hms = static function (int $sec): string {
    if ($sec < 60) return $sec . 's';
    $h = intdiv($sec, 3600);
    $m = intdiv($sec - $h * 3600, 60);
    if ($h > 0) return $h . 'h ' . $m . 'm';
    return $m . 'm';
};

$maxDaily = 0;
foreach ($daily as $d) {
    if ($d['seconds'] > $maxDaily) $maxDaily = (int) $d['seconds'];
}
$maxDaily = max(1, $maxDaily);
?>
<div class="content-wrapper stats-wrapper">
    <header class="page-header">
        <h1>Your reading stats</h1>
        <p class="muted">Everything we know about how you read on this library.</p>
    </header>

    <section class="stats-tiles">
        <div class="stat-tile">
            <div class="stat-tile-value"><?= e($hms($totals['total_seconds'])) ?></div>
            <div class="stat-tile-label">Time read</div>
        </div>
        <div class="stat-tile">
            <div class="stat-tile-value"><?= number_format($totals['books_started']) ?></div>
            <div class="stat-tile-label">Books opened</div>
        </div>
        <div class="stat-tile">
            <div class="stat-tile-value"><?= number_format($totals['books_finished']) ?></div>
            <div class="stat-tile-label">Books finished</div>
        </div>
        <div class="stat-tile">
            <div class="stat-tile-value"><?= number_format($totals['day_streak']) ?></div>
            <div class="stat-tile-label">Day streak</div>
        </div>
        <div class="stat-tile">
            <div class="stat-tile-value"><?= number_format($totals['sessions_count']) ?></div>
            <div class="stat-tile-label">Reading sessions</div>
        </div>
        <div class="stat-tile">
            <div class="stat-tile-value"><?= number_format(round($totals['words_read_est'] / 1000)) ?>k</div>
            <div class="stat-tile-label">Words read (estimate)</div>
        </div>
        <div class="stat-tile">
            <div class="stat-tile-value"><?= number_format($highlightCount) ?></div>
            <div class="stat-tile-label">Highlights</div>
        </div>
        <div class="stat-tile">
            <div class="stat-tile-value"><?= number_format($reviewCount) ?></div>
            <div class="stat-tile-label">Reviews posted</div>
        </div>
    </section>

    <section class="stats-section">
        <h2>Last 30 days</h2>
        <?php if ($totals['sessions_count'] === 0): ?>
            <p class="muted">No reading sessions yet — open any book and start reading. Your time is tracked automatically while you're on a reader tab.</p>
        <?php else: ?>
            <div class="stats-bar-chart" role="figure" aria-label="Reading time, last 30 days">
                <?php foreach ($daily as $d):
                    $h = (int) round(($d['seconds'] / $maxDaily) * 100);
                    $label = $d['seconds'] > 0 ? $hms((int) $d['seconds']) : '0';
                ?>
                    <div class="stats-bar" title="<?= e($d['day']) ?>: <?= e($label) ?>">
                        <div class="stats-bar-fill" style="height: <?= $h ?>%" aria-hidden="true"></div>
                        <span class="stats-bar-label"><?= e(substr($d['day'], 8, 2)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="stats-two-col">
        <section class="stats-section">
            <h2>Most-read books</h2>
            <?php if (empty($topBooks)): ?>
                <p class="muted">Read a book to see it here.</p>
            <?php else: ?>
                <ol class="stats-rank-list">
                    <?php foreach ($topBooks as $b):
                        $cover = !empty($b['cover_path']) ? asset($b['cover_path']) : null; ?>
                        <li>
                            <a class="stats-rank-item" href="<?= e(url('read.php?b=' . eurl($b['uuid']))) ?>">
                                <div class="stats-rank-cover" <?= $cover ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>></div>
                                <div class="stats-rank-info">
                                    <strong><?= e($b['title']) ?></strong>
                                    <span><?= e($b['author']) ?></span>
                                </div>
                                <span class="stats-rank-meta"><?= e($hms((int) $b['seconds'])) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>

        <section class="stats-section">
            <h2>Top genres</h2>
            <?php if (empty($topGenres)): ?>
                <p class="muted">Books need tags to appear here. Once books in the catalog have genres, your top ones will surface.</p>
            <?php else: ?>
                <ol class="stats-rank-list">
                    <?php foreach ($topGenres as $g): ?>
                        <li>
                            <a class="stats-rank-item" href="<?= e(url('genre.php?slug=' . eurl($g['slug']))) ?>">
                                <div class="stats-rank-info">
                                    <strong><?= e($g['name']) ?></strong>
                                </div>
                                <span class="stats-rank-meta"><?= e($hms((int) $g['seconds'])) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>

    <section class="stats-section">
        <h2>Recent reading sessions</h2>
        <?php if (empty($recent)): ?>
            <p class="muted">Nothing logged yet.</p>
        <?php else: ?>
            <table class="stats-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Book</th>
                        <th>Duration</th>
                        <th>Pages</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $s): ?>
                        <tr>
                            <td><time datetime="<?= e($s['started_at']) ?>"><?= e(date('M j, Y g:i a', strtotime($s['started_at']))) ?></time></td>
                            <td>
                                <a href="<?= e(url('book.php?b=' . eurl($s['book_uuid']))) ?>"><?= e($s['title']) ?></a>
                                <small class="muted"><?= e($s['author']) ?></small>
                            </td>
                            <td><?= e($hms((int) $s['duration_seconds'])) ?></td>
                            <td><?= number_format((int) $s['pages_read']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
