<?php
declare(strict_types=1);

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_role(): string
{
    return (string) ($_SESSION['role'] ?? '');
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_name(): string
{
    return (string) ($_SESSION['full_name'] ?? '');
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('Please log in to access that page.', 'error');
        redirect('login.php');
    }
}

/** Server-side role gate: never trust the navigation shown to the browser. */
function require_admin(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
    if (current_role() !== ROLE_ADMIN) {
        flash('You do not have permission to access that page.', 'error');
        redirect('index.php');
    }
}

function enforce_session_timeout(): void
{
    if (!is_logged_in()) {
        return;
    }
    $now = time();
    $last = $_SESSION['last_activity'] ?? null;
    if ($last && ($now - (int) $last) > SESSION_TIMEOUT_MINUTES * 60) {
        audit('SESSION_TIMEOUT', 'Auto-logout after inactivity');
        $_SESSION = [];
        session_regenerate_id(true);
        flash('Your session expired due to inactivity. Please log in again.', 'error');
        redirect('login.php');
    }
    $_SESSION['last_activity'] = $now;
}
