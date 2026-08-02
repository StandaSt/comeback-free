<?php
// includes/paticka.php * Verze: V8 * Aktualizace: 24.2.2026

/*
 * PATIČKA (jen footer)
 *
 * Co dělá:
 * - vykreslí patičku stránky (footer)
 *
 * Pozn.:
 * - žádné <script> ani </body></html> (to je v index_is.php)
 */

declare(strict_types=1);

$cbPatickaVerze = '0';
$cbPatickaUprava = '---';

try {
    $conn = db_connect();
    $resPaticka = $conn->query('SELECT verze, uprava_souboru FROM set_system WHERE id_set = 1 LIMIT 1');
    if ($resPaticka instanceof mysqli_result) {
        $rowPaticka = $resPaticka->fetch_assoc();
        $resPaticka->free();

        if (is_array($rowPaticka)) {
            $cbPatickaVerze = (string)((int)($rowPaticka['verze'] ?? 0));

            $cbPatickaUpravaRaw = trim((string)($rowPaticka['uprava_souboru'] ?? ''));
            if ($cbPatickaUpravaRaw !== '') {
                try {
                    $cbPatickaUprava = (new DateTimeImmutable($cbPatickaUpravaRaw))->format('j.n.Y H:i:s');
                } catch (Throwable $e) {
                    $cbPatickaUprava = $cbPatickaUpravaRaw;
                }
            }
        }
    }
} catch (Throwable $e) {
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
            <span class="cb_tooltip" tabindex="0" aria-label="Datum a čas verze" data-cb-tooltip-position="1">
                <strong style="display:inline-block;cursor:help;">Verze: 0.3.<?= h($cbPatickaVerze) ?></strong>
                <span class="cb_tooltip_panel cb_tooltip_card" data-cb-tooltip-panel="1">
                    <div class="text_12">Poslední aktualizace: <?= h($cbPatickaUprava) ?></div>
                </span>
            </span>
        </div>
    </div>
</footer>

<?php
/* includes/paticka.php * Verze: V8 * Aktualizace: 24.2.2026 * Počet řádků: 40 */
// Konec souboru
