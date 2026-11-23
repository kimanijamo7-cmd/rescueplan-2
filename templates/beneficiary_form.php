<div class="page-head">
    <h1><?= $editing ? 'Edit beneficiary #' . (int) $beneficiary_id : 'Register a beneficiary' ?></h1>
    <div class="page-actions"><a class="btn" href="<?= e(url('beneficiaries.php')) ?>">Back to records</a></div>
</div>

<section class="panel narrow">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div>
            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" maxlength="120" required
                   value="<?= e($record['full_name'] ?? '') ?>">
        </div>
        <div>
            <label for="national_id">National ID / pass number</label>
            <input id="national_id" name="national_id" required
                   value="<?= e($record['national_id'] ?? '') ?>">
        </div>
        <div>
            <label for="household_size">Household size</label>
            <input id="household_size" name="household_size" type="number" min="1" step="1" required
                   value="<?= e($record['household_size'] ?? '') ?>">
        </div>
        <div>
            <label for="camp_location">Camp / location</label>
            <input id="camp_location" name="camp_location" maxlength="120" required
                   value="<?= e($record['camp_location'] ?? '') ?>">
        </div>
        <div>
            <label for="phone">Phone (optional)</label>
            <input id="phone" name="phone" maxlength="30" value="<?= e($record['phone'] ?? '') ?>">
        </div>
        <?php if ($editing): ?>
            <div>
                <label>Current status</label>
                <p class="small"><span class="status status-<?= e($record['record_status'] ?? '') ?>">
                    <?= e(status_label($record['record_status'] ?? '')) ?></span></p>
            </div>
        <?php endif; ?>
        <div class="span-2">
            <label for="notes">Notes (optional)</label>
            <textarea id="notes" name="notes" rows="3"><?= e($record['notes'] ?? '') ?></textarea>
        </div>
        <?php if ($editing): ?>
            <label class="check span-2">
                <input type="checkbox" name="mark_verified" value="1">
                Mark this record as verified once the details are confirmed
            </label>
        <?php endif; ?>
        <div class="span-2 actions">
            <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Register beneficiary' ?></button>
            <a class="btn" href="<?= e(url('beneficiaries.php')) ?>">Cancel</a>
        </div>
    </form>
    <p class="hint" style="margin-top:1rem">
        New records are saved as <em>pending review</em>. A national ID can only exist once in the
        register; near-miss spelling variants are caught later by the deduplication scan.
    </p>
</section>
