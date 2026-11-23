<?php
declare(strict_types=1);

/** Report builder: three canned views over the register, stored for re-download. */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

function date_filter(string $date_from, string $date_to): array
{
    $clause = '';
    $params = [];
    if ($date_from !== '') {
        $clause .= ' AND date_registered >= ?';
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $clause .= ' AND date_registered <= ?';
        $params[] = $date_to . ' 23:59:59';
    }
    return [$clause, $params];
}

function build_report(string $report_type, string $camp, string $date_from, string $date_to): array
{
    $camp_clause = $camp !== '' ? ' AND camp_location = ?' : '';
    $camp_params = $camp !== '' ? [$camp] : [];
    [$tail, $tail_params] = date_filter($date_from, $date_to);
    $where = $camp_clause . $tail;
    $args = array_merge($camp_params, $tail_params);

    if ($report_type === 'deduplication_summary') {
        $summary = [
            'Records scanned' => (int) db_value(
                "SELECT COUNT(*) FROM beneficiaries WHERE 1 = 1{$where}", $args),
            'Duplicate clusters confirmed' => (int) db_value(
                "SELECT COUNT(*) FROM match_clusters WHERE match_status = 'confirmed_duplicate'"),
            'Clusters marked false positive' => (int) db_value(
                "SELECT COUNT(*) FROM match_clusters WHERE match_status = 'false_positive'"),
            'Clusters pending review' => (int) db_value(
                "SELECT COUNT(*) FROM match_clusters WHERE match_status = 'pending'"),
            'Beneficiaries merged as duplicates' => (int) db_value(
                "SELECT COUNT(*) FROM beneficiaries WHERE record_status = 'merged_inactive'{$where}", $args),
            'Verified unique beneficiaries' => (int) db_value(
                "SELECT COUNT(*) FROM beneficiaries WHERE record_status = 'verified'{$where}", $args),
        ];
        $rows = array_map(static fn($key, $value) => [$key, $value],
            array_keys($summary), array_values($summary));
        return [$summary, $rows, ['Metric', 'Value']];
    }

    if ($report_type === 'distribution_eligibility') {
        $rows_db = db_query(
            "SELECT camp_location, COUNT(*) AS verified_beneficiaries,
                    SUM(household_size) AS household_members
             FROM beneficiaries WHERE record_status = 'verified'{$where}
             GROUP BY camp_location ORDER BY camp_location", $args);
        $summary = [
            'Camps covered' => count($rows_db),
            'Verified beneficiaries' => array_sum(array_map(
                static fn(array $row) => (int) $row['verified_beneficiaries'], $rows_db)),
            'Total household members to assist' => array_sum(array_map(
                static fn(array $row) => (int) $row['household_members'], $rows_db)),
        ];
        $rows = array_map(static fn(array $row) => [
            $row['camp_location'], $row['verified_beneficiaries'], $row['household_members'],
        ], $rows_db);
        return [$summary, $rows, ['Camp / Location', 'Verified Beneficiaries', 'Household Members']];
    }

    $rows_db = db_query(
        "SELECT b.beneficiary_id, b.full_name, b.national_id, b.camp_location, b.record_status,
                mc.similarity_score, mc.cluster_id
         FROM beneficiaries b
         JOIN cluster_members cm ON cm.beneficiary_id = b.beneficiary_id
         JOIN match_clusters mc ON mc.cluster_id = cm.cluster_id
         WHERE mc.match_status = 'pending'{$where}
         ORDER BY mc.similarity_score DESC", $args);
    $summary = [
        'Unresolved flagged records' => count($rows_db),
        'Clusters awaiting review' => count(array_unique(array_map(
            static fn(array $row) => (int) $row['cluster_id'], $rows_db))),
    ];
    $rows = array_map(static fn(array $row) => [
        $row['beneficiary_id'], $row['full_name'], $row['national_id'], $row['camp_location'],
        $row['record_status'], '#' . $row['cluster_id'] . ' (' . $row['similarity_score'] . ')',
    ], $rows_db);
    return [$summary, $rows, ['ID', 'Full Name', 'National ID', 'Camp / Location',
                              'Status', 'Cluster (score)']];
}

$generated = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $report_type = post_string('report_type');
    if (!isset(REPORT_TYPES[$report_type])) {
        $report_type = 'deduplication_summary';
    }
    $camp = post_string('camp_location');
    $date_from = post_string('date_from');
    $date_to = post_string('date_to');
    [$summary, $rows, $columns] = build_report($report_type, $camp, $date_from, $date_to);

    $report_id = db_insert(
        'INSERT INTO reports (user_id, report_type, camp_location, date_from, date_to, payload_json)
         VALUES (?, ?, ?, ?, ?, ?)',
        [current_user_id(), $report_type, $camp !== '' ? $camp : null,
         $date_from !== '' ? $date_from : null, $date_to !== '' ? $date_to : null,
         json_encode(['summary' => $summary, 'columns' => $columns, 'rows' => $rows])]);

    audit('REPORT_GENERATED', REPORT_TYPES[$report_type] . " (report #{$report_id})"
        . ($camp !== '' ? " for camp '{$camp}'" : ''));

    $generated = [
        'report_id' => $report_id,
        'name' => REPORT_TYPES[$report_type],
        'summary' => $summary,
        'rows' => $rows,
        'columns' => $columns,
        'generated_at' => date('Y-m-d H:i'),
    ];
}

$history = db_query(
    'SELECT r.*, u.full_name AS author FROM reports r
     LEFT JOIN users u ON u.user_id = r.user_id
     ORDER BY r.report_id DESC LIMIT 12');
$camps = db_query('SELECT DISTINCT camp_location FROM beneficiaries ORDER BY camp_location');

$page_title = 'Reports';
$active_nav = 'reports';
render('reports', ['generated' => $generated, 'history' => $history, 'camps' => $camps]);
