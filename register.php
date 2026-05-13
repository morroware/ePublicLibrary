<?php
define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

if (is_authed()) {
    redirect('index.php');
}

$errors = [];
$input = ['email' => '', 'username' => '', 'display_name' => ''];

if (is_post()) {
    csrf_verify_or_abort();

    $input = [
        'email'        => trim((string) ($_POST['email'] ?? '')),
        'username'     => trim((string) ($_POST['username'] ?? '')),
        'display_name' => trim((string) ($_POST['display_name'] ?? '')),
    ];
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirmation'] ?? '');

    // Per-IP rate limit on signups
    $ipBucket = 'register:ip:' . (client_ip() ?: 'unknown');
    if (!rate_limit_check($ipBucket, 5, 3600)) {
        $errors[] = 'Too many sign-up attempts. Try again later.';
    }

    if (!$errors) {
        if ($input['email'] === '' || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/', $input['username'])) {
            $errors[] = 'Username must be 3–40 characters: letters, numbers, ".", "_", "-".';
        }
        if ($password !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }
        $errors = array_merge($errors, PasswordHasher::validate($password, $input['username'], $input['email']));

        if (!$errors && UserRepository::exists($input['email'], $input['username'])) {
            $errors[] = 'An account with that email or username already exists.';
        }
    }

    if (!$errors) {
        $userId = UserRepository::create([
            'email'         => $input['email'],
            'username'      => $input['username'],
            'display_name'  => $input['display_name'] ?: null,
            'password_hash' => PasswordHasher::hash($password),
            'role'          => 'reader',
        ]);
        CollectionRepository::seedSystemCollections($userId);
        rate_limit_hit($ipBucket, 3600);
        $user = UserRepository::findById($userId);
        AuditLogger::log('auth.register', 'user', $userId);
        login_user($user, false);
        flash('success', 'Welcome! Your account is ready.');
        redirect('index.php');
    }
}

render('auth/register', [
    'pageTitle' => 'Create your account',
    'errors'    => $errors,
    'input'     => $input,
], 'auth');
