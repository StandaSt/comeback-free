<?php
// includes/paticka.php * Verze: V7 * Aktualizace: 19.2.2026
declare(strict_types=1);

if (defined('COMEBACK_FOOTER_RENDERED')) {
    return;
}
define('COMEBACK_FOOTER_RENDERED', true);
?>
            </main>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-inner">
            <div class="footer-left">
                <strong>Comeback -</strong> informační systém
            </div>

            <div class="footer-center">
                © 2026 Comeback (Stst)
            </div>

            <div class="footer-right">
                <strong>verze 0.1</strong> (test)
            </div>
        </div>
    </footer>

</div>

<script src="<?= h(cb_url('js/ajax_core.js')) ?>"></script>
<script src="<?= h(cb_url('js/menu_ajax.js')) ?>"></script>
<script src="<?= h(cb_url('js/filtry.js')) ?>"></script>
<script src="<?= h(cb_url('js/filtry_reset.js')) ?>"></script>
<script src="<?= h(cb_url('js/strankovani.js')) ?>"></script>
<script src="<?= h(cb_url('js/casovac_odhlaseni.js')) ?>"></script>

</body>
</html>
<?php
/* includes/paticka.php * Verze: V7 * Aktualizace: 19.2.2026 * Počet řádků: 42 */
// Konec souboru