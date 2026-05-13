<?php
defined('APP_BOOTED') or exit;
/** @var array $items  rows from ProgressRepository::continueReading() joined with books */
if (empty($items)) return;
?>
<section class="book-rail continue-reading" aria-labelledby="continue-reading-title">
    <header class="book-rail-header">
        <h2 id="continue-reading-title" class="book-rail-title">Continue reading</h2>
    </header>
    <div class="book-rail-track" role="list">
        <?php foreach ($items as $row): ?>
            <?php
            $readUrl = url('read.php?b=' . eurl($row['book_uuid']));
            $cover   = !empty($row['cover_path']) ? asset($row['cover_path']) : null;
            $percent = (float) $row['percentage'];
            ?>
            <a role="listitem" class="continue-card" href="<?= e($readUrl) ?>"
               aria-label="Resume reading <?= e($row['title']) ?> by <?= e($row['author']) ?>, <?= e(number_format($percent, 0)) ?> percent complete">
                <div class="continue-cover" <?= $cover ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>>
                    <?php if (!$cover): ?>
                        <span class="continue-cover-fallback"><?= e(mb_substr($row['title'], 0, 24)) ?></span>
                    <?php endif; ?>
                    <div class="continue-resume-overlay" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Resume</span>
                    </div>
                </div>
                <div class="continue-info">
                    <h3 class="continue-title"><?= e($row['title']) ?></h3>
                    <p class="continue-author"><?= e($row['author']) ?></p>
                    <div class="continue-progress" aria-hidden="true">
                        <div class="continue-progress-fill" style="width: <?= e(number_format($percent, 1)) ?>%"></div>
                    </div>
                    <div class="continue-meta">
                        <span><?= e(number_format($percent, 0)) ?>% · last read <?= e(humanTime($row['last_read_at'])) ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php
function humanTime(string $datetime): string
{
    static $cache = [];
    if (isset($cache[$datetime])) return $cache[$datetime];
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      $out = 'just now';
    elseif ($diff < 3600)  $out = floor($diff / 60) . ' min ago';
    elseif ($diff < 86400) $out = floor($diff / 3600) . ' hr ago';
    elseif ($diff < 604800) $out = floor($diff / 86400) . ' days ago';
    else                   $out = date('M j', strtotime($datetime));
    return $cache[$datetime] = $out;
}
?>
