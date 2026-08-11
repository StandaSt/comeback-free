<?php
declare(strict_types=1);

function cb_helpdesk_request_dispatch(): void
{
    if (!isset($_GET['helpdesk_action']) && !isset($_POST['helpdesk_action'])) {
        return;
    }

    if (isset($_GET['cb_helpdesk_module']) || isset($_POST['cb_helpdesk_module'])) {
        $sourceModule = strtolower(trim((string)($_GET['cb_helpdesk_module'] ?? $_POST['cb_helpdesk_module'] ?? '')));
        if (in_array($sourceModule, ['provoz', 'hr', 'smeny', 'ukoly'], true)) {
            $_SESSION['cb_helpdesk_source_module'] = $sourceModule;
        }
    }

    $action = trim((string)($_GET['helpdesk_action'] ?? $_POST['helpdesk_action'] ?? ''));
    $routes = [
        'detail' => __DIR__ . '/../hl_ajax/hl_detail.php',
        'notifikace_nacist' => __DIR__ . '/../hl_ajax/hl_notifikace_nacist.php',
        'notifikace_precteno' => __DIR__ . '/../hl_ajax/hl_notifikace_precteno.php',
        'priloha_nahrat' => __DIR__ . '/../hl_ajax/hl_priloha_nahrat.php',
        'sledovat' => __DIR__ . '/../hl_ajax/hl_sledovat.php',
        'stav_tiketu' => __DIR__ . '/../hl_ajax/hl_stav_tiketu.php',
        'stav_zmenit' => __DIR__ . '/../hl_ajax/hl_stav_zmenit.php',
        'vytvorit' => __DIR__ . '/../hl_ajax/hl_vytvorit.php',
        'zprava_pridat' => __DIR__ . '/../hl_ajax/hl_zprava_pridat.php',
    ];

    if (!isset($routes[$action])) {
        http_response_code(404);
        exit;
    }

    if (!defined('CB_HELPDESK_DISPATCH_INTERNAL')) {
        define('CB_HELPDESK_DISPATCH_INTERNAL', true);
    }

    require $routes[$action];
    exit;
}
