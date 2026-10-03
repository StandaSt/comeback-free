<?php
declare(strict_types=1);
/* Účel: Sestaví menu směn a funkční odkazy na aktuální týdny; historie má vlastní stránku. */

require_once __DIR__ . '/../../common/includes/blok_menu.php';

$smMenuItems = $smMenuItems ?? [
    ['page' => 'prehled', 'label' => 'Přehled'],
];
$smPage = $smPage ?? 'prehled';
$smMenu = [];
foreach ($smMenuItems as $item) {
    $itemPage = (string)$item['page'];
    $smMenuItem = [
        'label' => (string)$item['label'],
        'url' => cb_root_url('index.php?m=smeny&page=' . rawurlencode($itemPage)),
        'active' => $smPage === $itemPage,
    ];
    if (isset($item['items']) && is_array($item['items'])) {
        $smMenuItem['items'] = $item['items'];
        if (in_array($itemPage, ['me_smeny','naplanovane_smeny','zadane_pozadavky'], true)) {
            // Indexy odpovídají aktuálnímu týdnu a následujícím týdnům veřejného přehledu.
            $smMenuItem['items'] = [];
            foreach ($item['items'] as $index => $label) {
                $smMenuItem['items'][] = ['label'=>$label,'url'=>cb_root_url('index.php?m=smeny&page='.$itemPage.'&week='.$index)];
            }
        }
    }
    $smMenu[] = $smMenuItem;
}

cb_render_blok_menu([
    'title' => 'Směny',
    'aria_label' => 'Menu směn',
    'items' => $smMenu,
]);
