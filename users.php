<?php
declare(strict_types=1);

/** Account administration: create staff accounts and enable/disable them. */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_string('action');

    if ($action === 'create') {
        $full_name = post_string('full_name');
        $username = post_string('username');
        $password = (string) ($_POST['password'] ?? '');
        $role = post_string('role') === ROLE_ADMIN ? ROLE_ADMIN : ROLE_FIELD;

        if ($full_name === '' || $username === '') {
            flash('Full name and username are required.', 'error');
        } elseif (strlen($password) < 8) {
            flash('Password must be at least 8 characters.', 'error');
        } elseif (db_one('SELECT user_id FROM users WHERE username = ?', [$username])) {
            flash('That username is already taken.', 'error');
        } else {
            db_insert(
                'INSERT INTO users (full_name, username, password_hash, role) VALUES (?, ?, ?, ?)',
                [$full_name, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
            audit('USER_CREATED', "Created account '{$username}' as {$role}");
            flash("Account for {$username} created.", 'success');
        }
        redirect('users.php');
    }

    if ($action === 'toggle') {
        $target_id = (int) post_string('user_id');
        $target = db_one('SELECT * FROM users WHERE user_id = ?', [$target_id]);
        if (!$target) {
            http_response_code(404);
            $page_title = 'Not found';
            render('error', ['code' => 404, 'message' => 'That account does not exist.']);
            exit;
        }
        if ($target_id === current_user_id()) {
            flash('You cannot deactivate your own account.', 'error');
            redirect('users.php');
        }
        $new_state = $target['is_active'] ? 0 : 1;
        db_exec('UPDATE users SET is_active = ? WHERE user_id = ?', [$new_state, $target_id]);
        audit('USER_STATUS_CHANGED', ($new_state ? 'Activated' : 'Deactivated')
            . " account '{$target['username']}'");
        flash("Account '{$target['username']}' " . ($new_state ? 'activated' : 'deactivated') . '.', 'success');
        redirect('users.php');
    }

    flash('Unknown request.', 'error');
    redirect('users.php');
}

$accounts = db_query(
    'SELECT u.*, (SELECT COUNT(*) FROM audit_log a WHERE a.user_id = u.user_id) AS activity
     FROM users u ORDER BY u.username');

$page_title = 'Users';
$active_nav = 'users';
render('users', ['accounts' => $accounts]);
