<div class="page-head">
    <div>
        <h1>Beneficiary records</h1>
        <p class="muted"><?= (int) $total_records ?> record(s) shown.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= e(url('beneficiary_form.php')) ?>">Register beneficiary</a>
        <a class="btn" href="<?= e(url('export_beneficiaries.php')) ?>">Export CSV</a>
        <?php if (current_role() === ROLE_ADMIN): ?>
            <a class="btn" href="<?= e(url('import.php')) ?>">Batch import</a>
        <?php endif; ?>
    </div>
</div>

<form method="get" class="filterbar">
    <input type="search" name="q" placeholder="Name or national ID" value="<?= e($search) ?>">
    <select name="camp">
        <option value="">All camps</option>
        <?php foreach ($camps as $camp_row): ?>
            <option value="<?= e($camp_row['camp_location']) ?>"
                <?= $camp === $camp_row['camp_location'] ? 'selected' : '' ?>>
                <?= e($camp_row['camp_location']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">All statuses</option>
        <?php foreach (RECORD_STATUSES as $option): ?>
            <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                <?= e(status_label($option)) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Apply</button>
    <?php if ($search !== '' || $camp !== '' || $status !== ''): ?>
        <a class="btn btn-ghost" href="<?= e(url('beneficiaries.php')) ?>">Clear</a>
    <?php endif; ?>
</form>

<section class="panel">
    <?php if (!$records): ?>
        <p class="empty muted">No records match these filters.</p>
    <?php else: ?>
        <table class="data">
            <thead>
            <tr>
                <th>ID</th><th>Full name</th><th>National ID</th><th class="num">Household</th>
                <th>Camp / location</th><th>Phone</th><th>Status</th><th>Registered</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($records as $record): ?>
                <tr class="<?= $record['pending_clusters'] > 0 ? 'row-flagged' : '' ?>">
                    <td><?= (int) $record['beneficiary_id'] ?></td>
                    <td><?= e($record['full_name']) ?>
                        <?php if ($record['pending_clusters'] > 0): ?>
                            <span class="tag warn"><?= (int) $record['pending_clusters'] ?> cluster(s)</span>
                        <?php endif; ?></td>
                    <td><code><?= e($record['national_id']) ?></code></td>
                    <td class="num"><?= (int) $record['household_size'] ?></td>
                    <td><?= e($record['camp_location']) ?></td>
                    <td><?= e($record['phone']) ?></td>
                    <td><span class="status status-<?= e($record['record_status']) ?>">
                        <?= e(status_label($record['record_status'])) ?></span></td>
                    <td class="small muted"><?= e(substr((string) $record['date_registered'], 0, 16)) ?></td>
                    <td class="right">
                        <a class="btn btn-sm"
                           href="<?= e(url('beneficiary_form.php?id=' . (int) $record['beneficiary_id'])) ?>">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
