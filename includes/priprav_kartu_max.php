<?php
// priprav_kartu_max.php * Verze: V1 * Aktualizace: 15.04.2026
declare(strict_types=1);

if (!function_exists('cb_zobraz_karty_max')) {
    function cb_zobraz_karty_max(string $cardMaxHtml): string
    {
        ob_start();
        ?>
          <div class="card_max card_expanded odstup_vnitrni_10 is-hidden" data-card-expanded>
            <?= $cardMaxHtml ?>
          </div>
        <?php
        return (string)ob_get_clean();
    }
}
