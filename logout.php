<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    audit('LOGOUT', "'" . current_name() . "' logged out");
}
$_SESSION = [];
session_regenerate_id(true);
flash('You have been logged out.', 'success');
redirect('login.php');
