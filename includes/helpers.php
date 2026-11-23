<?php
declare(strict_types=1);

/** Escape a value for safe HTML output. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Absolute URL from the app root so the site works from any folder in htdocs. */
function url(string $path = ''): string
{
    $root = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return ($root === '' ? '' : $root) . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function form_value(array $record, string $key, string $default = ''): string
{
    return isset($record[$key]) && $record[$key] !== null ? (string) $record[$key] : $default;
}

function status_label(string $status): string
{
    return str_replace('_', ' ', $status);
}

function csv_download(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $stream = fopen('php://output', 'wb');
    fputcsv($stream, $header);
    foreach ($rows as $row) {
        fputcsv($stream, $row);
    }
    fclose($stream);
    exit;
}

/** Read a trimmed POST field. */
function post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function get_string(string $key): string
{
    return trim((string) ($_GET[$key] ?? ''));
}

function page_title(string $title): string
{
    return $title . ' | ' . APP_NAME;
}
