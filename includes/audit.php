<?php
declare(strict_types=1);

/** Append an immutable trail entry for every security-relevant action. */
function audit(string $action, string $details, ?int $user_id = null): void
{
    $user_id = $user_id ?? (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);
    db_insert(
        'INSERT INTO audit_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)',
        [$user_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'cli']
    );
}
