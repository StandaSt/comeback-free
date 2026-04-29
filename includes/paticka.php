<?php
// includes/paticka.php * Verze: V8 * Aktualizace: 24.2.2026

/*
 * PATIČKA (jen footer)
 *
 * Co dělá:
 * - vykreslí patičku stránky (footer)
 *
 * Pozn.:
 * - žádné <script> ani </body></html> (to je v index.php)
 */

declare(strict_types=1);

$cbVerzeText = '';
if (isset($CB_VERZE)) {
    $cbVerzeText = trim((string)$CB_VERZE);
}
if ($cbVerzeText === '' && defined('CB_VERZE')) {
    $cbVerzeText = trim((string)CB_VERZE);
}
if ($cbVerzeText === '') {
    $cbVerzeText = '0.86';
}

?>

<footer class="footer bg_modra sirka100">
    <div class="footer_box text_11 gap_4 displ_flex jc_mezi sirka100">
        <div class="foot_left txt_l">
            <strong>Comeback -</strong> informační systém
        </div>

        <div class="foot_center txt_c">
            © 2026 Comeback (Stst)
        </div>

        <div class="foot_right txt_r">
            <strong>verze <?= h($cbVerzeText) ?></strong> 
        </div>
    </div>
</footer>

<?php
/* includes/paticka.php * Verze: V8 * Aktualizace: 24.2.2026 * Počet řádků: 40 */
// Konec souboru
