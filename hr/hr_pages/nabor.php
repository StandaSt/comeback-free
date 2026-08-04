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
        <section class="panel">
            <div class="panel-header">
                <h2>Detail VD</h2>
            </div>
            <p class="empty-state">Veřejný dotazník nebyl nalezen.</p>
        </section>
    <?php else: ?>
        <section class="panel panel-wide">
            <div class="panel-header">
                <div>
                    <h2><?= h($vdDetail['cele_jmeno']) ?></h2>
                    <p class="muted">VD #<?= h((string)$vdDetail['id_vd']) ?> · <?= h($vdDetail['stav_nazev']) ?></p>
                </div>
                <a class="secondary-button" href="<?= h(cb_root_url('index.php?m=hr&page=nabor')) ?>">Zavřít detail</a>
            </div>

            <div class="vd-detail-grid">
                <dl class="detail-list compact-detail-list">
                    <div><dt>Telefon</dt><dd><?= h($vdDetail['telefon']) ?></dd></div>
                    <div><dt>E-mail</dt><dd><?= h($vdDetail['email']) ?></dd></div>
                    <div><dt>Zdroj</dt><dd><?= h($vdDetail['zdroj_nazev']) ?></dd></div>
                </dl>

                <dl class="detail-list compact-detail-list">
                    <div><dt>Pracoviště</dt><dd><?= h($vdDetail['pracoviste_preference']) ?></dd></div>
                    <div><dt>Pozice</dt><dd><?= h($vdDetail['pozice']) ?></dd></div>
                    <div><dt>Očekávaná mzda</dt><dd><?= h((string)($vdDetail['ocekavana_mzda'] ?? '-')) ?></dd></div>
                </dl>

                <dl class="detail-list compact-detail-list">
                    <div><dt>Odesláno</dt><dd><?= h(hr_format_date((string)($vdDetail['zadano'] ?? ''))) ?></dd></div>
                    <div><dt>Možný nástup</dt><dd><?= h(hr_format_date((string)($vdDetail['mozny_nastup'] ?? ''))) ?></dd></div>
                    <div><dt>Povídání</dt><dd><?= h(trim((string)($vdDetail['povidani'] ?? '')) !== '' ? (string)$vdDetail['povidani'] : '-') ?></dd></div>
                </dl>
            </div>

            <form class="hr-form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=nabor&id_vd=' . rawurlencode((string)$vdDetail['id_vd']))) ?>">
                <input type="hidden" name="id_vd" value="<?= h((string)$vdDetail['id_vd']) ?>">
                <div class="form-grid">
                    <label>
                        <span>Stav VD</span>
                        <select name="id_vd_stav" required>
                            <?php foreach ($vdStavy as $stav): ?>
                                <option value="<?= h($stav['id']) ?>"<?= (int)$stav['id'] === (int)$vdDetail['id_vd_stav'] ? ' selected' : '' ?>><?= h($stav['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Typ akce</span>
                        <select name="id_vd_akce_typ" required>
                            <option value="">Vyberte</option>
                            <?php foreach ($vdAkceTypy as $typ): ?>
                                <option value="<?= h($typ['id']) ?>"><?= h($typ['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Kdy</span>
                        <input type="datetime-local" name="akce_kdy" required value="<?= h(date('Y-m-d\TH:i')) ?>">
                    </label>

                    <label>
                        <span>Poznámka</span>
                        <textarea name="poznamka" rows="3"></textarea>
                    </label>
                </div>

                <div class="form-actions">
                    <button class="primary-button" type="submit">Uložit akci</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Historie náboru</h2>
            </div>

            <?php if ($vdAkce === []): ?>
                <p class="empty-state">Zatím není zapsaná žádná akce.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Kdy</th>
                                <th>Akce</th>
                                <th>Zadal</th>
                                <th>Poznámka</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vdAkce as $akce): ?>
                                <tr>
                                    <td><?= h(hr_format_date((string)$akce['akce_kdy'])) ?></td>
                                    <td><?= h((string)$akce['akce_typ_nazev']) ?></td>
                                    <td><?= h((string)$akce['zadal_label']) ?></td>
                                    <td><?= h((string)$akce['poznamka']) ?></td>
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
    <section class="panel">
        <div class="panel-header">
            <h2><?= h($blok['title']) ?></h2>
            <span class="panel-header-count"><?= h(hr_pocet_uchazecu_text(count($blok['rows']))) ?></span>
        </div>

        <?php if ($blok['rows'] === []): ?>
            <p class="empty-state">Aktuálně žádný uchazeč.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Uchazeč</th>
                            <?php if (!empty($blok['show_expiration'])): ?>
                                <th>E-mail</th>
                                <th>Telefon</th>
                            <?php elseif (empty($blok['minimal'])): ?>
                                <th>Telefon</th>
                                <th>E-mail</th>
                                <th>Pozice</th>
                                <th>Pracoviště</th>
                            <?php endif; ?>
                            <th><?= h($blok['date_label']) ?></th>
                            <?php if (!empty($blok['show_expiration'])): ?>
                                <th>Expiruje</th>
                            <?php endif; ?>
                            <?php if (empty($blok['minimal'])): ?>
                                <th>Stav</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blok['rows'] as $uchazec): ?>
                            <tr>
                                <td>
                                    <?php if (empty($blok['minimal'])): ?>
                                        <a href="<?= h(cb_root_url('index.php?m=hr&page=nabor&id_vd=' . rawurlencode((string)$uchazec['id_vd']))) ?>"><?= h($uchazec['cele_jmeno']) ?></a>
                                    <?php else: ?>
                                        <?= h($uchazec['cele_jmeno']) ?>
                                    <?php endif; ?>
                                </td>
                                <?php if (!empty($blok['show_expiration'])): ?>
                                    <td><?= h($uchazec['email']) ?></td>
                                    <td><?= h($uchazec['telefon']) ?></td>
                                <?php elseif (empty($blok['minimal'])): ?>
                                    <td><?= h($uchazec['telefon']) ?></td>
                                    <td><?= h($uchazec['email']) ?></td>
                                    <td><?= h($uchazec['pozice']) ?></td>
                                    <td><?= h($uchazec['pracoviste_preference']) ?></td>
                                <?php endif; ?>
                                <td><?= h(!empty($blok['show_expiration']) ? $formatDateTime((string)($uchazec[$blok['date_key']] ?? '')) : hr_format_date((string)($uchazec[$blok['date_key']] ?? ''))) ?></td>
                                <?php if (!empty($blok['show_expiration'])): ?>
                                    <td><?= h($formatDateTime((string)($uchazec['platnost_do'] ?? ''))) ?></td>
                                <?php endif; ?>
                                <?php if (empty($blok['minimal'])): ?>
                                    <td><span class="badge neutral"><?= h($uchazec['stav_nazev']) ?></span></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
