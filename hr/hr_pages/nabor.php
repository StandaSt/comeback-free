<?php
declare(strict_types=1);

$idVd = (int)($_GET['id_vd'] ?? 0);
$vdDetail = $idVd > 0 ? hr_nacti_vd_detail($db, $idVd) : null;
$vdAkce = $vdDetail !== null ? hr_nacti_vd_akce($db, $idVd) : [];
$vdPodminky = $vdDetail !== null ? hr_nacti_vd_podminky($db, $idVd) : null;
$vdAkceTypy = $vdDetail !== null ? hr_nacti_vd_akce_typy($db, (int)$vdDetail['id_vd_stav']) : [];
$vdAkceVysledky = $vdDetail !== null ? hr_nacti_vd_akce_vysledky($db, (int)$vdDetail['id_vd_stav']) : [];
$vdPodminkyCiselniky = $vdDetail !== null ? hr_nacti_vd_podminky_ciselniky($db) : ['vztahy' => [], 'oblasti' => [], 'sloty' => []];
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
        'title' => 'Nové veřejné dotazníky - potvrzené',
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
        'title' => 'Domluven nástup',
        'rows' => $nabor['domluveny_nastup'],
        'date_key' => 'posledni_aktivita',
        'date_label' => 'Aktivita',
        'minimal' => false,
        'show_dotaznik_status' => true,
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
        <section class="hr_panel hr_panel_wide hr_vd_full_width">
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

            <?php if ($vdPodminky !== null): ?>
                <?php
                $mzdaMesicni = $vdPodminky['mzda_mesicni_fix'] !== null ? (float)$vdPodminky['mzda_mesicni_fix'] : null;
                $mzdaHodinova = $vdPodminky['mzda_hodinova'] !== null ? (float)$vdPodminky['mzda_hodinova'] : null;
                $mzdaLabel = $mzdaMesicni !== null
                    ? number_format($mzdaMesicni, 0, ',', ' ') . ' Kč/měsíc'
                    : ($mzdaHodinova !== null ? number_format($mzdaHodinova, 0, ',', ' ') . ' Kč/hodina' : '-');
                ?>
                <div class="hr_vd_podminky_summary">
                    <h2 class="hr_vd_podminky_title">Dohodnuté podmínky</h2>
                    <dl class="hr_vd_podminky_summary_grid">
                        <div><dt>Pracovní vztah</dt><dd><?= h((string)($vdPodminky['vztah_nazev'] ?? '-')) ?></dd></div>
                        <div><dt>Oblast</dt><dd><?= h((string)$vdPodminky['oblasti_label']) ?></dd></div>
                        <div><dt>Pozice</dt><dd><?= h((string)$vdPodminky['pozice_label']) ?></dd></div>
                        <div><dt>Datum nástupu</dt><dd><?= h(hr_format_date((string)($vdPodminky['datum_nastupu'] ?? ''))) ?></dd></div>
                        <div><dt>Mzda / odměna</dt><dd><?= h($mzdaLabel) ?></dd></div>
                    </dl>
                </div>
            <?php endif; ?>

            <form class="hr_form hr_vd_action_form" method="post" data-hr-vd-action-form action="<?= h(cb_root_url('index.php?m=hr&page=nabor&id_vd=' . rawurlencode((string)$vdDetail['id_vd']))) ?>">
                <input type="hidden" name="cb_action" value="hr_nabor_ulozit_akci">
                <input type="hidden" name="id_vd" value="<?= h((string)$vdDetail['id_vd']) ?>">
                <div class="hr_vd_action_fields">
                    <label class="hr_form_label hr_vd_inline_field">
                        <span class="hr_form_label_text">Typ akce</span>
                        <select name="id_vd_akce_typ" data-hr-vd-action-type required>
                            <?php foreach ($vdAkceTypy as $typ): ?>
                                <option value="<?= h($typ['id']) ?>"><?= h($typ['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="hr_form_label hr_vd_inline_field">
                        <span class="hr_form_label_text">Výsledek</span>
                        <select name="id_vd_akce_vysledek" data-hr-vd-action-result required disabled>
                            <option value="">Nejprve vyberte akci</option>
                        </select>
                    </label>

                    <div class="hr_vd_term" data-hr-vd-term hidden>
                        <label class="hr_form_label hr_vd_inline_field">
                            <span class="hr_form_label_text">Další termín</span>
                            <input type="date" name="termin_date" data-hr-vd-term-date>
                        </label>
                        <label class="hr_form_label hr_vd_inline_field" data-hr-vd-term-time-wrap>
                            <span class="hr_form_label_text">Čas</span>
                            <input type="hidden" name="termin_time" data-hr-vd-term-time>
                            <div class="hr_vd_time_selects">
                                <select data-hr-vd-term-hour aria-label="Hodina">
                                    <?php for ($hodina = 8; $hodina <= 20; $hodina++): ?>
                                        <option value="<?= h((string)$hodina) ?>"><?= h((string)$hodina) ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select data-hr-vd-term-minute aria-label="Minuta">
                                    <?php foreach ([0, 15, 30, 45] as $minuta): ?>
                                        <option value="<?= h(str_pad((string)$minuta, 2, '0', STR_PAD_LEFT)) ?>"><?= h(str_pad((string)$minuta, 2, '0', STR_PAD_LEFT)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </label>
                    </div>

                </div>

                <label class="hr_form_label hr_vd_action_note">
                    <span class="hr_form_label_text">Poznámka</span>
                    <textarea name="poznamka" rows="6"></textarea>
                </label>

                <div class="hr_vd_domluveny_nastup" data-hr-vd-domluveny-nastup hidden>
                    <fieldset class="hr_vd_podminky">
                        <legend>Dohodnuté podmínky</legend>
                        <label class="hr_form_label"><span class="hr_form_label_text">Pracovní vztah</span><select name="id_pracovni_vztah_typ" data-hr-vd-podminka data-hr-vd-podminka-required><option value="">Vyberte</option><?php foreach ($vdPodminkyCiselniky['vztahy'] as $vztah): ?><option value="<?= h((string)$vztah['id']) ?>"><?= h((string)$vztah['label']) ?></option><?php endforeach; ?></select></label>
                        <div class="hr_form_label hr_vd_oblasti"><span class="hr_form_label_text">Oblast pracoviště</span><div class="hr_vd_oblasti_choices"><?php foreach ($vdPodminkyCiselniky['oblasti'] as $oblast): ?><label class="hr_checkbox_label"><input type="checkbox" name="pracoviste_oblasti[]" value="<?= h((string)$oblast['id']) ?>" data-hr-vd-podminka data-hr-vd-oblast> <?= h((string)$oblast['label']) ?></label><?php endforeach; ?></div></div>
                        <label class="hr_form_label"><span class="hr_form_label_text">Pozice</span><select name="id_slot" data-hr-vd-podminka data-hr-vd-podminka-required><option value="">Vyberte</option><?php foreach ($vdPodminkyCiselniky['sloty'] as $slot): ?><option value="<?= h((string)$slot['id']) ?>"><?= h((string)$slot['label']) ?></option><?php endforeach; ?></select></label>
                        <label class="hr_form_label"><span class="hr_form_label_text">Datum nástupu</span><input type="date" name="datum_nastupu" data-hr-vd-podminka data-hr-vd-podminka-required></label>
                        <label class="hr_form_label"><span class="hr_form_label_text">Typ odměny</span><select name="mzda_typ" data-hr-vd-podminka data-hr-vd-podminka-required><option value="">Vyberte</option><option value="mesicni">Měsíční mzda</option><option value="hodinova">Hodinová odměna</option></select></label>
                        <label class="hr_form_label"><span class="hr_form_label_text">Mzda / odměna v Kč</span><input type="text" name="mzda" inputmode="numeric" pattern="[0-9]*" maxlength="10" data-hr-vd-podminka data-hr-vd-podminka-required></label>
                    </fieldset>
                </div>

                <script type="application/json" data-hr-vd-action-results><?= json_encode($vdAkceVysledky, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

                <div class="hr_form_actions hr_vd_action_submit">
                    <button class="hr_primary_button" type="submit">Uložit akci</button>
                </div>
            </form>
        </section>

        <section class="hr_panel hr_vd_full_width">
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
                                <th class="hr_table_cell hr_table_head">Výsledek</th>
                                <th class="hr_table_cell hr_table_head">Termín</th>
                                <th class="hr_table_cell hr_table_head">Zadal</th>
                                <th class="hr_table_cell hr_table_head">Poznámka</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vdAkce as $akce): ?>
                                <tr>
                                    <td class="hr_table_cell"><?= h(hr_format_date((string)$akce['akce_kdy'])) ?></td>
                                    <td class="hr_table_cell"><?= h((string)$akce['akce_typ_nazev']) ?></td>
                                    <td class="hr_table_cell"><?= h((string)$akce['vysledek']) ?></td>
                                    <td class="hr_table_cell"><?= h(trim((string)($akce['termin_date'] ?? '') . ' ' . substr((string)($akce['termin_time'] ?? ''), 0, 5)) ?: '-') ?></td>
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

<?php if ($idVd > 0 && $vdDetail !== null): ?>
    <section class="hr_panel hr_vd_full_width">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title">Domluvené pohovory</h2>
            <span class="hr_panel_header_count"><?= h(hr_pocet_uchazecu_text(count($nabor['domluvene_pohovory']))) ?></span>
        </div>
        <?php if ($nabor['domluvene_pohovory'] === []): ?>
            <p class="hr_empty_state">Aktuálně žádný domluvený pohovor.</p>
        <?php else: ?>
            <div class="hr_table_wrap">
                <table class="hr_table">
                    <thead><tr><th class="hr_table_cell hr_table_head">Pohovor</th><th class="hr_table_cell hr_table_head">Uchazeč</th><th class="hr_table_cell hr_table_head">Pozice</th></tr></thead>
                    <tbody>
                        <?php foreach ($nabor['domluvene_pohovory'] as $uchazec): ?>
                            <tr>
                                <td class="hr_table_cell"><?= h($formatDateTime((string)($uchazec['planovano_na'] ?? ''))) ?></td>
                                <td class="hr_table_cell"><a href="<?= h(cb_root_url('index.php?m=hr&page=nabor&id_vd=' . rawurlencode((string)$uchazec['id_vd']))) ?>"><?= h($uchazec['cele_jmeno']) ?></a></td>
                                <td class="hr_table_cell"><?= h($uchazec['pozice']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php else: ?>
<?php foreach ($bloky as $blok): ?>
    <section class="hr_panel hr_nabor_overview_panel">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title"><?= h($blok['title']) ?></h2>
            <span class="hr_panel_header_count"><?= h(hr_pocet_uchazecu_text(count($blok['rows']))) ?></span>
        </div>

        <?php if ($blok['rows'] === []): ?>
            <p class="hr_empty_state">Aktuálně žádný uchazeč.</p>
        <?php else: ?>
            <div class="hr_table_wrap">
                <table class="hr_table hr_nabor_table">
                    <colgroup>
                        <col class="hr_nabor_col_uchazec">
                        <col class="hr_nabor_col_telefon">
                        <col class="hr_nabor_col_email">
                        <col class="hr_nabor_col_pozice">
                        <col class="hr_nabor_col_pracoviste">
                        <col class="hr_nabor_col_datum">
                        <col class="hr_nabor_col_info">
                        <col class="hr_nabor_col_zbytek">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="hr_table_cell hr_table_head">Uchazeč</th>
                            <th class="hr_table_cell hr_table_head">Telefon</th>
                            <th class="hr_table_cell hr_table_head">E-mail</th>
                            <?php if (!empty($blok['show_expiration'])): ?>
                                <th class="hr_table_cell hr_table_head" colspan="2"><?= h($blok['date_label']) ?></th>
                                <th class="hr_table_cell hr_table_head" colspan="3">Expiruje</th>
                            <?php elseif (!empty($blok['minimal'])): ?>
                                <th class="hr_table_cell hr_table_head" colspan="5"><?= h($blok['date_label']) ?></th>
                            <?php else: ?>
                                <th class="hr_table_cell hr_table_head">Pozice</th>
                                <th class="hr_table_cell hr_table_head">Pracoviště</th>
                                <th class="hr_table_cell hr_table_head"><?= h($blok['date_label']) ?></th>
                                <?php if (!empty($blok['show_dotaznik_status'])): ?>
                                    <th class="hr_table_cell hr_table_head">Nástupní dotazník</th>
                                    <th class="hr_table_cell hr_table_head">Stav</th>
                                <?php else: ?>
                                    <th class="hr_table_cell hr_table_head" colspan="2">Stav</th>
                                <?php endif; ?>
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
                                <td class="hr_table_cell"><?= h($uchazec['telefon']) ?></td>
                                <td class="hr_table_cell"><?= h($uchazec['email']) ?></td>
                                <?php if (!empty($blok['show_expiration'])): ?>
                                    <td class="hr_table_cell" colspan="2"><?= h($formatDateTime((string)($uchazec[$blok['date_key']] ?? ''))) ?></td>
                                    <td class="hr_table_cell" colspan="3"><?= h($formatDateTime((string)($uchazec['platnost_do'] ?? ''))) ?></td>
                                <?php elseif (!empty($blok['minimal'])): ?>
                                    <td class="hr_table_cell" colspan="5"><?= h(hr_format_date((string)($uchazec[$blok['date_key']] ?? ''))) ?></td>
                                <?php else: ?>
                                    <td class="hr_table_cell"><?= h($uchazec['pozice']) ?></td>
                                    <td class="hr_table_cell"><?= h($uchazec['pracoviste_preference']) ?></td>
                                    <td class="hr_table_cell"><?= h(hr_format_date((string)($uchazec[$blok['date_key']] ?? ''))) ?></td>
                                    <?php if (!empty($blok['show_dotaznik_status'])): ?>
                                        <td class="hr_table_cell"><?= !empty($uchazec['dotaznik_odeslan']) ? 'Odeslán' : '<strong>Nástupní dotazník neodeslán</strong>' ?></td>
                                        <td class="hr_table_cell"><span class="hr_badge hr_neutral"><?= h($uchazec['stav_label']) ?></span></td>
                                    <?php else: ?>
                                        <td class="hr_table_cell" colspan="2"><span class="hr_badge hr_neutral"><?= h($uchazec['stav_label']) ?></span></td>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
<?php endif; ?>
