<?php
declare(strict_types=1);

$idVd = (int)($_GET['id_vd'] ?? 0);
$vdDetail = $idVd > 0 ? hr_nacti_vd_detail($db, $idVd) : null;
$vdAkce = $vdDetail !== null ? hr_nacti_vd_akce($db, $idVd) : [];
$vdStavy = $vdDetail !== null ? hr_nacti_vd_stavy($db) : [];
$vdAkceTypy = $vdDetail !== null ? hr_nacti_vd_akce_typy($db) : [];
$nabor = hr_nacti_nabor_prehled($db);
$formatDateTime = static function (?string $date): string {
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '-';
    }

    $ts = strtotime($date);
    return $ts === false ? '-' : date('j. n. Y H:i', $ts);
};

$bloky = [
    [
        'title' => 'Nepotvrzené veřejné dotazníky',
        'rows' => $nabor['nepotvrzene_dotazniky'],
        'date_key' => 'zadano',
        'date_label' => 'Uloženo',
        'minimal' => true,
        'show_expiration' => true,
    ],
    [
        'title' => 'Nové veřejné dotazníky',
        'rows' => $nabor['nove_dotazniky'],
        'date_key' => 'zadano',
        'date_label' => 'Zadáno',
        'minimal' => false,
    ],
    [
        'title' => 'Domluvené pohovory',
        'rows' => $nabor['domluvene_pohovory'],
        'date_key' => 'planovano_na',
        'date_label' => 'Pohovor',
        'minimal' => false,
    ],
    [
        'title' => 'Čekáme na vstupní dotazník',
        'rows' => $nabor['ceka_na_vstupni_dotaznik'],
        'date_key' => 'odeslano',
        'date_label' => 'Odesláno',
        'minimal' => false,
    ],
    [
        'title' => 'Čekáme na podepsanou smlouvu',
        'rows' => $nabor['ceka_na_smlouvu'],
        'date_key' => 'posledni_aktivita',
        'date_label' => 'Aktivita',
        'minimal' => false,
    ],
    [
        'title' => 'Expirované - nepotvrzené dotazníky',
        'rows' => $nabor['expirovane_dotazniky'],
        'date_key' => 'posledni_aktivita',
        'date_label' => 'Expirace',
        'minimal' => true,
    ],
];
?>
<?php if ($idVd > 0): ?>
    <?php if ($vdDetail === null): ?>
        <section class="hr_panel">
            <div class="hr_panel_header">
                <h2 class="hr_panel_title">Detail VD</h2>
            </div>
            <p class="hr_empty_state">Veřejný dotazník nebyl nalezen.</p>
        </section>
    <?php else: ?>
        <section class="hr_panel hr_panel_wide">
            <div class="hr_panel_header">
                <div>
                    <h2 class="hr_panel_title"><?= h($vdDetail['cele_jmeno']) ?></h2>
                    <p class="hr_muted">VD #<?= h((string)$vdDetail['id_vd']) ?> · <?= h($vdDetail['stav_nazev']) ?></p>
                </div>
                <a class="hr_secondary_button hr_panel_button_secondary" href="<?= h(cb_root_url('index.php?m=hr&page=nabor')) ?>">Zavřít detail</a>
            </div>

            <div class="hr_vd_detail_grid">
                <dl class="hr_detail_list hr_compact_detail_list">
                    <div class="hr_detail_item"><dt class="hr_detail_term">Telefon</dt><dd class="hr_detail_value"><?= h($vdDetail['telefon']) ?></dd></div>
                    <div class="hr_detail_item"><dt class="hr_detail_term">E-mail</dt><dd class="hr_detail_value"><?= h($vdDetail['email']) ?></dd></div>
                    <div class="hr_detail_item"><dt class="hr_detail_term">Zdroj</dt><dd class="hr_detail_value"><?= h($vdDetail['zdroj_nazev']) ?></dd></div>
                </dl>

                <dl class="hr_detail_list hr_compact_detail_list">
                    <div class="hr_detail_item"><dt class="hr_detail_term">Pracoviště</dt><dd class="hr_detail_value"><?= h($vdDetail['pracoviste_preference']) ?></dd></div>
                    <div class="hr_detail_item"><dt class="hr_detail_term">Pozice</dt><dd class="hr_detail_value"><?= h($vdDetail['pozice']) ?></dd></div>
                    <div class="hr_detail_item"><dt class="hr_detail_term">Očekávaná mzda</dt><dd class="hr_detail_value"><?= h((string)($vdDetail['ocekavana_mzda'] ?? '-')) ?></dd></div>
                </dl>

                <dl class="hr_detail_list hr_compact_detail_list">
                    <div class="hr_detail_item"><dt class="hr_detail_term">Odesláno</dt><dd class="hr_detail_value"><?= h(hr_format_date((string)($vdDetail['zadano'] ?? ''))) ?></dd></div>
                    <div class="hr_detail_item"><dt class="hr_detail_term">Možný nástup</dt><dd class="hr_detail_value"><?= h(hr_format_date((string)($vdDetail['mozny_nastup'] ?? ''))) ?></dd></div>
                    <div class="hr_detail_item"><dt class="hr_detail_term">Povídání</dt><dd class="hr_detail_value"><?= h(trim((string)($vdDetail['povidani'] ?? '')) !== '' ? (string)$vdDetail['povidani'] : '-') ?></dd></div>
                </dl>
            </div>

            <form class="hr_form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=nabor&id_vd=' . rawurlencode((string)$vdDetail['id_vd']))) ?>">
                <input type="hidden" name="id_vd" value="<?= h((string)$vdDetail['id_vd']) ?>">
                <div class="hr_form_grid">
                    <label class="hr_form_label">
                        <span class="hr_form_label_text">Stav VD</span>
                        <select name="id_vd_stav" required>
                            <?php foreach ($vdStavy as $stav): ?>
                                <option value="<?= h($stav['id']) ?>"<?= (int)$stav['id'] === (int)$vdDetail['id_vd_stav'] ? ' selected' : '' ?>><?= h($stav['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="hr_form_label">
                        <span class="hr_form_label_text">Typ akce</span>
                        <select name="id_vd_akce_typ" required>
                            <option value="">Vyberte</option>
                            <?php foreach ($vdAkceTypy as $typ): ?>
                                <option value="<?= h($typ['id']) ?>"><?= h($typ['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="hr_form_label">
                        <span class="hr_form_label_text">Kdy</span>
                        <input type="datetime-local" name="akce_kdy" required value="<?= h(date('Y-m-d\TH:i')) ?>">
                    </label>

                    <label class="hr_form_label">
                        <span class="hr_form_label_text">Poznámka</span>
                        <textarea name="poznamka" rows="3"></textarea>
                    </label>
                </div>

                <div class="hr_form_actions">
                    <button class="hr_primary_button" type="submit">Uložit akci</button>
                </div>
            </form>
        </section>

        <section class="hr_panel">
            <div class="hr_panel_header">
                <h2 class="hr_panel_title">Historie náboru</h2>
            </div>

            <?php if ($vdAkce === []): ?>
                <p class="hr_empty_state">Zatím není zapsaná žádná akce.</p>
            <?php else: ?>
                <div class="hr_table_wrap">
                    <table class="hr_table">
                        <thead>
                            <tr>
                                <th class="hr_table_cell hr_table_head">Kdy</th>
                                <th class="hr_table_cell hr_table_head">Akce</th>
                                <th class="hr_table_cell hr_table_head">Zadal</th>
                                <th class="hr_table_cell hr_table_head">Poznámka</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vdAkce as $akce): ?>
                                <tr>
                                    <td class="hr_table_cell"><?= h(hr_format_date((string)$akce['akce_kdy'])) ?></td>
                                    <td class="hr_table_cell"><?= h((string)$akce['akce_typ_nazev']) ?></td>
                                    <td class="hr_table_cell"><?= h((string)$akce['zadal_label']) ?></td>
                                    <td class="hr_table_cell"><?= h((string)$akce['poznamka']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php foreach ($bloky as $blok): ?>
    <section class="hr_panel">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title"><?= h($blok['title']) ?></h2>
            <span class="hr_panel_header_count"><?= h(hr_pocet_uchazecu_text(count($blok['rows']))) ?></span>
        </div>

        <?php if ($blok['rows'] === []): ?>
            <p class="hr_empty_state">Aktuálně žádný uchazeč.</p>
        <?php else: ?>
            <div class="hr_table_wrap">
                <table class="hr_table">
                    <thead>
                        <tr>
                            <th class="hr_table_cell hr_table_head">Uchazeč</th>
                            <?php if (!empty($blok['show_expiration'])): ?>
                                <th class="hr_table_cell hr_table_head">E-mail</th>
                                <th class="hr_table_cell hr_table_head">Telefon</th>
                            <?php elseif (empty($blok['minimal'])): ?>
                                <th class="hr_table_cell hr_table_head">Telefon</th>
                                <th class="hr_table_cell hr_table_head">E-mail</th>
                                <th class="hr_table_cell hr_table_head">Pozice</th>
                                <th class="hr_table_cell hr_table_head">Pracoviště</th>
                            <?php endif; ?>
                            <th class="hr_table_cell hr_table_head"><?= h($blok['date_label']) ?></th>
                            <?php if (!empty($blok['show_expiration'])): ?>
                                <th class="hr_table_cell hr_table_head">Expiruje</th>
                            <?php endif; ?>
                            <?php if (empty($blok['minimal'])): ?>
                                <th class="hr_table_cell hr_table_head">Stav</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blok['rows'] as $uchazec): ?>
                            <tr>
                                <td class="hr_table_cell">
                                    <?php if (empty($blok['minimal'])): ?>
                                        <a href="<?= h(cb_root_url('index.php?m=hr&page=nabor&id_vd=' . rawurlencode((string)$uchazec['id_vd']))) ?>"><?= h($uchazec['cele_jmeno']) ?></a>
                                    <?php else: ?>
                                        <?= h($uchazec['cele_jmeno']) ?>
                                    <?php endif; ?>
                                </td>
                                <?php if (!empty($blok['show_expiration'])): ?>
                                    <td class="hr_table_cell"><?= h($uchazec['email']) ?></td>
                                    <td class="hr_table_cell"><?= h($uchazec['telefon']) ?></td>
                                <?php elseif (empty($blok['minimal'])): ?>
                                    <td class="hr_table_cell"><?= h($uchazec['telefon']) ?></td>
                                    <td class="hr_table_cell"><?= h($uchazec['email']) ?></td>
                                    <td class="hr_table_cell"><?= h($uchazec['pozice']) ?></td>
                                    <td class="hr_table_cell"><?= h($uchazec['pracoviste_preference']) ?></td>
                                <?php endif; ?>
                                <td class="hr_table_cell"><?= h(!empty($blok['show_expiration']) ? $formatDateTime((string)($uchazec[$blok['date_key']] ?? '')) : hr_format_date((string)($uchazec[$blok['date_key']] ?? ''))) ?></td>
                                <?php if (!empty($blok['show_expiration'])): ?>
                                    <td class="hr_table_cell"><?= h($formatDateTime((string)($uchazec['platnost_do'] ?? ''))) ?></td>
                                <?php endif; ?>
                                <?php if (empty($blok['minimal'])): ?>
                                    <td class="hr_table_cell"><span class="hr_badge hr_neutral"><?= h($uchazec['stav_nazev']) ?></span></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
