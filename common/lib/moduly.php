<?php
declare(strict_types=1);

function cb_moduly_povolene(): array
{
    return ['provoz', 'hr', 'smeny', 'ukoly', 'helpdesk'];
}

function cb_modul_normalizuj(string $module, string $fallback = 'provoz'): string
{
    $module = strtolower(trim($module));
    return in_array($module, cb_moduly_povolene(), true) ? $module : $fallback;
}

function cb_modul_nacti(string $module): void
{
    $module = cb_modul_normalizuj($module);
    $root = dirname(__DIR__, 2);
    $files = [
        'provoz' => $root . '/provoz/provoz.php',
        'hr' => $root . '/hr/hr.php',
        'smeny' => $root . '/smeny/smeny.php',
        'ukoly' => $root . '/ukoly/ukoly.php',
        'helpdesk' => $root . '/helpdesk/helpdesk.php',
    ];

    $GLOBALS['CURRENT_MODULE'] = $module;
    if (!defined('CB_EMBEDDED_MODULE')) {
        define('CB_EMBEDDED_MODULE', $module);
    }

    require $files[$module];
}
