<?php
declare(strict_types=1);

/*
 * Pohled kategorie Prihlaseni a 2FA.
 * Zobrazuje pouze bezpecne stopy a orientacni porovnani, nikdy zadane heslo.
 */

require_once __DIR__ . '/../../admin_db/admin_prihlaseni_dohled_db.php';

$limit = cb_admin_prihlaseni_limit($_GET['limit'] ?? 50);
$pokusy = cb_admin_prihlaseni_pokusy(db(), $limit);
$twoFactor = cb_admin_prihlaseni_2fa(db(), $limit);
$twoFactorLabels = [
    'ceka' => 'Čeká na rozhodnutí',
    'ok' => 'Schváleno',
    'ne' => 'Zamítnuto',
    'exp' => 'Vypršelo',
];
?>
<form class="admin_chyby_limit" method="get" action="<?= h(cb_root_url('index.php')) ?>">
    <input type="hidden" name="m" value="administrace">
    <input type="hidden" name="page" value="log_chyby">
    <input type="hidden" name="cat" value="prihlaseni">
    <label>Zobrazit posledních
        <select name="limit" onchange="this.form.submit()">
            <?php foreach ([20, 50, 100, 250] as $value): ?>
                <option value="<?= $value ?>"<?= $limit === $value ? ' selected' : '' ?>><?= $value ?></option>
            <?php endforeach; ?>
        </select>
        záznamů
    </label>
</form>

<section class="admin_rights_editor_panel">
    <h3>Neúspěšné pokusy</h3>
    <p class="admin_chyby_note">Hodnocení je orientační. Mobilní síť, sdílená IP nebo změna prohlížeče mohou výsledek ovlivnit.</p>
    <div class="admin_matrix_wrap table-wrap">
        <table class="admin_matrix admin_chyby_simple_table">
            <thead><tr><th>ID</th><th>Kdy</th><th>Účet</th><th>IP</th><th>Zařízení</th><th>Následný login</th><th>Odhad</th></tr></thead>
            <tbody>
                <?php if ($pokusy === []): ?>
                    <tr><td colspan="7" class="admin_rights_editor_empty">Nebyly nalezeny žádné neúspěšné pokusy.</td></tr>
                <?php endif; ?>
                <?php foreach ($pokusy as $row): ?>
                    <?php
                    $hodnoceni = cb_admin_prihlaseni_hodnoceni($row);
                    $jmeno = trim((string)($row['jmeno'] ?? '') . ' ' . (string)($row['prijmeni'] ?? ''));
                    $ua = trim((string)($row['user_agent'] ?? ''));
                    $rozmery = !empty($row['screen_w']) && !empty($row['screen_h'])
                        ? (string)$row['screen_w'] . ' × ' . (string)$row['screen_h']
                        : 'neuvedeno';
                    ?>
                    <tr>
                        <td><?= h((string)(int)$row['id_bad_login']) ?></td>
                        <td><?= h($formatKdy($row['kdy'] ?? null)) ?></td>
                        <td>
                            <strong><?= h((string)$row['email']) ?></strong>
                            <br><small><?= h($jmeno !== '' ? $jmeno . ' · ID ' . (string)$row['id_user'] : 'Účet v IS nenalezen') ?></small>
                        </td>
                        <td><?= h((string)($row['ip'] ?: '—')) ?></td>
                        <td>
                            <?= !empty($row['zname_zarizeni']) ? 'Známé' : 'Neznámé' ?>
                            <details><summary>Detail</summary><div><?= h($ua !== '' ? $ua : 'User-agent neuveden') ?><br>Displej: <?= h($rozmery) ?><br>Dotyk: <?= !empty($row['is_touch']) ? 'ano' : 'ne / neuvedeno' ?></div></details>
                        </td>
                        <td>
                            <?= h($formatKdy($row['uspesny_login_kdy'] ?? null)) ?>
                            <?php if (!empty($row['uspesny_login_ip'])): ?><br><small>IP <?= h((string)$row['uspesny_login_ip']) ?></small><?php endif; ?>
                        </td>
                        <td><span class="admin_chyby_badge is-<?= h($hodnoceni['key']) ?>"><?= h($hodnoceni['label']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin_rights_editor_panel">
    <h3>Poslední 2FA výzvy</h3>
    <p class="admin_chyby_note">Zamítnutí nebo vypršení výzvy je průběh přihlášení, nikoli technická chyba.</p>
    <div class="admin_matrix_wrap table-wrap">
        <table class="admin_matrix admin_chyby_simple_table">
            <thead><tr><th>ID</th><th>Vytvořeno</th><th>Uživatel</th><th>IP</th><th>Zařízení</th><th>Stav</th><th>Rozhodnuto</th></tr></thead>
            <tbody>
                <?php if ($twoFactor === []): ?>
                    <tr><td colspan="7" class="admin_rights_editor_empty">Nebyly nalezeny žádné 2FA výzvy.</td></tr>
                <?php endif; ?>
                <?php foreach ($twoFactor as $row): ?>
                    <?php $jmeno = trim((string)($row['jmeno'] ?? '') . ' ' . (string)($row['prijmeni'] ?? '')); ?>
                    <tr>
                        <td><?= h((string)(int)$row['id']) ?></td>
                        <td><?= h($formatKdy($row['vytvoreno'] ?? null)) ?></td>
                        <td><?= h($jmeno !== '' ? $jmeno . ' · ID ' . (string)$row['id_user'] : 'ID ' . (string)$row['id_user']) ?></td>
                        <td><?= h((string)($row['ip'] ?: '—')) ?></td>
                        <td><?= h((string)($row['prohlizec'] ?: '—')) ?></td>
                        <td><?= h($twoFactorLabels[(string)$row['stav']] ?? (string)$row['stav']) ?></td>
                        <td><?= h($formatKdy($row['rozhodnuto'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

