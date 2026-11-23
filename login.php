<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post_string('username');
    $password = (string) ($_POST['password'] ?? '');
    $user = db_one('SELECT * FROM users WHERE username = ?', [$username]);

    if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
        if ($user && $user['is_active']) {
            db_exec('UPDATE users SET failed_attempts = failed_attempts + 1 WHERE user_id = ?',
                [$user['user_id']]);
            audit('LOGIN_FAILED', "Invalid credentials for '{$username}'", (int) $user['user_id']);
        } else {
            audit('LOGIN_FAILED', "Unknown or deactivated username '{$username}'");
        }
        flash('Invalid username or password.', 'error');
    } else {
        db_exec('UPDATE users SET failed_attempts = 0 WHERE user_id = ?', [$user['user_id']]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['last_activity'] = time();
        audit('LOGIN_SUCCESS', "'{$username}' logged in as {$user['role']}", (int) $user['user_id']);
        redirect('index.php');
    }
}

$page_title = 'Log in';
$active_nav = '';
render('login', ['username' => $username]);
