<?php defined('APP_BOOTED') or exit; ?>
<footer class="site-footer">
    <div class="footer-inner">
        <span><?= e(config('app_name', 'ePublicLibrary')) ?></span>
        <span class="footer-sep">·</span>
        <a href="<?= e(url('index.php')) ?>">Library</a>
        <?php if (is_guest()): ?>
            <span class="footer-sep">·</span>
            <a href="<?= e(url('login.php')) ?>">Sign in</a>
            <span class="footer-sep">·</span>
            <a href="<?= e(url('register.php')) ?>">Create account</a>
        <?php endif; ?>
    </div>
</footer>
