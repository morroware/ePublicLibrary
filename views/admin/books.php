<?php
defined('APP_BOOTED') or exit;
/** @var array $books */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var string $searchTerm */
?>
<div class="admin-page-header">
    <h1>Books</h1>
    <p class="muted"><?= number_format($total) ?> total · sorted by recently added</p>
</div>

<form method="get" action="<?= e(url('admin/books.php')) ?>" class="admin-toolbar">
    <input type="search" name="q" value="<?= e($searchTerm) ?>" placeholder="Search books..."
           aria-label="Search books">
    <button type="submit" class="btn btn-ghost">Search</button>
    <a href="<?= e(url('admin/upload.php')) ?>" class="btn btn-primary">+ Upload</a>
</form>

<?php if (!$books): ?>
    <div class="empty-state">
        <h2>No books to manage</h2>
        <p><?= $searchTerm ? 'Nothing matches that search.' : 'Upload an EPUB to get started.' ?></p>
    </div>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th class="cover-cell">Cover</th>
                <th>Title</th>
                <th>Author</th>
                <th>Status</th>
                <th>Added</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($books as $b): ?>
                <tr>
                    <td class="cover-cell">
                        <?php if (!empty($b['cover_path'])): ?>
                            <img src="<?= e(asset($b['cover_path'])) ?>" alt="">
                        <?php else: ?>
                            <div class="cover-placeholder" aria-hidden="true"></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= e(url('admin/books.php?action=edit&id=' . $b['id'])) ?>">
                            <?= e($b['title']) ?>
                        </a>
                    </td>
                    <td><?= e($b['author']) ?></td>
                    <td><span class="badge badge-<?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
                    <td><?= e(date('M j, Y', strtotime($b['created_at']))) ?></td>
                    <td class="actions-cell">
                        <a href="<?= e(url('book.php?b=' . $b['uuid'])) ?>" title="View">View</a>
                        <a href="<?= e(url('read.php?b=' . $b['uuid'])) ?>" title="Read">Read</a>
                        <a href="<?= e(url('admin/books.php?action=edit&id=' . $b['id'])) ?>" title="Edit">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Books pagination">
            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <?php $params = http_build_query(array_filter(['q' => $searchTerm, 'page' => $i])); ?>
                <?php if ($i === $page): ?>
                    <span aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= e(url('admin/books.php?' . $params)) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
