<?php
declare(strict_types=1);
// Otevře Kontrolu reportů s jejím vlastním obdobím a požadovanou pobočkou.

$cbKontrolaPozadovanaPobocka = (int)($_GET['zr_id_pob'] ?? $_POST['zr_id_pob'] ?? 0);
require __DIR__ . '/../bloky/kontrola_reportu.php';
