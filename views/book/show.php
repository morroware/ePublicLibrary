<?php
defined('APP_BOOTED') or exit;
/** @var array $book */
/** @var array $tags */
$readUrl = url('read.php?b=' . eurl($book['uuid']));
$downloadUrl = url('api/download.php?b=' . eurl($book['uuid']));
?>
<div class="content-wrapper" style="max-width:960px;">
    <a href="<?= e(url('index.php')) ?>" class="book-back-link">← Back to library</a>

    <article class="book-detail">
        <aside class="book-detail-cover">
            <?php if (!empty($book['cover_path'])): ?>
                <img src="<?= e(asset($book['cover_path'])) ?>" alt="Cover of <?= e($book['title']) ?>">
            <?php else: ?>
                <div class="book-detail-placeholder" aria-hidden="true"><?= e(mb_substr($book['title'], 0, 32)) ?></div>
            <?php endif; ?>
            <div class="book-detail-actions">
                <a class="btn btn-primary btn-block" href="<?= e($readUrl) ?>">Read now</a>
                <a class="btn btn-ghost btn-block" href="<?= e($downloadUrl) ?>" download>Download EPUB</a>
            </div>
        </aside>

        <div class="book-detail-info">
            <h1 class="book-detail-title"><?= e($book['title']) ?></h1>
            <?php if (!empty($book['subtitle'])): ?>
                <h2 class="book-detail-subtitle"><?= e($book['subtitle']) ?></h2>
            <?php endif; ?>
            <p class="book-detail-author">by <strong><?= e($book['author']) ?></strong></p>

            <?php if ($tags): ?>
                <ul class="book-detail-tags">
                    <?php foreach ($tags as $t): ?>
                        <li class="badge"><?= e($t['name']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($book['description'])): ?>
                <section class="book-detail-description">
                    <h3>About this book</h3>
                    <?php if (!empty($book['description_html'])): ?>
                        <div><?= $book['description_html'] /* already sanitized at insert */ ?></div>
                    <?php else: ?>
                        <p><?= nl2br(e($book['description'])) ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <dl class="book-detail-meta">
                <?php if ($book['publisher']): ?><dt>Publisher</dt><dd><?= e($book['publisher']) ?></dd><?php endif; ?>
                <?php if ($book['published_date']): ?><dt>Published</dt><dd><?= e($book['published_date']) ?></dd><?php endif; ?>
                <?php if ($book['language']): ?><dt>Language</dt><dd><?= e($book['language']) ?></dd><?php endif; ?>
                <?php if ($book['isbn']): ?><dt>ISBN</dt><dd><?= e($book['isbn']) ?></dd><?php endif; ?>
                <dt>File size</dt><dd><?= e(number_format($book['file_size'] / 1024 / 1024, 1)) ?> MB</dd>
                <dt>Added</dt><dd><?= e(date('M j, Y', strtotime($book['created_at']))) ?></dd>
            </dl>
        </div>
    </article>
</div>

<style nonce="<?= e(csp_nonce()) ?>">
.book-back-link { display: inline-block; margin-bottom: 1.5rem; color: var(--text-tertiary); }
.book-detail { display: grid; grid-template-columns: 240px 1fr; gap: 2.5rem; align-items: start; }
@media (max-width: 720px) { .book-detail { grid-template-columns: 1fr; max-width: 480px; margin: 0 auto; } }
.book-detail-cover img,
.book-detail-placeholder {
    width: 100%; aspect-ratio: 2/3; border-radius: var(--radius-md);
    box-shadow: var(--shadow-medium); object-fit: cover;
    background: linear-gradient(135deg, var(--accent-200), var(--accent-400));
}
.book-detail-placeholder {
    display: flex; align-items: center; justify-content: center; padding: 1rem;
    text-align: center; color: var(--accent-700); font-family: var(--font-display);
}
.book-detail-actions { margin-top: 1rem; display: grid; gap: 0.5rem; }
.book-detail-title { font-size: var(--text-4xl); margin-bottom: 0.3rem; line-height: 1.1; }
.book-detail-subtitle { font-size: var(--text-xl); color: var(--text-secondary); font-weight: 400; margin-bottom: 1rem; }
.book-detail-author { color: var(--text-secondary); margin-bottom: 1.5rem; }
.book-detail-tags { list-style: none; display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 1.5rem 0; padding: 0; }
.book-detail-description { margin: 2rem 0; }
.book-detail-description h3 { font-size: var(--text-lg); margin-bottom: 0.5rem; }
.book-detail-meta { display: grid; grid-template-columns: max-content 1fr; gap: 0.4rem 1.5rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-default); font-size: var(--text-sm); }
.book-detail-meta dt { color: var(--text-tertiary); }
</style>
