<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-head">
            <div class="brand-mark big">RP</div>
            <h1>Create a field officer account</h1>
            <p class="muted small">Administrator accounts are issued by an existing administrator.</p>
        </div>
        <form method="post" class="stack">
            <?= csrf_field() ?>
            <div>
                <label for="full_name">Full names</label>
                <input id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" required>
            </div>
            <div>
                <label for="username">Username</label>
                <input id="username" name="username" value="<?= e($form['username']) ?>"
                       autocomplete="username" required>
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required>
            </div>
            <div>
                <label for="confirm_password">Confirm password</label>
                <input id="confirm_password" name="confirm_password" type="password"
                       autocomplete="new-password" required>
            </div>
            <button class="btn btn-primary full" type="submit">Register</button>
            <p class="small muted">Already registered?
                <a href="<?= e(url('login.php')) ?>">Log in instead</a>.</p>
        </form>
    </div>
</div>
