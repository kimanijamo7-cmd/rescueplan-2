<div class="page-head">
    <h1>Audit trail</h1>
    <div class="page-actions">
        <a class="btn" href="<?= e(url('audit_export.php')) ?>">Export CSV</a>
        <a class="btn" href="<?= e(url('index.php')) ?>">Dashboard</a>
    </div>
</div>

<form method="get" class="filterbar">
    <select name="action">
        <option value="">All actions</option>
        <?php foreach ($actions as $row): ?>
            <option value="<?= e($row['action']) ?>" <?= $action === $row['action'] ? 'selected' : '' ?>>
                <?= e($row['action']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filter</button>
    <?php if ($action !== ''): ?>
        <a class="btn btn-ghost" href="<?= e(url('audit_log.php')) ?>">Clear</a>
    <?php endif; ?>
    <span class="muted small"><?= count($entries) ?> entr(ies) shown, newest first.</span>
</form>

<section class="panel">
    <?php if (!$entries): ?>
        <p class="empty muted">Nothing has been logged yet.</p>
    <?php else: ?>
        <table class="data">
            <thead><tr><th>#</th><th>When</th><th>User</th><th>Role</th>
                <th>Action</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><?= (int) $entry['log_id'] ?></td>
                    <td class="small muted"><?= e($entry['logged_at']) ?></td>
                    <td><?= e($entry['username'] ?: 'anonymous') ?></td>
                    <td class="small"><?= e($entry['role'] ? status_label($entry['role']) : ' guest') ?></td>
                    <td><span class="tag"><?= e($entry['action']) ?></span></td>
                    <td class="small"><?= e($entry['details']) ?></td>
                    <td class="small muted"><?= e($entry['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
