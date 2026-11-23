<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-head">
            <div class="brand-mark big">RP</div>
            <h1>Beneficiary Deduplication System</h1>
            <p class="muted small">RescuePlan Kenya &middot; aid distribution integrity</p>
        </div>
        <form method="post" class="stack">
            <?= csrf_field() ?>
            <div>
                <label for="username">Username</label>
                <input id="username" name="username" value="<?= e($username ?? '') ?>"
                       autocomplete="username" required>
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary full" type="submit">Log in</button>
            <p class="small muted">No account yet?
                <a href="<?= e(url('signup.php')) ?>">Register as a field officer</a>.</p>
        </form>
        <p class="hint" style="margin-top:1rem">
            Sessions end automatically after
            <?= (int) SESSION_TIMEOUT_MINUTES ?> minutes of inactivity.
        </p>
    </div>
</div>
