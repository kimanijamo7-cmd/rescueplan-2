<?php
declare(strict_types=1);

/** Register a new beneficiary or edit an existing one (?id=...). */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

function validate_record(array $form): array
{
    $errors = [];
    if ($form['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if ($form['national_id'] === '' || !ctype_digit($form['national_id'])
        || strlen($form['national_id']) < 7 || strlen($form['national_id']) > 9) {
        $errors[] = 'National ID must be 7-9 digits.';
    }
    if ($form['household_size'] === '' || !preg_match('/^\d+$/', $form['household_size'])) {
        $errors[] = 'Household size must be a whole number.';
    } elseif ((int) $form['household_size'] < 1) {
        $errors[] = 'Household size must be at least 1.';
    }
    if ($form['camp_location'] === '') {
        $errors[] = 'Camp / location is required.';
    }
    return $errors;
}

$beneficiary_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editing = $beneficiary_id > 0;
$record = ['full_name' => '', 'national_id' => '', 'household_size' => '',
           'camp_location' => '', 'phone' => '', 'notes' => ''];

if ($editing) {
    $stored = db_one('SELECT * FROM beneficiaries WHERE beneficiary_id = ?', [$beneficiary_id]);
    if (!$stored) {
        http_response_code(404);
        $page_title = 'Not found';
        render('error', ['code' => 404, 'message' => 'That beneficiary record does not exist.']);
        exit;
    }
    $record = $stored;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form = [
        'full_name' => post_string('full_name'),
        'national_id' => post_string('national_id'),
        'household_size' => post_string('household_size'),
        'camp_location' => post_string('camp_location'),
        'phone' => post_string('phone'),
        'notes' => post_string('notes'),
    ];
    $errors = validate_record($form);

    if ($editing) {
        $clash = db_one(
            'SELECT beneficiary_id FROM beneficiaries
             WHERE national_id = ? AND beneficiary_id <> ?',
            [$form['national_id'], $beneficiary_id]);
        if ($clash) {
            $errors[] = "That national ID already belongs to record #{$clash['beneficiary_id']}.";
        }
    } elseif ($form['national_id'] !== '' && db_one(
        'SELECT beneficiary_id FROM beneficiaries WHERE national_id = ?', [$form['national_id']])) {
        $errors[] = 'A beneficiary with that national ID already exists. '
            . 'Search records before registering.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash($error, 'error');
        }
        $record = $form;
    } elseif ($editing) {
        $status = isset($_POST['mark_verified']) ? 'verified' : $stored['record_status'];
        db_exec(
            'UPDATE beneficiaries SET full_name = ?, national_id = ?, household_size = ?,
                camp_location = ?, phone = ?, notes = ?, record_status = ?, date_updated = NOW()
             WHERE beneficiary_id = ?',
            [$form['full_name'], $form['national_id'], (int) $form['household_size'],
             $form['camp_location'], $form['phone'], $form['notes'], $status, $beneficiary_id]);
        audit('BENEFICIARY_UPDATED', "Edited beneficiary #{$beneficiary_id} '{$form['full_name']}'");
        flash('Record updated.', 'success');
        redirect('beneficiaries.php');
    } else {
        $new_id = db_insert(
            'INSERT INTO beneficiaries (full_name, national_id, household_size, camp_location,
                phone, record_status, notes, registered_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$form['full_name'], $form['national_id'], (int) $form['household_size'],
             $form['camp_location'], $form['phone'], 'pending_review', $form['notes'], current_user_id()]);
        audit('BENEFICIARY_CREATED', "Registered beneficiary #{$new_id} '{$form['full_name']}'");
        flash('Beneficiary registered. Run a deduplication scan to check for duplicates.', 'success');
        redirect('beneficiaries.php');
    }
}

$page_title = $editing ? 'Edit record' : 'New record';
$active_nav = $editing ? 'beneficiaries' : 'new';
render('beneficiary_form', ['record' => $record, 'editing' => $editing,
                            'beneficiary_id' => $beneficiary_id]);
