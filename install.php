<?php
declare(strict_types=1);

/**
 * One-time setup: creates the MySQL database, tables and demo data.
 * Web: open install.php and press the button. CLI: php install.php [--reset]
 * Localhost only, unless ALLOW_HOSTED_INSTALL is true for a shared-hosting install.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$from_localhost = in_array($_SERVER['REMOTE_ADDR'] ?? 'cli', ['127.0.0.1', '::1', 'cli'], true);
if (!$from_localhost && !(defined('ALLOW_HOSTED_INSTALL') && ALLOW_HOSTED_INSTALL)) {
    http_response_code(403);
    exit('install.php runs from the computer hosting XAMPP, or from anywhere while ALLOW_HOSTED_INSTALL is'
        . ' true in includes/config.php. Set it back to false, or delete install.php, as soon as the'
        . ' database has been created.');
}

const ACCOUNTS = [
    ['Kimani James Mugo', 'admin', 'Admin@1234', ROLE_ADMIN],
    ['Alice Achieng Odera', 'officer', 'Officer@1234', ROLE_FIELD],
];

const DEMO_BENEFICIARIES = [
    ['Faith Njeri', '22991234', 4, 'Kisumu IDP Camp', '0712345678'],
    ['Feith Njeri', '22991235', 4, 'Kisumu IDP Camp', '0712345678'],
    ['Peter Wekesa', '55674321', 6, 'Nyamasula Camp', '0722334455'],
    ['Peter Wekessa', '55674322', 6, 'Nyamasula Camp', '0722334455'],
    ['Grace Wanza', '11880021', 3, 'Kisumu IDP Camp', '0733112233'],
    ['Samuel Otieno', '77120934', 5, 'Ngoma Camp', '0700112233'],
    ['Beatrice Chweya', '40912775', 2, 'Nyamasula Camp', '0711987654'],
    ['David Kiplagat', '66203118', 7, 'Dundugua Camp', '0722446688'],
    ['Mary Akinyi', '33190542', 4, 'Ngoma Camp', '0733221100'],
    ['Joseph Omandi', '90217734', 1, 'Kisumu IDP Camp', '0700998877'],
    ['Esther Apio', '51209334', 8, 'Dundugua Camp', '0711223344'],
    ['Thomas Barasa', '28374615', 3, 'Nyamasula Camp', '0722667788'],
];

/** Applies sql/schema.sql, then seeds demo data when the user table is empty. */
function run_installer(bool $reset): array
{
    $log = [];
    $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Could not read sql/schema.sql.');
    }
    // A hosted schema has no CREATE DATABASE line, so the configured database is used directly.
    $server = str_contains($schema, 'CREATE DATABASE') ? db_server() : db();
    $lines = [];
    foreach (explode("\n", $schema) as $line) {
        if (!str_starts_with(ltrim($line), '--')) {
            $lines[] = $line;
        }
    }
    foreach (array_filter(array_map('trim', explode(';', implode("\n", $lines)))) as $statement) {
        if ($statement === '') {
            continue;
        }
        try {
            $server->exec($statement);
        } catch (PDOException $error) {
            // 1050 table exists / 1061 index exists: re-running the installer is safe.
            if (!preg_match('/\b(1050|1061)\b/', $error->getMessage())) {
                throw $error;
            }
        }
    }
    $log[] = 'Database and tables are in place.';

    if ($reset) {
        db_exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['cluster_members', 'match_clusters', 'reports', 'audit_log', 'beneficiaries', 'users'] as $table) {
            db_exec("TRUNCATE TABLE {$table}");
        }
        db_exec('SET FOREIGN_KEY_CHECKS = 1');
        $log[] = 'Existing data was cleared before seeding.';
    }

    $existing = (int) db_value('SELECT COUNT(*) FROM users');
    if ($existing > 0) {
        $log[] = "Users table already had {$existing} account(s); seeding skipped.";
        return $log;
    }

    $admin_id = null;
    foreach (ACCOUNTS as [$full_name, $username, $password, $role]) {
        $user_id = db_insert(
            'INSERT INTO users (full_name, username, password_hash, role) VALUES (?, ?, ?, ?)',
            [$full_name, $username, password_hash($password, PASSWORD_DEFAULT), $role]
        );
        $admin_id = $admin_id ?? $user_id;
    }
    foreach (DEMO_BENEFICIARIES as [$full_name, $national_id, $household, $camp, $phone]) {
        db_insert(
            'INSERT INTO beneficiaries
                (full_name, national_id, household_size, camp_location, phone, record_status, registered_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$full_name, $national_id, $household, $camp, $phone, 'pending_review', $admin_id]
        );
    }
    db_insert(
        "INSERT INTO audit_log (user_id, action, details) VALUES (?, 'SEED_DATA', ?)",
        [$admin_id, 'Demo dataset loaded: 12 beneficiaries, 2 planted near-duplicate pairs']
    );
    $log[] = sprintf('Seeded %d accounts and %d beneficiaries.', count(ACCOUNTS), count(DEMO_BENEFICIARIES));
    return $log;
}

if (PHP_SAPI === 'cli') {
    try {
        foreach (run_installer(in_array('--reset', $argv ?? [], true)) as $line) {
            echo $line, PHP_EOL;
        }
    } catch (Throwable $error) {
        echo 'FAILED: ', $error->getMessage(), PHP_EOL;
        exit(1);
    }
    exit(0);
}

$log = [];
$installed = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'install') {
    csrf_check();
    try {
        $log = run_installer(($_POST['reset'] ?? '') === '1');
        $installed = true;
    } catch (Throwable $error) {
        $log[] = 'FAILED: ' . $error->getMessage();
    }
}

$page_title = 'Install';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(page_title('Install')) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/styles.css')) ?>">
</head>
<body>
<main class="with-nav">
    <div class="page-head"><h1>Setup &middot; Beneficiary Deduplication System</h1></div>
    <?= flash_render() ?>
    <div class="panel narrow">
        <h2>Create database and demo data</h2>
        <p class="muted small">Runs <code>sql/schema.sql</code> against the MySQL server configured in
            <code>includes/config.php</code>, then loads two demo accounts and twelve beneficiary
            records, including the two planted near-duplicate pairs.</p>
        <form method="post" class="stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="install">
            <label class="check"><input type="checkbox" name="reset" value="1">
                Clear existing rows first (re-seed from scratch)</label>
            <button class="btn btn-primary full" type="submit">Run installer</button>
        </form>
        <?php if ($log): ?>
            <ul class="timeline" style="margin-top:1rem">
                <?php foreach ($log as $line): ?>
                    <li><?= e($line) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($installed): ?>
            <p class="hint" style="margin-top:1rem">
                Login as <strong>admin / Admin@1234</strong> (administrator) or
                <strong>officer / Officer@1234</strong> (field officer).
                Delete <code>install.php</code> once the system is deployed.
                <a href="<?= e(url('login.php')) ?>">Go to the login page</a>.
            </p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
