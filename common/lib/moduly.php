<?php
declare(strict_types=1);

function cb_moduly_povolene(): array
{
    return ['provoz', 'hr', 'smeny', 'ukoly', 'helpdesk', 'administrace'];
}

function cb_modul_vstupni_pravo(string $module): int
{
    return [
        'administrace' => 100,
        'provoz' => 200,
        'hr' => 300,
        'smeny' => 400,
        'ukoly' => 500,
        'helpdesk' => 600,
    ][$module] ?? 0;
}

function cb_modul_ma_pristup(string $module): bool
{
    $idPravo = cb_modul_vstupni_pravo(strtolower(trim($module)));
    return $idPravo > 0 && cb_pravo_ma($idPravo);
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
        'administrace' => $root . '/administrace/administrace.php',
    ];

    if (!cb_modul_ma_pristup($module)) {
        http_response_code(403);
        $cbNepovolenyModul = $module;
        require dirname(__DIR__) . '/includes/modul_bez_pristupu.php';
        return;
    }

    $GLOBALS['CURRENT_MODULE'] = $module;
    if (!defined('CB_EMBEDDED_MODULE')) {
        define('CB_EMBEDDED_MODULE', $module);
    }

    if (cb_modul_logovat_otevreni()) {
        require_once __DIR__ . '/uloz_akci.php';
        $page = cb_modul_logovana_stranka($module);
        if ($page !== '') {
            cb_user_akce_zapis([
                'id_user_akce_typ' => 2,
                'modul' => $module,
                'objekt' => 'stranka',
                'vysledek' => 1,
                'zdroj' => 'moduly',
                'detail' => [
                    'module' => $module,
                    'page' => $page,
                ],
            ]);
        } else {
            cb_user_akce_zapis([
                'id_user_akce_typ' => 1,
                'modul' => $module,
                'vysledek' => 1,
                'zdroj' => 'moduly',
                'detail' => [
                    'module' => $module,
                ],
            ]);
        }
    }

    require $files[$module];
}

function cb_modul_logovat_otevreni(): bool
{
    if (empty($_SESSION['login_ok'])) {
        return false;
    }

    if (isset($_SERVER['HTTP_X_COMEBACK_SHELL_MODULE'])) {
        return true;
    }

    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        return false;
    }

    foreach (array_keys($_SERVER) as $key) {
        if (strncmp((string)$key, 'HTTP_X_COMEBACK_', 16) === 0) {
            return false;
        }
    }

    return true;
}

function cb_modul_logovana_stranka(string $module): string
{
    $module = cb_modul_normalizuj($module);
    $keys = match ($module) {
        'administrace' => ['a', 'page'],
        'helpdesk' => ['hd', 'page'],
        default => ['page'],
    };

    foreach ($keys as $key) {
        $value = strtolower(trim((string)($_GET[$key] ?? '')));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}
