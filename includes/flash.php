<?php
declare(strict_types=1);

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flashes'][] = ['message' => $message, 'type' => $type];
}

/** Pulls and renders queued messages; call once inside the layout. */
function flash_render(): string
{
    $messages = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    if (!$messages) {
        return '';
    }
    $html = '<div class="flashes">';
    foreach ($messages as $message) {
        $class = $message['type'] === 'error' ? 'flash-error' : 'flash-success';
        $html .= '<div class="flash ' . $class . '">' . e($message['message']) . '</div>';
    }
    return $html . '</div>';
}
