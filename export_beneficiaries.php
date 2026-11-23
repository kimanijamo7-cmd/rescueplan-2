<?php
declare(strict_types=1);

/** Download the whole register as CSV for offline verification. */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$rows = db_query('SELECT * FROM beneficiaries ORDER BY beneficiary_id');
csv_download('beneficiaries_export.csv',
    ['beneficiary_id', 'full_name', 'national_id', 'household_size', 'camp_location',
     'phone', 'record_status', 'date_registered'],
    array_map(static fn(array $row) => [
        $row['beneficiary_id'], $row['full_name'], $row['national_id'],
        $row['household_size'], $row['camp_location'], $row['phone'],
        $row['record_status'], $row['date_registered'],
    ], $rows));
