<?php
// K6
// karty/nakupni_kosik.php * Verze: V1 * Aktualizace: 22.04.2026
declare(strict_types=1);

$card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0">Zde bude obsah min režimu</p>';

ob_start();
?>
<p class="card_text txt_seda odstup_vnejsi_0">Zde bude max obsah</p>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/nakupni_kosik.php * Verze: V1 * Aktualizace: 22.04.2026 */
?>