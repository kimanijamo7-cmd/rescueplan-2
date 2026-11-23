<div class="page-head">
    <h1>System users</h1>
    <div class="page-actions"><a class="btn" href="<?= e(url('index.php')) ?>">Dashboard</a></div>
</div>

<div class="grid-2">
    <section class="panel">
        <h2>Accounts</h2>
        <table class="data">
            <thead><tr><th>Username</th><th>Full name</th><th>Role</th>
                <th class="num">Activity</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($accounts as $account): ?>
                <tr>
                    <td><code><?= e($account['username']) ?></code></td>
                    <td><?= e($account['full_name']) ?></td>
                    <td><span class="tag"><?= e(status_label($account['role'])) ?></span></td>
                    <td class="num"><?= (int) $account['activity'] ?></td>
                    <td>
                        <span class="status <?= $account['is_active'] ? 'status-verified' : 'status-merged_inactive' ?>">
                            <?= $account['is_active'] ? 'active' : 'disabled' ?></span>
                        <?php if ((int) $account['failed_attempts'] > 0): ?>
                            <span class="tag warn"><?= (int) $account['failed_attempts'] ?> failed</span>
                        <?php endif; ?>
                    </td>
                    <td class="right">
                        <?php if ((int) $account['user_id'] !== current_user_id()): ?>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="user_id" value="<?= (int) $account['user_id'] ?>">
                                <button class="btn btn-sm" type="submit">
                                    <?= $account['is_active'] ? 'Disable' : 'Enable' ?></button>
                            </form>
                        <?php else: ?>
                            <span class="muted small">you</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>Create an account</h2>
        <form method="post" class="stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div>
                <label for="full_name">Full names</label>
                <input id="full_name" name="full_name" maxlength="120" required>
            </div>
            <div>
                <label for="username">Username</label>
                <input id="username" name="username" maxlength="60" autocomplete="off" required>
            </div>
            <div>
                <label for="password">Temporary password</label>
                <input id="password" name="password" type="password" minlength="8" required>
            </div>
            <div>
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="field_officer">Field officer</option>
                    <option value="administrator">Administrator</option>
                </select>
            </div>
            <button class="btn btn-primary full" type="submit">Create account</button>
        </form>
        <p class="hint" style="margin-top:1rem">
            Field officers can register and edit beneficiaries. Only administrators can run scans,
            resolve duplicates, import batches, manage users and read the audit trail.
        </p>
    </section>
</div>
