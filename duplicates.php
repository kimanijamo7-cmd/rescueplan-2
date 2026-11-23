<?php
declare(strict_types=1);

/** Review queue: candidate duplicate clusters grouped by resolution status. */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$status = get_string('status');
if (!in_array($status, ['pending', 'confirmed_duplicate', 'false_positive'], true)) {
    $status = 'pending';
}

$clusters = db_query(
    'SELECT mc.*, u.full_name AS reviewer FROM match_clusters mc
     LEFT JOIN users u ON u.user_id = mc.reviewed_by
     WHERE mc.match_status = ?
     ORDER BY mc.similarity_score DESC, mc.cluster_id DESC', [$status]);

$groups = [];
foreach ($clusters as $cluster) {
    $members = db_query(
        'SELECT b.* FROM cluster_members cm
         JOIN beneficiaries b ON b.beneficiary_id = cm.beneficiary_id
         WHERE cm.cluster_id = ?
         ORDER BY b.date_registered, b.beneficiary_id', [$cluster['cluster_id']]);
    $groups[] = ['cluster' => $cluster, 'members' => $members];
}

$counts = [];
foreach (['pending', 'confirmed_duplicate', 'false_positive'] as $option) {
    $counts[$option] = (int) db_value(
        'SELECT COUNT(*) FROM match_clusters WHERE match_status = ?', [$option]);
}

$page_title = 'Duplicate review';
$active_nav = 'duplicates';
render('duplicates', ['groups' => $groups, 'status' => $status, 'counts' => $counts]);
