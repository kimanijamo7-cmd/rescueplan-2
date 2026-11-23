<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/dedup.php';

/**
 * Shared hosting turns an uncaught database exception into a blank page, while printing
 * the raw message would name the database host and account. Render one clear page instead.
 */
set_exception_handler(function (Throwable $error): void {
    if (headers_sent()) {
        return;
    }
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $is_database = $error instanceof PDOException || $error instanceof SQLException;
    $advice = $is_database
        ? 'RescuePlan Kenya cannot reach its MySQL database. Compare DB_HOST, DB_NAME, DB_USER and DB_PASS in'
            . ' <code>includes/config.php</code> with the values shown in your hosting control panel, and confirm'
            . ' the schema has been imported into that database. Free hosts such as InfinityFree do not accept'
            . ' database connections from outside their own servers, so the site has to be hosted where the'
            . ' database lives.'
        : 'RescuePlan Kenya stopped while handling this request.';
    $detail = (defined('SHOW_ERROR_DETAIL') && SHOW_ERROR_DETAIL)
        ? '<p class="d">' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>'
        : '';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Database unavailable</title>'
        . '<style>body{margin:0;padding:48px 24px;background:#0D2B22;color:#CFE3D8;'
        . 'font:15px/1.6 "Segoe UI",Arial,sans-serif}main{max-width:720px;margin:0 auto}'
        . 'h1{font-size:24px;color:#fff;margin:0 0 12px}code{background:#0F3329;padding:1px 5px;border-radius:4px}'
        . '.d{margin-top:18px;padding:14px;background:#0F3329;border-left:3px solid #4FAF85;'
        . 'font:13px/1.5 Consolas,monospace;white-space:pre-wrap;color:#BFE3CF}</style></head>'
        . '<body><main><h1>Database unavailable</h1><p>' . $advice . '</p>' . $detail
        . '<p><a href="login.php" style="color:#4FAF85">Back to the login page</a></p></main></body></html>';
});

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('RESCUEPLAN_SESSION');
    session_start();
}

start_session();
enforce_session_timeout();

/** Renders a page inside the shared layout. */
function render(string $template, array $data = []): void
{
    $page_title = $GLOBALS['page_title'] ?? '';
    $active_nav = $GLOBALS['active_nav'] ?? '';
    extract($data, EXTR_SKIP);
    require __DIR__ . '/../templates/header.php';
    require __DIR__ . '/../templates/' . $template . '.php';
    require __DIR__ . '/../templates/footer.php';
}
