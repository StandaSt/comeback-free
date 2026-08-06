<?php
declare(strict_types=1);
?>
<table class="prehled_table" aria-label="Přehled Provozu">
    <tbody>
        <tr>
            <td class="prehled_cell"><?php require __DIR__ . '/../includes/objednavky_online.php'; ?></td>
            <td class="prehled_cell"><?php require __DIR__ . '/../includes/denni_report.php'; ?></td>
        </tr>
        <tr>
            <td class="prehled_cell"><?php require __DIR__ . '/../includes/top_report.php'; ?></td>
            <td class="prehled_cell"><?php require __DIR__ . '/../includes/users_online.php'; ?></td>
        </tr>
    </tbody>
</table>
