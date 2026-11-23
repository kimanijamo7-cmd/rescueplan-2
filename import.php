<?php
declare(strict_types=1);

/** Batch registration from a CSV file (administrators only). */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

/** Normalised header aliases so partner spreadsheets load without editing them. */
const IMPORT_ALIASES = [
    'name' => 'full_name',
    'nationalid' => 'national_id',
    'id_number' => 'national_id',
    'household' => 'household_size',
    'camp' => 'camp_location',
    'location' => 'camp_location',
];

function read_upload(string $temporary_path, string $original_name): array
{
    $lower = strtolower($original_name);
    if (str_ends_with($lower, '.xlsx') || str_ends_with($lower, '.xlsm')) {
        throw new RuntimeException(
            'Excel files need a PHP spreadsheet extension. Save the sheet as CSV and upload again.');
    }
    if (!str_ends_with($lower, '.csv') && !str_ends_with($lower, '.txt') && !str_ends_with($lower, '.tsv')) {
        throw new RuntimeException('Unsupported file type. Use .csv or .tsv.');
    }

    $raw = file_get_contents($temporary_path);
    if ($raw === false || $raw === '') {
        throw new RuntimeException('The upload could not be read. Please try again.');
    }
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $first_line = strtok($raw, "\n") ?: '';
    $delimiter = str_contains($first_line, "\t") ? "\t" : ',';

    $handle = fopen('php://memory', 'r+b');
    fwrite($handle, $raw);
    rewind($handle);
    $table = [];
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
        $table[] = array_map(static fn($cell) => trim((string) ($cell ?? '')), $row);
    }
    fclose($handle);

    $table = array_values(array_filter($table, static fn(array $row) =>
        implode('', $row) !== ''));
    if (!$table) {
        throw new RuntimeException('The file is empty.');
    }

    $header = array_map(static function (string $cell) {
        $key = strtolower(str_replace([' ', '-'], '_', $cell));
        if (isset(IMPORT_ALIASES[$key])) {
            return IMPORT_ALIASES[$key];
        }
        $compact = str_replace('_', '', $key);
        return IMPORT_ALIASES[$compact] ?? $key;
    }, array_shift($table));

    $required = ['full_name', 'national_id', 'household_size', 'camp_location'];
    foreach ($required as $column) {
        if (!in_array($column, $header, true)) {
            throw new RuntimeException(
                'Header must include: full_name, national_id, household_size, camp_location.');
        }
    }

    $rows = [];
    foreach ($table as $values) {
        $values = array_pad($values, count($header), '');
        $rows[] = array_combine($header, $values);
    }
    return $rows;
}

function validate_import_rows(array $rows): array
{
    $errors = [];
    $seen = [];
    foreach ($rows as $index => $row) {
        $number = $index + 2;
        if (($row['full_name'] ?? '') === '') {
            $errors[] = "Row {$number}: full_name is blank.";
        }
        $nid = $row['national_id'] ?? '';
        if ($nid === '' || !ctype_digit($nid)) {
            $errors[] = "Row {$number}: national_id '{$nid}' is not numeric.";
        }
        $household = $row['household_size'] ?? '';
        if ($household === '' || !preg_match('/^\d+$/', $household)) {
            $errors[] = "Row {$number}: household_size '{$household}' is not a number.";
        } elseif ((int) $household < 1) {
            $errors[] = "Row {$number}: household_size must be 1 or more.";
        }
        if (($row['camp_location'] ?? '') === '') {
            $errors[] = "Row {$number}: camp_location is blank.";
        }
        if (isset($seen[$nid])) {
            $errors[] = "Row {$number}: national_id '{$nid}' repeats inside this file.";
        }
        $seen[$nid] = true;
    }
    return $errors;
}

$filename = '';
$preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE || !is_uploaded_file((string) $file['tmp_name'])) {
        flash('Choose a CSV file to upload.', 'error');
    } else {
        $filename = (string) $file['name'];
        try {
            $rows = read_upload((string) $file['tmp_name'], $filename);
        } catch (RuntimeException $error) {
            flash($error->getMessage(), 'error');
            $rows = null;
        }
        if ($rows !== null) {
            $errors = validate_import_rows($rows);
            if ($errors) {
                foreach (array_slice($errors, 0, 10) as $error) {
                    flash($error, 'error');
                }
                $preview = $rows;
            } else {
                $inserted = $skipped = 0;
                foreach ($rows as $row) {
                    if (db_one('SELECT beneficiary_id FROM beneficiaries WHERE national_id = ?',
                        [$row['national_id']])) {
                        $skipped++;
                        continue;
                    }
                    db_insert(
                        'INSERT INTO beneficiaries (full_name, national_id, household_size,
                            camp_location, phone, record_status, registered_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$row['full_name'], $row['national_id'], (int) $row['household_size'],
                         $row['camp_location'], $row['phone'] ?? '', 'pending_review', current_user_id()]);
                    $inserted++;
                }
                audit('BATCH_IMPORT', "Imported {$inserted} record(s) from '{$filename}'; "
                    . "{$skipped} skipped, " . count($rows) . ' parsed');
                flash("Imported {$inserted} record(s); {$skipped} skipped as existing national IDs.", 'success');
                redirect('beneficiaries.php');
            }
        }
    }
}

$page_title = 'Batch import';
$active_nav = 'import';
render('import', ['preview' => $preview, 'filename' => $filename]);
