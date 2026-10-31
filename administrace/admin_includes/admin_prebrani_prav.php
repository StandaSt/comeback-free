<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';

/* Kompaktni vyvojovy vyber Prebrani prav; dostupny jen normalne prihlasenemu adminovi USER 1. */
require_once __DIR__ . '/../../common/lib/prebrani_prav.php';
if ((int)($_SESSION['cb_user']['id_user'] ?? 0) !== 1 || !cb_user_ma_roli(1) || cb_prebrani_prav_profil() !== null) {
    return;
}
$ready = cb_prebrani_prav_pripraveno(db());
?>
<section class="blok admin_prebrani_prav" aria-label="Přebrání práv">
    <h2 class="blok_title">Přebrání práv</h2>
    <p>Otestujete práva, role, funkce a pobočky vybraného uživatele. Vaše ID a autorství změn zůstane 1.</p>
    <?php if (!$ready): ?>
        <p>Nejprve je třeba vytvořit tabulku admin_new_identita připraveným SQL.</p>
    <?php else:
        $sources = db()->query('SELECT p.id_user,' . cb_osoba_jmeno_sql('o.id_person',true) . ' AS jmeno
            FROM hr_person p JOIN user u ON u.id_user=p.id_user
            JOIN ' . cb_osoby_zdroj_sql() . ' o ON o.id_person=p.id_person AND o.platny=1
            WHERE p.aktivni=1 ORDER BY o.prijmeni,o.jmeno,p.id_user'); ?>
        <form method="post" action="<?= h(cb_root_url('index.php')) ?>">
            <input type="hidden" name="cb_action" value="admin_prebrani_prav">
            <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
            <label>Uživatel k otestování
                <select name="id_user_zdroj" required>
                    <option value="">Vyberte uživatele</option>
                    <?php while ($source = $sources->fetch_assoc()): ?>
                        <option value="<?= h((string)$source['id_user']) ?>"><?= h($source['jmeno'] . ' — ID ' . $source['id_user']) ?></option>
                    <?php endwhile; $sources->free(); ?>
                </select>
            </label>
            <button type="submit">Přebrat práva a odhlásit</button>
        </form>
    <?php endif; ?>
</section>
