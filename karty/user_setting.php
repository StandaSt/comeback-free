<?php
// karty/user_setting.php * Verze: V1 * Aktualizace: 25.03.2026
declare(strict_types=1);

$card_min_html = '<p class="card_text">Zde bude obsah min režimu</p>';

ob_start();
?>
<p class="card_text">Zde bude max obsah</p>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/user_setting.php * Verze: V1 * Aktualizace: 25.03.2026 */
?>