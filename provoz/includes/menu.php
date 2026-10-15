<?php
// Sestavi leve menu Provozu a skryje polozky, ke kterym uzivatel nema pravo.
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/blok_menu.php';
require_once __DIR__ . '/../lib/ai_analytik_pravidla.php';
require_once __DIR__ . '/../lib/denni_report_prava.php';
require_once __DIR__ . '/../lib/nastaveni_prava.php';
require_once __DIR__ . '/../lib/kontrola_trzeb_pristup.php';

$provozMenuItems = [
    ['page' => 'prehled', 'label' => 'Přehled'],
    ['page' => 'denni_report', 'label' => 'Denní report', 'pravo' => CB_DENNI_REPORT_ZOBRAZIT_PRAVO],
    ['page' => 'archiv_reportu', 'label' => 'Archiv reportů', 'pravo' => 202],
    ['page' => 'kontrola_trzeb', 'label' => 'Kontrola tržeb', 'pravo' => CB_KT_ZOBRAZIT],
    ['page' => 'objednavky', 'label' => 'Objednávky'],
    ['page' => 'prehled_hodin', 'label' => 'Přehled hodin', 'pravo' => 209],
    ['page' => 'ai_analytik', 'label' => 'Chytrý Franta', 'pravo' => CB_AI_ANALYTIK_PRAVO],
    ['page' => 'nastaveni', 'label' => 'Nastavení', 'pravo' => CB_PROVOZ_NASTAVENI_PRAVO],
];

$provozMenu = [];
foreach ($provozMenuItems as $item) {
    $idPravo = (int)($item['pravo'] ?? 0);
    if ($idPravo === CB_KT_ZOBRAZIT && !cb_kt_ma_pravo()) {
        continue;
    }
    if ($idPravo === CB_AI_ANALYTIK_PRAVO && !cb_ai_analytik_ma_pravo()) {
        continue;
    }
    if ($idPravo > 0 && $idPravo !== CB_AI_ANALYTIK_PRAVO
        && (!function_exists('cb_pravo_ma') || !cb_pravo_ma($idPravo))) {
        continue;
    }

    $itemPage = (string)$item['page'];
    $provozMenu[] = [
        'label' => (string)$item['label'],
        'url' => cb_root_url('index.php?m=provoz&page=' . rawurlencode($itemPage)),
        'active' => $cbPage === $itemPage || ($itemPage === 'nastaveni' && $cbPage === 'nastaveni_pobocky')
            || ($itemPage === 'kontrola_trzeb' && $cbPage === 'archiv_kontrol_trzeb'),
    ];
}

cb_render_blok_menu([
    'title' => 'Provoz',
    'aria_label' => 'Menu Provozu',
    'items' => $provozMenu,
]);
