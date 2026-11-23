<?php
declare(strict_types=1);

/** Runs the three-stage pipeline and stores new pending clusters. */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('duplicates.php');
}
csrf_check();

$records = db_query("SELECT * FROM beneficiaries WHERE record_status <> 'merged_inactive'");
$clusters = run_pipeline($records);

$existing = [];
foreach (db_query(
    "SELECT GROUP_CONCAT(cm.beneficiary_id) AS ids
     FROM match_clusters mc
     JOIN cluster_members cm ON cm.cluster_id = mc.cluster_id
     WHERE mc.match_status = 'pending'
     GROUP BY mc.cluster_id") as $row) {
    $ids = array_map('intval', explode(',', (string) $row['ids']));
    sort($ids);
    $existing[implode('-', $ids)] = true;
}

$created = 0;
foreach ($clusters as $cluster) {
    $key = implode('-', $cluster['beneficiary_ids']);
    if (isset($existing[$key])) {
        continue;
    }
    $cluster_id = db_insert('INSERT INTO match_clusters (similarity_score) VALUES (?)',
        [$cluster['similarity_score']]);
    foreach ($cluster['beneficiary_ids'] as $member_id) {
        db_insert('INSERT INTO cluster_members (cluster_id, beneficiary_id) VALUES (?, ?)',
            [$cluster_id, $member_id]);
        db_exec("UPDATE beneficiaries SET record_status = 'duplicate_flagged'
                 WHERE beneficiary_id = ? AND record_status <> 'merged_inactive'", [$member_id]);
    }
    $created++;
}

audit('DEDUP_SCAN_RUN', count($records) . ' records scanned, ' . count($clusters)
    . ' cluster(s) detected, ' . $created . ' new cluster(s) created (threshold '
    . SIMILARITY_THRESHOLD . ')');
flash('Deduplication scan complete: ' . count($records) . ' records scanned, '
    . $created . ' new duplicate cluster(s) found.', 'success');
redirect('duplicates.php');
