<?php
defined('APP_BOOTED') or exit;
/** @var array $user */
/** @var array $errors */
?>
<div class="content-wrapper account-wrapper">
    <h1 class="section-title">Your account</h1>

    <?php if ($errors): ?>
        <div class="form-errors" role="alert">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="account-section">
        <h2>Profile</h2>
        <form method="post" action="<?= e(url('account.php')) ?>" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">

            <label>
                <span>Display name</span>
                <input type="text" name="display_name" value="<?= e($user['display_name'] ?? '') ?>" maxlength="120">
            </label>

            <dl class="account-readonly">
                <dt>Email</dt><dd><?= e($user['email']) ?></dd>
                <dt>Username</dt><dd><?= e($user['username']) ?></dd>
                <dt>Role</dt><dd><?= e($user['role']) ?></dd>
                <dt>Member since</dt><dd><?= e(date('M j, Y', strtotime($user['created_at']))) ?></dd>
                <dt>Last sign-in</dt><dd><?= $user['last_login_at'] ? e(date('M j, Y g:i a', strtotime($user['last_login_at']))) : 'Just now' ?></dd>
            </dl>

            <button type="submit" class="btn btn-primary">Save profile</button>
        </form>
    </section>

    <section class="account-section">
        <h2>Change password</h2>
        <form method="post" action="<?= e(url('account.php')) ?>" class="form" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">

            <label>
                <span>Current password</span>
                <input type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label>
                <span>New password</span>
                <input type="password" name="new_password" required minlength="12" autocomplete="new-password">
                <small>Minimum 12 characters.</small>
            </label>
            <label>
                <span>Confirm new password</span>
                <input type="password" name="new_password_confirmation" required minlength="12" autocomplete="new-password">
            </label>
            <button type="submit" class="btn btn-primary">Change password</button>
        </form>
    </section>
</div>
