<?php
declare(strict_types=1);

/* Zobrazí vstup IČO, needitovatelné údaje ARES a výběr hlavního jednatele. */

if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(105)) {
    http_response_code(403);
    echo '<section class="blok"><h2 class="blok_title">Přístup zamítnut</h2><p>Nemáte právo přidat firmu.</p></section>';
    return;
}

$flash = $_SESSION['cb_admin_firma_flash'] ?? null;
unset($_SESSION['cb_admin_firma_flash']);
$stav = $_SESSION['cb_admin_firma_ares'] ?? null;
$firma = is_array($stav) && is_array($stav['data'] ?? null) ? $stav['data'] : null;
$ico = is_array($firma)
    ? (string)($firma['ico'] ?? '')
    : (is_array($flash) ? (string)($flash['ico'] ?? '') : '');
$csrfToken = cb_admin_firma_csrf_token();
$formUrl = cb_root_url('index.php?m=administrace&page=firma_pridat');
$firmy = cb_admin_firmy_nacti(db());
?>
<section class="blok">
    <div class="admin_rights_editor">
        <?php if (is_array($flash)): ?>
            <p class="<?= (string)($flash['typ'] ?? '') === 'ok' ? 'txt_zelena' : 'txt_cervena' ?>"><?= h((string)($flash['text'] ?? '')) ?></p>
        <?php endif; ?>

        <section class="admin_rights_editor_panel">
            <h2 class="blok_title">Načtení firmy z ARES</h2>
            <form class="admin_script_form" method="post" action="<?= h($formUrl) ?>">
                <input type="hidden" name="cb_action" value="admin_firma_ares_nacist">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <label class="admin_rights_editor_select">
                    <span>IČO</span>
                    <input type="text" name="ico" value="<?= h($ico) ?>" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required autocomplete="off">
                </label>
                <button class="head_task_btn" type="submit">Načíst z ARES</button>
            </form>
        </section>

        <?php if (is_array($firma)): ?>
            <?php $jednatele = is_array($firma['jednatele'] ?? null) ? array_values($firma['jednatele']) : []; ?>
            <section class="admin_rights_editor_panel">
                <h2 class="blok_title">Oficiální údaje firmy</h2>
                <div class="admin_matrix_wrap">
                    <table class="admin_matrix">
                        <tbody>
                            <tr><th>Obchodní název</th><td><?= h((string)$firma['obchodni_jmeno']) ?></td></tr>
                            <tr><th>IČO</th><td><?= h((string)$firma['ico']) ?></td></tr>
                            <tr><th>DIČ</th><td><?= h((string)($firma['dic'] ?? '—')) ?></td></tr>
                            <tr><th>Právní forma</th><td><?= h((string)($firma['pravni_forma'] ?? '—')) ?></td></tr>
                            <tr><th>Datum vzniku</th><td><?= h((string)($firma['datum_vzniku'] ?? '—')) ?></td></tr>
                            <tr><th>Sídlo</th><td><?= h((string)($firma['textova_adresa'] ?? '—')) ?></td></tr>
                            <tr><th>Aktualizace ARES</th><td><?= h((string)($firma['datum_aktualizace_ares'] ?? '—')) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin_rights_editor_panel">
                <h2 class="blok_title">Jednatelé</h2>
                <p><?= count($jednatele) > 1 ? 'Vyberte jednoho hlavního jednatele.' : 'Jediný jednatel bude označen jako hlavní.' ?></p>
                <form method="post" action="<?= h($formUrl) ?>">
                    <input type="hidden" name="cb_action" value="admin_firma_ulozit">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="ares_nonce" value="<?= h((string)($stav['nonce'] ?? '')) ?>">
                    <div class="admin_matrix_wrap">
                        <table class="admin_matrix">
                            <thead>
                                <tr><th>Hlavní</th><th>Jméno</th><th>Příjmení</th><th>Funkce</th><th>Funkce od</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jednatele as $index => $jednatel): ?>
                                    <tr>
                                        <td class="admin_matrix_check"><input type="radio" name="hlavni_jednatel" value="<?= h((string)$index) ?>" required<?= count($jednatele) === 1 ? ' checked' : '' ?>></td>
                                        <td><?= h((string)$jednatel['jmeno']) ?></td>
                                        <td><?= h((string)$jednatel['prijmeni']) ?></td>
                                        <td><?= h((string)$jednatel['funkce']) ?></td>
                                        <td><?= h((string)($jednatel['funkce_od'] ?? '—')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p><button class="head_task_btn" type="submit">Přidat firmu do IS</button></p>
                </form>
            </section>
        <?php endif; ?>

        <section class="admin_rights_editor_panel">
            <h2 class="blok_title">Firmy v systému</h2>
            <?php if ($firmy === []): ?>
                <p class="admin_rights_editor_empty">V systému zatím není žádná firma.</p>
            <?php else: ?>
                <div class="admin_matrix_wrap">
                    <table class="admin_matrix">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Firma</th>
                                <th>IČO</th>
                                <th>Sídlo</th>
                                <th>Stav</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($firmy as $firmaSystem): ?>
                                <?php
                                $detailId = 'admin-firma-detail-' . (string)$firmaSystem['id_firma'];
                                $jednateleSystem = is_array($firmaSystem['jednatele'] ?? null) ? $firmaSystem['jednatele'] : [];
                                ?>
                                <tr>
                                    <td><?= h((string)$firmaSystem['id_firma']) ?></td>
                                    <td><strong><?= h((string)$firmaSystem['obchodni_jmeno']) ?></strong></td>
                                    <td><?= h((string)$firmaSystem['ico']) ?></td>
                                    <td><?= h((string)($firmaSystem['textova_adresa'] ?? '—')) ?></td>
                                    <td class="<?= !empty($firmaSystem['aktivni']) ? 'txt_zelena' : 'txt_cervena' ?>"><?= !empty($firmaSystem['aktivni']) ? 'Aktivní' : 'Neaktivní' ?></td>
                                    <td><button class="head_task_btn" type="button" data-row-detail-toggle="<?= h($detailId) ?>" aria-expanded="false">detail</button></td>
                                </tr>
                                <tr data-row-detail="<?= h($detailId) ?>" hidden>
                                    <td colspan="6">
                                        <div class="admin_matrix_blocks">
                                            <div class="admin_matrix_wrap">
                                                <table class="admin_matrix">
                                                    <tbody>
                                                        <tr><th>DIČ</th><td><?= h((string)($firmaSystem['dic'] ?? '—')) ?></td></tr>
                                                        <tr><th>Právní forma</th><td><?= h((string)($firmaSystem['pravni_forma'] ?? '—')) ?></td></tr>
                                                        <tr><th>Datum vzniku</th><td><?= h((string)($firmaSystem['datum_vzniku'] ?? '—')) ?></td></tr>
                                                        <tr><th>Mateřská firma</th><td><?= $firmaSystem['id_firma_nadrazena'] !== null ? 'ID ' . h((string)$firmaSystem['id_firma_nadrazena']) : '—' ?></td></tr>
                                                        <tr><th>Platnost</th><td><?= h((string)$firmaSystem['platnost_od']) ?> – <?= h((string)($firmaSystem['platnost_do'] ?? 'dosud')) ?></td></tr>
                                                        <tr><th>Aktualizace ARES</th><td><?= h((string)($firmaSystem['datum_aktualizace_ares'] ?? '—')) ?></td></tr>
                                                        <tr><th>Načteno z ARES</th><td><?= h((string)$firmaSystem['ares_nacteno']) ?></td></tr>
                                                        <tr><th>Zadáno</th><td><?= h((string)$firmaSystem['zadano']) ?> uživatelem ID <?= h((string)$firmaSystem['zadal']) ?></td></tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <?php if ($jednateleSystem === []): ?>
                                                <p class="admin_rights_editor_empty">Firma nemá evidovaného jednatele.</p>
                                            <?php else: ?>
                                                <div class="admin_matrix_wrap">
                                                    <table class="admin_matrix">
                                                        <thead>
                                                            <tr><th>Jednatel</th><th>Funkce</th><th>Funkce od</th><th>Funkce do</th><th>Hlavní</th><th>Stav</th></tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($jednateleSystem as $jednatelSystem): ?>
                                                                <tr>
                                                                    <td><?= h(trim((string)$jednatelSystem['jmeno'] . ' ' . (string)$jednatelSystem['prijmeni'])) ?></td>
                                                                    <td><?= h((string)$jednatelSystem['funkce']) ?></td>
                                                                    <td><?= h((string)($jednatelSystem['funkce_od'] ?? '—')) ?></td>
                                                                    <td><?= h((string)($jednatelSystem['funkce_do'] ?? '—')) ?></td>
                                                                    <td><?= !empty($jednatelSystem['hlavni']) ? 'Ano' : 'Ne' ?></td>
                                                                    <td><?= !empty($jednatelSystem['aktivni']) ? 'Aktivní' : 'Neaktivní' ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
