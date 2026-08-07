<?php
declare(strict_types=1);
?>
<div class="provoz_prehled_grid" aria-label="Přehled Provozu">
    <div class="provoz_prehled_cell"><?php require __DIR__ . '/../includes/objednavky_online.php'; ?></div>
    <div class="provoz_prehled_cell"><?php require __DIR__ . '/../includes/denni_report.php'; ?></div>
    <div class="provoz_prehled_cell"><?php require __DIR__ . '/../includes/top_report.php'; ?></div>
    <div class="provoz_prehled_cell"><?php require __DIR__ . '/../includes/users_online.php'; ?></div>
</div>
