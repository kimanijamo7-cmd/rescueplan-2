<div class="page-head">
    <h1>Batch import beneficiaries</h1>
    <div class="page-actions"><a class="btn" href="<?= e(url('beneficiaries.php')) ?>">Back to records</a></div>
</div>

<div class="grid-2">
    <section class="panel">
        <h2>Upload a CSV file</h2>
        <form method="post" enctype="multipart/form-data" class="stack">
            <?= csrf_field() ?>
            <div>
                <label for="file">CSV / TSV file</label>
                <input id="file" name="file" type="file" accept=".csv,.txt,.tsv" required>
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Validate and import</button>
                <a class="btn" href="<?= e(url('import_template.php')) ?>">Download template</a>
            </div>
        </form>
        <p class="hint" style="margin-top:1rem">
            Required columns: <code>full_name</code>, <code>national_id</code>,
            <code>household_size</code>, <code>camp_location</code> (optional <code>phone</code>).
            Rows whose national ID already exists in the register are skipped, never overwritten.
        </p>
    </section>

    <section class="panel">
        <h2>Column reference</h2>
        <table class="data">
            <thead><tr><th>Column</th><th>Rule</th></tr></thead>
            <tbody>
            <tr><td><code>full_name</code></td><td>Must not be blank</td></tr>
            <tr><td><code>national_id</code></td><td>Digits only, must not repeat inside the file</td></tr>
            <tr><td><code>household_size</code></td><td>Whole number, 1 or more</td></tr>
            <tr><td><code>camp_location</code></td><td>Must not be blank</td></tr>
            <tr><td><code>phone</code></td><td>Optional</td></tr>
            </tbody>
        </table>
    </section>
</div>

<?php if ($preview): ?>
    <section class="panel">
        <h2>Preview of <?= e($filename) ?> (not imported)</h2>
        <table class="data">
            <thead><tr><th>Row</th><th>full_name</th><th>national_id</th>
                <th>household_size</th><th>camp_location</th><th>phone</th></tr></thead>
            <tbody>
            <?php foreach ($preview as $index => $row): ?>
                <tr>
                    <td><?= $index + 2 ?></td>
                    <td><?= e($row['full_name'] ?? '') ?></td>
                    <td><code><?= e($row['national_id'] ?? '') ?></code></td>
                    <td><?= e($row['household_size'] ?? '') ?></td>
                    <td><?= e($row['camp_location'] ?? '') ?></td>
                    <td><?= e($row['phone'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted small">Fix the highlighted rows in the spreadsheet and upload again.</p>
    </section>
<?php endif; ?>
