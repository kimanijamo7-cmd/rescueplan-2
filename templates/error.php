<div class="center-block">
    <h1>Error <?= (int) $code ?></h1>
    <p class="muted"><?= e($message) ?></p>
    <p><a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Back to the dashboard</a></p>
</div>
