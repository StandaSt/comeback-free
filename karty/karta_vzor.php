<?php
// karty/admin_priprava_init.php * Verze: V2 * Aktualizace: 24.03.2026
declare(strict_types=1);

$card_min_html = '<p class="card_text">Zde bude obsah min režimu</p>';

ob_start();
?>
<p class="card_text">Zde bude max obsah</p>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/admin_priprava_init.php * Verze: V2 * Aktualizace: 24.03.2026 */
?>