<?php
/** Shared page head + top navigation. Expects $page_title and optional $active_nav. */
$active_nav = $active_nav ?? '';
$nav_items = [
    'dashboard' => ['label' => 'Dashboard', 'href' => 'index.php', 'admin' => false],
    'beneficiaries' => ['label' => 'Beneficiaries', 'href' => 'beneficiaries.php', 'admin' => false],
    'new' => ['label' => 'New record', 'href' => 'beneficiary_form.php', 'admin' => false],
    'duplicates' => ['label' => 'Duplicates', 'href' => 'duplicates.php', 'admin' => true],
    'import' => ['label' => 'Import', 'href' => 'import.php', 'admin' => true],
    'reports' => ['label' => 'Reports', 'href' => 'reports.php', 'admin' => false],
    'users' => ['label' => 'Users', 'href' => 'users.php', 'admin' => true],
    'audit' => ['label' => 'Audit log', 'href' => 'audit_log.php', 'admin' => true],
];
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(page_title($page_title ?? '')) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/styles.css')) ?>">
</head>
<body>
<?php if (is_logged_in()): ?>
    <header class="topbar">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark">RP</span><span>RescuePlan Kenya</span>
        </a>
        <button class="nav-toggle" type="button" aria-label="Toggle navigation"
                onclick="document.getElementById('nav').classList.toggle('open')">&#9776;</button>
        <ul class="nav-links" id="nav">
            <?php foreach ($nav_items as $key => $item): ?>
                <?php if ($item['admin'] && current_role() !== ROLE_ADMIN) {
                    continue;
                } ?>
                <li><a class="<?= $active_nav === $key ? 'active' : '' ?>"
                       href="<?= e(url($item['href'])) ?>"><?= e($item['label']) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="nav-user">
            <span class="chip"><?= e(current_name()) ?></span>
            <span class="chip role-<?= e(current_role()) ?>"><?= e(status_label(current_role())) ?></span>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('logout.php')) ?>">Log out</a>
        </div>
    </header>
<?php endif; ?>
<main>
    <?= flash_render() ?>
