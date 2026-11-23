<?php
declare(strict_types=1);

/** Replays a stored report payload as a CSV download. */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$report_id = (int) ($_GET['id'] ?? 0);
$report = db_one('SELECT * FROM reports WHERE report_id = ?', [$report_id]);
if (!$report) {
    http_response_code(404);
    exit('Report not found.');
}
if (current_role() !== ROLE_ADMIN && (int) $report['user_id'] !== current_user_id()) {
    http_response_code(403);
    exit('You may only download reports you generated.');
}

$payload = json_decode((string) $report['payload_json'], true) ?? [];
$name = REPORT_TYPES[$report['report_type']] ?? $report['report_type'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=report_' . $report_id . '.csv');
$stream = fopen('php://output', 'wb');
fputcsv($stream, [APP_NAME . ' - ' . $name]);
fputcsv($stream, ['Report ID', $report['report_id'], 'Generated', $report['generated_at']]);
fputcsv($stream, []);
foreach ($payload['summary'] ?? [] as $key => $value) {
    fputcsv($stream, [$key, $value]);
}
fputcsv($stream, []);
if (!empty($payload['columns'])) {
    fputcsv($stream, $payload['columns']);
}
foreach ($payload['rows'] ?? [] as $row) {
    fputcsv($stream, $row);
}
fclose($stream);

audit('REPORT_DOWNLOADED', "Downloaded report #{$report_id}");
exit;
