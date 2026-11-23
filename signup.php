<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$form = ['full_name' => '', 'username' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form = [
        'full_name' => post_string('full_name'),
        'username' => post_string('username'),
    ];
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($form['full_name'] === '' || $form['username'] === '') {
        flash('Full name and username are required.', 'error');
    } elseif ($password !== $confirm) {
        flash('Passwords do not match.', 'error');
    } elseif (strlen($password) < 8) {
        flash('Password must be at least 8 characters.', 'error');
    } elseif (db_one('SELECT user_id FROM users WHERE username = ?', [$form['username']])) {
        flash('That username is already taken.', 'error');
    } else {
        // Self-registration can never mint an administrator; those come from the Users page.
        $user_id = db_insert(
            'INSERT INTO users (full_name, username, password_hash, role) VALUES (?, ?, ?, ?)',
            [$form['full_name'], $form['username'], password_hash($password, PASSWORD_DEFAULT), ROLE_FIELD]
        );
        audit('ACCOUNT_CREATED', "Self-registered account '{$form['username']}' as " . ROLE_FIELD, $user_id);
        flash('Account created. You can now log in.', 'success');
        redirect('login.php');
    }
}

$page_title = 'Register';
render('signup', ['form' => $form]);
