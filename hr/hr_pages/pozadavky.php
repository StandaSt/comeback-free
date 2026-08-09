<?php
declare(strict_types=1);

/**
 * Stranka HR pozadavku podle opravneni role.
 *
 * Role 1 vidi a spravuje vsechny pobocky, role 5 jen hlavni pobocku,
 * role 3 vidi vsechny pobocky pouze pro cteni.
 */
$pozadavkyUser = $_SESSION['cb_user'] ?? [];
$pozadavkyUserId = is_array($pozadavkyUser) ? (int)($pozadavkyUser['id_user'] ?? 0) : 0;
$pozadavkyRoleId = is_array($pozadavkyUser) ? (int)($pozadavkyUser['id_role'] ?? 0) : 0;
$pozadavkyJeAdmin = $pozadavkyRoleId === 1;
$pozadavkyJenCteni = $pozadavkyRoleId === 3;
$pozadavkyJeVedouci = $pozadavkyRoleId === 5;
$pozadavkyMuzeCist = $pozadavkyJeAdmin || $pozadavkyJenCteni || $pozadavkyJeVedouci;
$pozadavkyMuzeZadat = $pozadavkyJeAdmin || $pozadavkyJeVedouci;
$pozadavkyZobraziPobocku = $pozadavkyJeAdmin || $pozadavkyJenCteni;
$pozadavkyMainPobocka = [];
$pozadavkyPobocky = $pozadavkyJeAdmin ? hr_fetch_lookup($db, 'pobocka', 'id_pob', 'nazev') : [];
$pozadavkyPersonId = 0;
$pozadavkyUlozeno = !empty($_SESSION['hr_pozadavek_ulozeno']);
$pozadavkyZrusen = !empty($_SESSION['hr_pozadavek_zrusen']);
unset($_SESSION['hr_pozadavek_ulozeno']);
unset($_SESSION['hr_pozadavek_zrusen']);

if ($pozadavkyMuzeZadat) {
    try {
        $pozadavkyPersonId = hr_current_person_id($db);
    } catch (RuntimeException $e) {
        if (!$pozadavkyJeAdmin) {
            $pozadavkyMuzeZadat = false;
        }
        $pozadavkyPersonId = 1;
    }
}

if ($pozadavkyJeVedouci && $pozadavkyMuzeCist) {
    $pozadavkyMainPobocka = hr_nacti_hlavni_pobocku_uzivatele($db, $pozadavkyUserId);
}

$pozadavkyNove = [];
$pozadavkyVyresene = [];
$pozadavkyExpirovane = [];
$pozadavkyZrusene = [];

if ($pozadavkyMuzeCist && $pozadavkyZobraziPobocku) {
    // Admin a cteni vidi pozadavky napric vsemi pobockami.
    $pozadavkyNove = hr_nacti_pozadavky_podle_stavu($db, 1);
    $pozadavkyVyresene = hr_nacti_pozadavky_podle_stavu($db, 2);
    $pozadavkyExpirovane = hr_nacti_pozadavky_podle_stavu($db, 3);
    $pozadavkyZrusene = hr_nacti_pozadavky_podle_stavu($db, 4);
} elseif ($pozadavkyMuzeCist && $pozadavkyJeVedouci) {
    // Vedouci smeny vidi jen pozadavky sve hlavni pobocky.
    $pozadavkyNove = hr_nacti_nove_pozadavky_pobocky($db, (int)$pozadavkyMainPobocka['id_pob']);
    $pozadavkyVyresene = hr_nacti_pozadavky_pobocky_podle_stavu($db, (int)$pozadavkyMainPobocka['id_pob'], 2);
    $pozadavkyExpirovane = hr_nacti_pozadavky_pobocky_podle_stavu($db, (int)$pozadavkyMainPobocka['id_pob'], 3);
    $pozadavkyZrusene = hr_nacti_pozadavky_pobocky_podle_stavu($db, (int)$pozadavkyMainPobocka['id_pob'], 4);
}

