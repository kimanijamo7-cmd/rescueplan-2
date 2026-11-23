<?php
declare(strict_types=1);

/** Searchable register of beneficiary records with camp / status filters. */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$search = get_string('q');
$camp = get_string('camp');
$status = get_string('status');

$sql = 'SELECT * FROM beneficiaries WHERE 1 = 1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (full_name LIKE ? OR national_id LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($camp !== '') {
    $sql .= ' AND camp_location = ?';
    $params[] = $camp;
}
if (in_array($status, RECORD_STATUSES, true)) {
    $sql .= ' AND record_status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY date_registered DESC, beneficiary_id DESC';
$records = db_query($sql, $params);

$flag_rows = db_query(
    "SELECT cm.beneficiary_id, COUNT(*) AS pending
     FROM cluster_members cm
     JOIN match_clusters mc ON mc.cluster_id = cm.cluster_id
     WHERE mc.match_status = 'pending'
     GROUP BY cm.beneficiary_id");
$flags = [];
foreach ($flag_rows as $row) {
    $flags[(int) $row['beneficiary_id']] = (int) $row['pending'];
}
foreach ($records as &$record) {
    $record['pending_clusters'] = $flags[(int) $record['beneficiary_id']] ?? 0;
}
unset($record);

$camps = db_query('SELECT DISTINCT camp_location FROM beneficiaries ORDER BY camp_location');

$page_title = 'Beneficiaries';
$active_nav = 'beneficiaries';
render('beneficiaries', [
    'records' => $records, 'camps' => $camps, 'search' => $search,
    'camp' => $camp, 'status' => $status, 'total_records' => count($records),
]);
