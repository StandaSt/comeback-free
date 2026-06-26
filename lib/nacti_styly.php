<?php
// lib/nacti_styly.php * Verze: V6 * Aktualizace: 09.03.2026
// Pocet radku: 51
// Predchozi pocet radku: 51

/*
 * Nacteni CSS stylu do <head>
 *
 *
 * Zavislosti:
 * - cb_url() (sestaveni URL cesty)
 * - h() (HTML escape)
 */

declare(strict_types=1);

if (!function_exists('cb_asset_url')) {
    function cb_asset_url(string $path): string
    {
        $full = __DIR__ . '/../' . ltrim($path, '/');
        $ver = is_file($full) ? (string)filemtime($full) : '1';
        return cb_url($path) . '?v=' . $ver;
    }
}

?>
<!-- styly -->
<link rel="stylesheet" href="<?= h(cb_asset_url('style/1/global.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_asset_url('style/1/modal_alert.css')) ?>">



<?php

/* lib/nacti_styly.php * Verze: V6 * Aktualizace: 09.03.2026 * Pocet radku: 51
   konec souboru */
?>
