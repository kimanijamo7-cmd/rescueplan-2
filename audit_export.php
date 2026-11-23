<?php
declare(strict_types=1);

/** Flat export of the full trail for offline review by programme managers. */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$rows = db_query(
    'SELECT a.*, u.username, u.role FROM audit_log a
     LEFT JOIN users u ON u.user_id = a.user_id ORDER BY a.log_id');

audit('AUDIT_EXPORTED', 'Exported ' . count($rows) . ' audit entries');

csv_download('audit_trail.csv',
    ['log_id', 'logged_at', 'username', 'role', 'action', 'details', 'ip_address'],
    array_map(static fn(array $row) => [
        $row['log_id'], $row['logged_at'], $row['username'], $row['role'],
        $row['action'], $row['details'], $row['ip_address'],
    ], $rows));
