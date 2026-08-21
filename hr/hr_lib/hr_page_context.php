<?php
/*
 * Ucel souboru: Nacita deklarovany datovy kontext a flash zpravu pro stranku HR.
 * Nevykresluje HTML, neresi PP layout ani neobsahuje DB dotazy konkretni stranky.
 */
declare(strict_types=1);

/*
 * Zavola poskytovatele dat deklarovaneho u stranky modulu HR.
 */
function hr_page_context(array $pageDefinition, mysqli $db): array
{
    $provider = trim((string)($pageDefinition['context_provider'] ?? ''));
    if ($provider === '') {
        return [];
    }

    $file = trim((string)($pageDefinition['context_file'] ?? ''));
    if ($file === '' || !is_file($file)) {
        throw new RuntimeException('Neplatny datovy poskytovatel stranky HR.');
    }

    require_once $file;
    if (!function_exists($provider)) {
        throw new RuntimeException('Datovy poskytovatel stranky HR neexistuje.');
    }

    $context = $provider($db);
    if (!is_array($context)) {
        throw new RuntimeException('Datovy poskytovatel stranky HR nevratil pole.');
    }

    return $context;
}

/*
 * Prevede dosavadni HR flash typy na obecne typy spolecneho PP rendereru.
 */
function hr_page_flash(?array $flash): ?array
{
    if (!is_array($flash)) {
        return null;
    }

    $text = trim((string)($flash['text'] ?? ''));
    if ($text === '') {
        return null;
    }

    $type = match ((string)($flash['type'] ?? '')) {
        'hr_success' => 'success',
        'hr_error' => 'error',
        default => 'info',
    };

    return [
        'type' => $type,
        'text' => $text,
    ];
}
