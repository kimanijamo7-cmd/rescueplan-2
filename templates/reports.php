<div class="page-head">
    <h1>Reports</h1>
    <div class="page-actions">
        <a class="btn" href="<?= e(url('beneficiaries.php')) ?>">Back to records</a>
    </div>
</div>

<section class="panel">
    <h2>Generate a report</h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div>
            <label for="report_type">Report</label>
            <select id="report_type" name="report_type">
                <?php foreach (REPORT_TYPES as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="camp_location">Camp / location</label>
            <select id="camp_location" name="camp_location">
                <option value="">All camps</option>
                <?php foreach ($camps as $camp_row): ?>
                    <option value="<?= e($camp_row['camp_location']) ?>">
                        <?= e($camp_row['camp_location']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="date_from">Registered from</label>
            <input id="date_from" name="date_from" type="date">
        </div>
        <div>
            <label for="date_to">Registered to</label>
            <input id="date_to" name="date_to" type="date">
        </div>
        <div class="span-2 actions">
            <button class="btn btn-primary" type="submit">Generate</button>
        </div>
    </form>
</section>

<?php if ($generated): ?>
    <section class="panel">
        <h2><?= e($generated['name']) ?> &middot; report #<?= (int) $generated['report_id'] ?></h2>
        <p class="small muted">Generated <?= e($generated['generated_at']) ?>.</p>
        <table class="data">
            <thead><tr><th>Metric</th><th class="num">Value</th></tr></thead>
            <tbody>
            <?php foreach ($generated['summary'] as $key => $value): ?>
                <tr><td><?= e($key) ?></td><td class="num"><?= e((string) $value) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($generated['rows']): ?>
            <table class="data" style="margin-top:1rem">
                <thead><tr>
                    <?php foreach ($generated['columns'] as $column): ?>
                        <th><?= e($column) ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($generated['rows'] as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?= e((string) $cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted small">No rows matched these filters.</p>
        <?php endif; ?>
        <div class="resolve-bar">
            <a class="btn btn-primary"
               href="<?= e(url('report_download.php?id=' . (int) $generated['report_id'])) ?>">Download CSV</a>
        </div>
    </section>
<?php endif; ?>

<section class="panel">
    <h2>Recent reports</h2>
    <?php if (!$history): ?>
        <p class="empty muted">No reports have been generated yet.</p>
    <?php else: ?>
        <table class="data">
            <thead><tr><th>#</th><th>Type</th><th>Camp</th><th>Period</th>
                <th>Author</th><th>Generated</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($history as $report): ?>
                <tr>
                    <td><?= (int) $report['report_id'] ?></td>
                    <td><?= e(REPORT_TYPES[$report['report_type']] ?? $report['report_type']) ?></td>
                    <td><?= e($report['camp_location'] ?: 'All camps') ?></td>
                    <td class="small muted"><?= e($report['date_from'] ?: 'start') ?>
                        &rarr; <?= e($report['date_to'] ?: 'today') ?></td>
                    <td><?= e($report['author'] ?: 'Unknown') ?></td>
                    <td class="small muted"><?= e($report['generated_at']) ?></td>
                    <td class="right">
                        <?php if (current_role() === ROLE_ADMIN
                                  || (int) $report['user_id'] === current_user_id()): ?>
                            <a class="btn btn-sm"
                               href="<?= e(url('report_download.php?id=' . (int) $report['report_id'])) ?>">CSV</a>
                        <?php else: ?>
                            <span class="muted small">own report only</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
