<?php
defined('APP_BOOTED') or exit;
/** @var array $messages */
/** @var int $maxMb */
?>
<div class="admin-page-header">
    <h1>Upload books</h1>
    <p class="muted">Drag and drop EPUB files, or pick from disk. Each file is
       validated, metadata extracted, and a thumbnail generated automatically.
       Maximum size <?= e((string) $maxMb) ?> MB per file.</p>
</div>

<?php if ($messages): ?>
    <div class="upload-messages">
        <?php foreach ($messages as $m): ?>
            <div class="upload-message upload-<?= e($m['type']) ?>"><?= e($m['text']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/upload.php')) ?>" enctype="multipart/form-data"
      class="upload-form" id="uploadForm">
    <?= csrf_field() ?>
    <label for="epub-input" class="upload-dropzone" id="dropzone">
        <input type="file" name="epub[]" id="epub-input" multiple accept=".epub" hidden>
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
        </svg>
        <span class="upload-prompt">
            <strong>Drop EPUB files here</strong>
            or click to browse
        </span>
        <span class="upload-hint">Up to <?= e((string) $maxMb) ?> MB per file</span>
    </label>

    <div class="upload-actions">
        <button type="submit" class="btn btn-primary">Upload selected</button>
        <a href="<?= e(url('admin/books.php')) ?>" class="btn btn-ghost">View all books</a>
    </div>
</form>

<aside class="upload-tips">
    <h2>Tips</h2>
    <ul>
        <li>Drag a folder to upload all EPUBs inside it (file selection only, not recursive folder upload — server still processes each file individually).</li>
        <li>Re-uploading the same file is safe; duplicates are detected by SHA-256 hash.</li>
        <li>To bulk-import an existing <code>books/</code> directory from the legacy PoC, use
            <a href="<?= e(url('admin/scan-books.php')) ?>">Import legacy books</a>.</li>
    </ul>
</aside>
