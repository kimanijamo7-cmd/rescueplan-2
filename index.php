<?php
declare(strict_types=1);

/** Dashboard: headline counts, camp coverage, and the most recent activity. */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$counts = db_one(
    "SELECT COUNT(*) AS total,
            SUM(record_status = 'verified')          AS verified,
            SUM(record_status = 'duplicate_flagged') AS flagged,
            SUM(record_status = 'pending_review')    AS pending_review,
            SUM(record_status = 'merged_inactive')   AS merged
     FROM beneficiaries") ?? [];

$camps = db_query(
    "SELECT camp_location, COUNT(*) AS records,
            SUM(record_status = 'verified') AS verified
     FROM beneficiaries GROUP BY camp_location ORDER BY records DESC");

$stats = [
    'pending_clusters' => (int) db_value(
        "SELECT COUNT(*) FROM match_clusters WHERE match_status = 'pending'"),
    'users' => (int) db_value('SELECT COUNT(*) FROM users'),
    'household_members' => (int) db_value(
        "SELECT COALESCE(SUM(household_size), 0) FROM beneficiaries WHERE record_status = 'verified'"),
];

$recent = db_query(
    'SELECT a.*, u.username FROM audit_log a
     LEFT JOIN users u ON u.user_id = a.user_id
     ORDER BY a.log_id DESC LIMIT 8');

$page_title = 'Dashboard';
$active_nav = 'dashboard';
render('dashboard', ['counts' => $counts, 'camps' => $camps, 'stats' => $stats, 'recent' => $recent]);
