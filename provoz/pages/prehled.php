<?php
declare(strict_types=1);
?>
<div class="provoz_prehled_grid" aria-label="Přehled Provozu">
    <div class="provoz_prehled_cell" data-pp-block="objednavky_online"><?php require __DIR__ . '/../bloky/objednavky_online.php'; ?></div>
    <div class="provoz_prehled_cell" data-pp-block="denni_report_prehled"><?php require __DIR__ . '/../bloky/denni_report_prehled.php'; ?></div>
    <div class="provoz_prehled_cell" data-pp-block="top_report" data-gn="1"><?php require __DIR__ . '/../bloky/top_report.php'; ?></div>
    <div class="provoz_prehled_cell" data-pp-block="uzivatele_online"><?php require __DIR__ . '/../bloky/uzivatele_online.php'; ?></div>
</div>
