<?php
// zobraz_karty_nano.php * Verze: V3 * Aktualizace: 15.04.2026
declare(strict_types=1);

if (!function_exists('cb_zobraz_karty_nano')) {
    function cb_zobraz_karty_nano(array $kartyNano, int $nanoKde, array $renderContext): string
    {
        if (empty($kartyNano)) {
            return '';
        }

        ob_start();

        if ($nanoKde === 1) {
            foreach (array_chunk($kartyNano, 9) as $nanoSkupina) {
                echo '<div class="dash_nano_group">';
                foreach ($nanoSkupina as $kartaNano) {
                    echo cb_zobraz_kartu((array)$kartaNano, true, '', $renderContext);
                }
                echo '</div>';
            }

            return (string)ob_get_clean();
        }

        foreach ($kartyNano as $kartaNano) {
            echo cb_zobraz_kartu((array)$kartaNano, true, '', $renderContext);
        }

        return (string)ob_get_clean();
    }
}
