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
<section class="panel">
    <div class="panel-header">
        <div>
            <h2 class="hr-request-title">Zadání požadavku</h2>
        </div>
        <?php if ($pozadavkyUlozeno): ?>
            <p class="notice success hr-request-notice">Požadavek byl uložen.</p>
        <?php elseif ($pozadavkyZrusen): ?>
            <p class="notice success hr-request-notice">Požadavek byl odstraněn.</p>
        <?php endif; ?>
    </div>

    <?php if ($pozadavkyMuzeZadat): ?>
        <form class="hr-form hr-request-form" method="post" action="" data-hr-request-form>
            <input type="hidden" name="akce" value="vytvorit">
            <span>Požaduji</span>
            <select class="hr-request-select" name="pocet">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <option value="<?= h($i) ?>"<?= $i === 1 ? ' selected' : '' ?>><?= h($i) ?></option>
                <?php endfor; ?>
            </select>
            <span>zaměstnance na pozici</span>
            <select class="hr-request-select" name="id_slot" data-hr-request-slot required>
                <option value="">Vyber</option>
                <option value="1">instor</option>
                <option value="2">kurýr</option>
            </select>
            <?php if ($pozadavkyJeAdmin): ?>
                <span>pro pobočku</span>
                <select class="hr-request-select" name="id_pob" required>
                    <option value="">Vyberte</option>
                    <?php foreach ($pozadavkyPobocky as $pobocka): ?>
                        <option value="<?= h($pobocka['id']) ?>"><?= h($pobocka['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <span>pro pobočku <?= h($pozadavkyMainPobocka['nazev']) ?>.</span>
            <?php endif; ?>
            <span>Poznámka:</span>
            <input class="hr-note-input" type="text" name="upresneni" maxlength="500" placeholder="Zde upřesněte, třeba termín nástupu.">
            <button class="primary-button hr-request-submit" type="submit">Zadat požadavek</button>
        </form>
    <?php elseif ($pozadavkyJenCteni): ?>
        <p class="empty-state">Požadavky máte dostupné pouze pro čtení.</p>
    <?php else: ?>
        <p class="empty-state">Na tuto stránku nemáte přístup.</p>
    <?php endif; ?>
</section>

<?php if ($pozadavkyMuzeCist): ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Nové požadavky <?= h($pozadavkyRozsah) ?></h2>
            </div>
        </div>

        <?php if ($pozadavkyNove === []): ?>
            <p class="empty-state">Žádné zadané požadavky</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Požadavek</th>
                            <?php if ($pozadavkyZobraziPobocku): ?><th>Pobočka</th><?php endif; ?>
                            <th>Pozice</th>
                            <th>Upřesnění</th>
                            <th>Zadáno</th>
                            <?php if ($pozadavkyMuzeZadat): ?><th>Akce</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pozadavkyNove as $pozadavek): ?>
                            <tr>
                                <td>#<?= h($pozadavek['id_pozadavek']) ?></td>
                                <?php if ($pozadavkyZobraziPobocku): ?><td><?= h((string)$pozadavek['pobocka']) ?></td><?php endif; ?>
                                <td><?= h($pozadavek['slot']) ?></td>
                                <td><?= h($pozadavek['upresneni']) ?></td>
                                <td><?= h(hr_format_date((string)$pozadavek['zadano'])) ?></td>
                                <?php if ($pozadavkyMuzeZadat): ?>
                                    <td>
                                        <?php if ($pozadavkyJeAdmin || (int)$pozadavek['zadal'] === $pozadavkyPersonId || ($pozadavkyJeVedouci && (int)$pozadavek['id_pob'] === (int)$pozadavkyMainPobocka['id_pob'])): ?>
                                            <form method="post" action="" class="hr-row-action-form">
                                                <input type="hidden" name="akce" value="zrusit">
                                                <input type="hidden" name="id_pozadavek" value="<?= h($pozadavek['id_pozadavek']) ?>">
                                                <button class="hr-delete-button" type="submit" title="Odstranit" aria-label="Odstranit">×</button>
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

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Vyřešené požadavky <?= h($pozadavkyRozsah) ?></h2>
            </div>
        </div>

        <?php if ($pozadavkyVyresene === []): ?>
            <p class="empty-state">Bez záznamu</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Požadavek</th>
                            <?php if ($pozadavkyZobraziPobocku): ?><th>Pobočka</th><?php endif; ?>
                            <th>Pozice</th>
                            <th>Upřesnění</th>
                            <th>Zadáno</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pozadavkyVyresene as $pozadavek): ?>
                            <tr>
                                <td>#<?= h($pozadavek['id_pozadavek']) ?></td>
                                <?php if ($pozadavkyZobraziPobocku): ?><td><?= h((string)$pozadavek['pobocka']) ?></td><?php endif; ?>
                                <td><?= h($pozadavek['slot']) ?></td>
                                <td><?= h($pozadavek['upresneni']) ?></td>
                                <td><?= h(hr_format_date((string)$pozadavek['zadano'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($pozadavkyExpirovane !== []): ?>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Expirované požadavky <?= h($pozadavkyRozsah) ?> - uzavřené systémem pro neaktivitu</h2>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Požadavek</th>
                            <?php if ($pozadavkyZobraziPobocku): ?><th>Pobočka</th><?php endif; ?>
                            <th>Pozice</th>
                            <th>Upřesnění</th>
                            <th>Zadáno</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pozadavkyExpirovane as $pozadavek): ?>
                            <tr>
                                <td>#<?= h($pozadavek['id_pozadavek']) ?></td>
                                <?php if ($pozadavkyZobraziPobocku): ?><td><?= h((string)$pozadavek['pobocka']) ?></td><?php endif; ?>
                                <td><?= h($pozadavek['slot']) ?></td>
                                <td><?= h($pozadavek['upresneni']) ?></td>
                                <td><?= h(hr_format_date((string)$pozadavek['zadano'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Požadavky zrušené zadavatelem <?= h($pozadavkyRozsah) ?></h2>
            </div>
        </div>

        <?php if ($pozadavkyZrusene === []): ?>
            <p class="empty-state">Bez záznamu</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Požadavek</th>
                            <?php if ($pozadavkyZobraziPobocku): ?><th>Pobočka</th><?php endif; ?>
                            <th>Pozice</th>
                            <th>Upřesnění</th>
                            <th>Zadáno</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pozadavkyZrusene as $pozadavek): ?>
                            <tr>
                                <td>#<?= h($pozadavek['id_pozadavek']) ?></td>
                                <?php if ($pozadavkyZobraziPobocku): ?><td><?= h((string)$pozadavek['pobocka']) ?></td><?php endif; ?>
                                <td><?= h($pozadavek['slot']) ?></td>
                                <td><?= h($pozadavek['upresneni']) ?></td>
                                <td><?= h(hr_format_date((string)$pozadavek['zadano'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
