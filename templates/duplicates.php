<div class="page-head">
    <div>
        <h1>Duplicate review</h1>
        <p class="muted">Candidate matches scored with Jaro-Winkler similarity
            (threshold <?= e((string) SIMILARITY_THRESHOLD) ?>).</p>
    </div>
    <div class="page-actions">
        <form method="post" action="<?= e(url('dedup_run.php')) ?>" class="inline">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">Run deduplication scan</button>
        </form>
    </div>
</div>

<div class="tabs">
    <?php foreach (['pending' => 'Pending review', 'confirmed_duplicate' => 'Confirmed duplicates',
                    'false_positive' => 'False positives'] as $key => $label): ?>
        <a class="tab <?= $status === $key ? 'active' : '' ?>"
           href="<?= e(url('duplicates.php?status=' . $key)) ?>">
            <?= e($label) ?> (<?= (int) $counts[$key] ?>)</a>
    <?php endforeach; ?>
</div>

<?php if (!$groups): ?>
    <section class="panel empty">
        <p class="muted">Nothing in this queue. Run a scan to detect new candidate matches.</p>
    </section>
<?php endif; ?>

<?php foreach ($groups as $group): ?>
    <?php $cluster = $group['cluster']; ?>
    <section class="panel cluster">
        <h2>Cluster #<?= (int) $cluster['cluster_id'] ?>
            <span class="score">similarity <?= e($cluster['similarity_score']) ?></span>
            <span class="status status-<?= e($cluster['match_status']) ?>">
                <?= e(status_label($cluster['match_status'])) ?></span>
        </h2>
        <?php if ($cluster['match_status'] !== 'pending'): ?>
            <p class="small muted">Reviewed by <?= e($cluster['reviewer'] ?: 'an unknown user') ?>
                on <?= e($cluster['date_reviewed']) ?>.</p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('resolve.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="cluster_id" value="<?= (int) $cluster['cluster_id'] ?>">
            <table class="data">
                <thead><tr>
                    <th></th><th>ID</th><th>Full name</th><th>National ID</th>
                    <th class="num">Household</th><th>Camp / location</th><th>Status</th><th>Registered</th>
                </tr></thead>
                <tbody>
                <?php foreach ($group['members'] as $member): ?>
                    <tr>
                        <td>
                            <?php if ($cluster['match_status'] === 'pending'): ?>
                                <input type="radio" name="keep_beneficiary_id"
                                       value="<?= (int) $member['beneficiary_id'] ?>"
                                       <?= $member === $group['members'][0] ? 'checked' : '' ?>>
                            <?php else: ?>
                                <span class="muted">&middot;</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $member['beneficiary_id'] ?></td>
                        <td><?= e($member['full_name']) ?></td>
                        <td><code><?= e($member['national_id']) ?></code></td>
                        <td class="num"><?= (int) $member['household_size'] ?></td>
                        <td><?= e($member['camp_location']) ?></td>
                        <td><span class="status status-<?= e($member['record_status']) ?>">
                            <?= e(status_label($member['record_status'])) ?></span></td>
                        <td class="small muted"><?= e(substr((string) $member['date_registered'], 0, 16)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($cluster['match_status'] === 'pending'): ?>
                <div class="resolve-bar">
                    <span class="small muted">Keep the selected record verified and merge the rest:</span>
                    <button class="btn btn-danger btn-sm" type="submit" name="decision"
                            value="confirmed_duplicate">Confirm duplicate</button>
                    <button class="btn btn-sm" type="submit" name="decision"
                            value="false_positive">Mark as false positive</button>
                </div>
            <?php endif; ?>
        </form>
    </section>
<?php endforeach; ?>
