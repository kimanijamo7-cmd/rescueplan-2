<?php
declare(strict_types=1);

/** Tamper-evident activity trail, filterable by action type. */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$action = get_string('action');
$sql = 'SELECT a.*, u.username, u.role FROM audit_log a LEFT JOIN users u ON u.user_id = a.user_id';
$params = [];
if ($action !== '') {
    $sql .= ' WHERE a.action = ?';
    $params[] = $action;
}
$sql .= ' ORDER BY a.log_id DESC LIMIT 200';

$entries = db_query($sql, $params);
$actions = db_query('SELECT DISTINCT action FROM audit_log ORDER BY action');

$page_title = 'Audit log';
$active_nav = 'audit';
render('audit_log', ['entries' => $entries, 'actions' => $actions, 'action' => $action]);