$pozadavkyRozsah = $pozadavkyZobraziPobocku ? 'všech poboček' : 'pobočky ' . (string)($pozadavkyMainPobocka['nazev'] ?? '');
?>
<section class="hr_panel">
    <div class="hr_panel_header">
        <div>
            <h2 class="hr_request_title hr_panel_title">Zadání požadavku</h2>
        </div>
        <?php if ($pozadavkyUlozeno): ?>
            <p class="hr_notice hr_success hr_request_notice">Požadavek byl uložen.</p>
        <?php elseif ($pozadavkyZrusen): ?>
            <p class="hr_notice hr_success hr_request_notice">Požadavek byl odstraněn.</p>
        <?php endif; ?>
    </div>

    <?php if ($pozadavkyMuzeZadat): ?>
        <form class="hr_form hr_request_form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=pozadavky')) ?>" data-hr_request_form>
            <input type="hidden" name="akce" value="vytvorit">
            <span class="hr_request_text">Požaduji</span>
            <select class="hr_request_select" name="pocet">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <option value="<?= h($i) ?>"<?= $i === 1 ? ' selected' : '' ?>><?= h($i) ?></option>
                <?php endfor; ?>
            </select>
            <span class="hr_request_text">zaměstnance na pozici</span>
            <select class="hr_request_select" name="id_slot" data-hr-request-slot required>
                <option value="">Vyber</option>
                <option value="1">instor</option>
                <option value="2">kurýr</option>
            </select>
            <?php if ($pozadavkyJeAdmin): ?>
                <span class="hr_request_text">pro pobočku</span>
                <select class="hr_request_select" name="id_pob" required>
                    <option value="">Vyberte</option>
                    <?php foreach ($pozadavkyPobocky as $pobocka): ?>
                        <option value="<?= h($pobocka['id']) ?>"><?= h($pobocka['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <span class="hr_request_text">pro pobočku <?= h($pozadavkyMainPobocka['nazev']) ?>.</span>
            <?php endif; ?>
            <span class="hr_request_text">Poznámka:</span>
            <input class="hr_note_input hr_request_field" type="text" name="upresneni" maxlength="500" placeholder="Zde upřesněte, třeba termín nástupu.">
            <button class="hr_primary_button hr_request_submit" type="submit">Zadat požadavek</button>
        </form>
    <?php elseif ($pozadavkyJenCteni): ?>
        <p class="hr_empty_state">Požadavky máte dostupné pouze pro čtení.</p>
    <?php else: ?>
        <p class="hr_empty_state">Na tuto stránku nemáte přístup.</p>
    <?php endif; ?>
</section>

<?php if ($pozadavkyMuzeCist): ?>
    <section class="hr_panel">
        <div class="hr_panel_header">
            <div>
                <h2 class="hr_panel_title">Nové požadavky <?= h($pozadavkyRozsah) ?></h2>
            </div>
        </div>

        <?php if ($pozadavkyNove === []): ?>
            <p class="hr_empty_state">Žádné zadané požadavky</p>
        <?php else: ?>
            <div class="hr_table_wrap">
                <table class="hr_table">
                    <thead>
                        <tr>
                            <th class="hr_table_cell hr_table_head">Požadavek</th>
                            <?php if ($pozadavkyZobraziPobocku): ?><th class="hr_table_cell hr_table_head">Pobočka</th><?php endif; ?>
                            <th class="hr_table_cell hr_table_head">Pozice</th>
                            <th class="hr_table_cell hr_table_head">Upřesnění</th>
                            <th class="hr_table_cell hr_table_head">Zadáno</th>
                            <?php if ($pozadavkyMuzeZadat): ?><th class="hr_table_cell hr_table_head">Akce</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pozadavkyNove as $pozadavek): ?>
                            <tr>
                                <td class="hr_table_cell">#<?= h($pozadavek['id_pozadavek']) ?></td>
                                <?php if ($pozadavkyZobraziPobocku): ?><td class="hr_table_cell"><?= h((string)$pozadavek['pobocka']) ?></td><?php endif; ?>
                                <td class="hr_table_cell"><?= h($pozadavek['slot']) ?></td>
                                <td class="hr_table_cell"><?= h($pozadavek['upresneni']) ?></td>
                                <td class="hr_table_cell"><?= h(hr_format_date((string)$pozadavek['zadano'])) ?></td>
                                <?php if ($pozadavkyMuzeZadat): ?>
                                    <td class="hr_table_cell">
                                        <?php if ($pozadavkyJeAdmin || (int)$pozadavek['zadal'] === $pozadavkyPersonId || ($pozadavkyJeVedouci && (int)$pozadavek['id_pob'] === (int)$pozadavkyMainPobocka['id_pob'])): ?>
                                            <form method="post" action="<?= h(cb_root_url('index.php?m=hr&page=pozadavky')) ?>" class="hr_row_action_form">
                                                <input type="hidden" name="akce" value="zrusit">
                                                <input type="hidden" name="id_pozadavek" value="<?= h($pozadavek['id_pozadavek']) ?>">
                                                <button class="hr_delete_button" type="submit" title="Odstranit" aria-label="Odstranit">×</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="hr_panel">
        <div class="hr_panel_header">
            <div>
                <h2 class="hr_panel_title">Vyřešené požadavky <?= h($pozadavkyRozsah) ?></h2>
            </div>
        </div>

        <?php if ($pozadavkyVyresene === []): ?>
            <p class="hr_empty_state">Bez záznamu</p>
        <?php else: ?>
            <div class="hr_table_wrap">
                <table class="hr_table">
                    <thead>
                        <tr>
                            <th class="hr_table_cell hr_table_head">Požadavek</th>
                            <?php if ($pozadavkyZobraziPobocku): ?><th class="hr_table_cell hr_table_head">Pobočka</th><?php endif; ?>
                            <th class="hr_table_cell hr_table_head">Pozice</th>
                            <th class="hr_table_cell hr_table_head">Upřesnění</th>
                            <th class="hr_table_cell hr_table_head">Zadáno</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pozadavkyVyresene as $pozadavek): ?>
                            <tr>
                                <td class="hr_table_cell">#<?= h($pozadavek['id_pozadavek']) ?></td>
                                <?php if ($pozadavkyZobraziPobocku): ?><td class="hr_table_cell"><?= h((string)$pozadavek['pobocka']) ?></td><?php endif; ?>
                                <td class="hr_table_cell"><?= h($pozadavek['slot']) ?></td>
                                <td class="hr_table_cell"><?= h($pozadavek['upresneni']) ?></td>
                                <td class="hr_table_cell"><?= h(hr_format_date((string)$pozadavek['zadano'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($pozadavkyExpirovane !== []): ?>
        <section class="hr_panel">
            <div class="hr_panel_header">
                <div>
                    <h2 class="hr_panel_title">Expirované požadavky <?= h($pozadavkyRozsah) ?> - uzavřené systémem pro neaktivitu</h2>
                </div>
            </div>

            <div class="hr_table_wrap">
                <table class="hr_table">
                    <thead>
                        <tr>
                            <th class="hr_table_cell hr_table_head">Požadavek</th>
                            <?php if ($pozadavkyZobraziPobocku): ?><th class="hr_table_cell hr_table_head">Pobočka</th><?php endif; ?>
                            <th class="hr_table_cell hr_table_head">Pozice</th>
                            <th class="hr_table_cell hr_table_head">Upřesnění</th>
                            <th class="hr_table_cell hr_table_head">Zadáno</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pozadavkyExpirovane as $pozadavek): ?>
                            <tr>
                                <td class="hr_table_cell">#<?= h($pozadavek['id_pozadavek']) ?></td>
                                <?php if ($pozadavkyZobraziPobocku): ?><td class="hr_table_cell"><?= h((string)$pozadavek['pobocka']) ?></td><?php endif; ?>
                                <td class="hr_table_cell"><?= h($pozadavek['slot']) ?></td>
                                <td class="hr_table_cell"><?= h($pozadavek['upresneni']) ?></td>
                                <td class="hr_table_cell"><?= h(hr_format_date((string)$pozadavek['zadano'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <section class="hr_panel">
        <div class="hr_panel_header">
            <div>
                <h2 class="hr_panel_title">Požadavky zrušené zadavatelem <?= h($pozadavkyRozsah) ?></h2>
            </div>
        </div>

        <?php if ($pozadavkyZrusene === []): ?>
            <p class="hr_empty_state">Bez záznamu</p>
        <?php else: ?>
            <div class="hr_table_wrap">
                <table class="hr_table">
                    <thead>
                        <tr>
                            <th class="hr_table_cell hr_table_head">Požadavek</th>
                            <?php if ($pozadavkyZobraziPobocku): ?><th class="hr_table_cell hr_table_head">Pobočka</th><?php endif; ?>
                            <th class="hr_table_cell hr_table_head">Pozice</th>
                            <th class="hr_table_cell hr_table_head">Upřesnění</th>
                            <th class="hr_table_cell hr_table_head">Zadáno</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pozadavkyZrusene as $pozadavek): ?>
                            <tr>
                                <td class="hr_table_cell">#<?= h($pozadavek['id_pozadavek']) ?></td>
                                <?php if ($pozadavkyZobraziPobocku): ?><td class="hr_table_cell"><?= h((string)$pozadavek['pobocka']) ?></td><?php endif; ?>
                                <td class="hr_table_cell"><?= h($pozadavek['slot']) ?></td>
                                <td class="hr_table_cell"><?= h($pozadavek['upresneni']) ?></td>
                                <td class="hr_table_cell"><?= h(hr_format_date((string)$pozadavek['zadano'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
