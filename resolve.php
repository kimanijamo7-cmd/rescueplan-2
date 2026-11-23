<?php
declare(strict_types=1);

/** Records the administrator's decision on one candidate cluster. */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('duplicates.php');
}
csrf_check();

$cluster_id = (int) ($_POST['cluster_id'] ?? 0);
$cluster = db_one('SELECT * FROM match_clusters WHERE cluster_id = ?', [$cluster_id]);
if (!$cluster || $cluster['match_status'] !== 'pending') {
    http_response_code(404);
    $page_title = 'Not found';
    render('error', ['code' => 404, 'message' => 'That cluster is missing or already resolved.']);
    exit;
}

$decision = post_string('decision');
$keep_id = isset($_POST['keep_beneficiary_id']) ? (int) $_POST['keep_beneficiary_id'] : 0;
$member_ids = array_map(
    static fn(array $row) => (int) $row['beneficiary_id'],
    db_query('SELECT beneficiary_id FROM cluster_members WHERE cluster_id = ?', [$cluster_id]));

if ($decision === 'confirmed_duplicate') {
    if (!in_array($keep_id, $member_ids, true)) {
        flash('Select which record should be kept.', 'error');
        redirect('duplicates.php');
    }
    foreach ($member_ids as $member_id) {
        $status = $member_id === $keep_id ? 'verified' : 'merged_inactive';
        db_exec('UPDATE beneficiaries SET record_status = ?, date_updated = NOW()
                 WHERE beneficiary_id = ?', [$status, $member_id]);
    }
    audit('CLUSTER_CONFIRMED', "Cluster #{$cluster_id}: kept #{$keep_id}, merged/deactivated "
        . (count($member_ids) - 1) . ' record(s)');
    flash('Duplicate confirmed. The other record(s) were merged and deactivated.', 'success');
} elseif ($decision === 'false_positive') {
    foreach ($member_ids as $member_id) {
        db_exec("UPDATE beneficiaries SET record_status = 'verified', date_updated = NOW()
                 WHERE beneficiary_id = ?", [$member_id]);
    }
    audit('CLUSTER_FALSE_POSITIVE', "Cluster #{$cluster_id}: all members re-verified");
    flash('Marked as false positive. All records re-verified.', 'success');
} else {
    flash('Choose a resolution decision.', 'error');
    redirect('duplicates.php');
}

db_exec('UPDATE match_clusters SET match_status = ?, reviewed_by = ?, date_reviewed = NOW()
         WHERE cluster_id = ?', [$decision, current_user_id(), $cluster_id]);
redirect('duplicates.php');
