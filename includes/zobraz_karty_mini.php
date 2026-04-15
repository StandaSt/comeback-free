<?php
// priprav_kartu_mini.php * Verze: V3 * Aktualizace: 15.04.2026
declare(strict_types=1);

if (!function_exists('cb_zobraz_karty_mini')) {
    function cb_zobraz_karty_mini(array $kartyMini, int $nanoKde, array $kartyNano, array $renderContext): string
    {
        ob_start();

        if ($nanoKde === 0 && !empty($kartyNano) && !empty($kartyMini)) {
            echo '<div class="dash_break odstup_vnejsi_0 odstup_vnitrni_0" aria-hidden="true"></div>';
        }

        foreach ($kartyMini as $kartaMini) {
            echo cb_zobraz_kartu((array)$kartaMini, false, '', $renderContext);
        }

        return (string)ob_get_clean();
    }
}
