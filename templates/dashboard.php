<div class="page-head">
    <div>
        <h1>Distribution integrity dashboard</h1>
        <p class="muted">Signed in as <?= e(current_name()) ?>
            (<?= e(status_label(current_role())) ?>).</p>
    </div>
    <div class="page-actions">
        <a class="btn" href="<?= e(url('beneficiaries.php')) ?>">Browse records</a>
        <a class="btn btn-primary" href="<?= e(url('beneficiary_form.php')) ?>">Register beneficiary</a>
    </div>
</div>

<div class="cards">
    <div class="card"><span class="card-num"><?= (int) ($counts['total'] ?? 0) ?></span>
        <span class="card-label">Total records</span></div>
    <div class="card ok"><span class="card-num"><?= (int) ($counts['verified'] ?? 0) ?></span>
        <span class="card-label">Verified unique</span></div>
    <div class="card danger"><span class="card-num"><?= (int) ($counts['flagged'] ?? 0) ?></span>
        <span class="card-label">Duplicate flagged</span></div>
    <div class="card warn"><span class="card-num"><?= (int) ($counts['pending_review'] ?? 0) ?></span>
        <span class="card-label">Pending review</span></div>
    <div class="card"><span class="card-num"><?= (int) ($counts['merged'] ?? 0) ?></span>
        <span class="card-label">Merged / inactive</span></div>
    <div class="card warn"><span class="card-num"><?= (int) $stats['pending_clusters'] ?></span>
        <span class="card-label">Clusters to review</span></div>
    <div class="card ok"><span class="card-num"><?= (int) $stats['household_members'] ?></span>
        <span class="card-label">Household members covered</span></div>
    <div class="card"><span class="card-num"><?= (int) $stats['users'] ?></span>
        <span class="card-label">System users</span></div>
</div>

<div class="grid-2">
    <section class="panel">
        <h2>Coverage by camp</h2>
        <?php if (!$camps): ?>
            <p class="empty muted">No beneficiary records yet.</p>
        <?php else: ?>
            <table class="data">
                <thead><tr><th>Camp / location</th><th class="num">Records</th>
                    <th class="num">Verified</th></tr></thead>
                <tbody>
                <?php foreach ($camps as $camp): ?>
                    <tr>
                        <td><?= e($camp['camp_location']) ?></td>
                        <td class="num"><?= (int) $camp['records'] ?></td>
                        <td class="num"><?= (int) $camp['verified'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Recent activity</h2>
        <?php if (!$recent): ?>
            <p class="empty muted">Nothing logged yet.</p>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($recent as $entry): ?>
                    <li>
                        <strong><?= e(status_label(strtolower($entry['action']))) ?></strong>
                        <span class="muted small"><?= e($entry['logged_at']) ?></span>
                        <div class="small"><?= e($entry['details']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (current_role() === ROLE_ADMIN): ?>
                <p class="small" style="margin-top:.8rem">
                    <a href="<?= e(url('audit_log.php')) ?>">Open the full audit trail</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
