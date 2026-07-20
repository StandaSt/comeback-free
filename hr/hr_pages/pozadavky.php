<?php
declare(strict_types=1);

$pozadavkyUser = $_SESSION['cb_user'] ?? [];
$pozadavkyRoleId = is_array($pozadavkyUser) ? (int)($pozadavkyUser['id_role'] ?? 0) : 0;
$pozadavkyUserId = is_array($pozadavkyUser) ? (int)($pozadavkyUser['id_user'] ?? 0) : 0;
$pozadavkyMuzeZadat = in_array($pozadavkyRoleId, [1, 5], true);
$pozadavkyMainPobocka = [];
$pozadavkyUlozeno = !empty($_SESSION['hr_pozadavek_ulozeno']);
$pozadavkyZrusen = !empty($_SESSION['hr_pozadavek_zrusen']);
unset($_SESSION['hr_pozadavek_ulozeno']);
unset($_SESSION['hr_pozadavek_zrusen']);

if ($pozadavkyMuzeZadat) {
    $pozadavkyMainPobocka = hr_nacti_hlavni_pobocku_uzivatele($db, $pozadavkyUserId);
}

$pozadavkyNove = $pozadavkyMuzeZadat
    ? hr_nacti_nove_pozadavky_pobocky($db, (int)$pozadavkyMainPobocka['id_pob'])
    : [];
$pozadavkyVyresene = $pozadavkyMuzeZadat
    ? hr_nacti_pozadavky_pobocky_podle_stavu($db, (int)$pozadavkyMainPobocka['id_pob'], 2)
    : [];
$pozadavkyExpirovane = $pozadavkyMuzeZadat
    ? hr_nacti_pozadavky_pobocky_podle_stavu($db, (int)$pozadavkyMainPobocka['id_pob'], 3)
    : [];
$pozadavkyZrusene = $pozadavkyMuzeZadat
    ? hr_nacti_pozadavky_pobocky_podle_stavu($db, (int)$pozadavkyMainPobocka['id_pob'], 0)
    : [];
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
            <span>pro pobočku <?= h($pozadavkyMainPobocka['nazev']) ?>.</span>
            <span>Poznámka:</span>
            <input class="hr-note-input" type="text" name="upresneni" maxlength="500" placeholder="Zde upřesněte, třeba termín nástupu.">
            <button class="primary-button hr-request-submit" type="submit">Zadat požadavek</button>
        </form>
    <?php else: ?>
        <p class="empty-state">Formulář je dostupný pouze pro admina a vedoucí směny.</p>
    <?php endif; ?>
</section>

<?php if ($pozadavkyMuzeZadat): ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Nové požadavky pobočky <?= h($pozadavkyMainPobocka['nazev']) ?></h2>
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
                            <th>Pozice</th>
                            <th>Upřesnění</th>
                            <th>Zadáno</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pozadavkyNove as $pozadavek): ?>
                            <tr>
                                <td>#<?= h($pozadavek['id_hr_pozadavek']) ?></td>
                                <td><?= h($pozadavek['slot']) ?></td>
                                <td><?= h($pozadavek['upresneni']) ?></td>
                                <td><?= h(hr_format_date((string)$pozadavek['zadano'])) ?></td>
                                <td>
                                    <?php if ((int)$pozadavek['zadal'] === $pozadavkyUserId || ($pozadavkyRoleId === 5 && (int)$pozadavek['id_pob'] === (int)$pozadavkyMainPobocka['id_pob'])): ?>
                                        <form method="post" action="" class="hr-row-action-form">
                                            <input type="hidden" name="akce" value="zrusit">
                                            <input type="hidden" name="id_hr_pozadavek" value="<?= h($pozadavek['id_hr_pozadavek']) ?>">
                                            <button class="hr-delete-button" type="submit" title="Odstranit" aria-label="Odstranit">×</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
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
                <h2>Vyřešené požadavky pobočky <?= h($pozadavkyMainPobocka['nazev']) ?></h2>
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
                            <th>Pozice</th>
                            <th>Upřesnění</th>
                            <th>Zadáno</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pozadavkyVyresene as $pozadavek): ?>
                            <tr>
                                <td>#<?= h($pozadavek['id_hr_pozadavek']) ?></td>
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
                    <h2>Expirované požadavky - uzavřené systémem pro neaktivitu</h2>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Požadavek</th>
                            <th>Pozice</th>
                            <th>Upřesnění</th>
                            <th>Zadáno</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pozadavkyExpirovane as $pozadavek): ?>
                            <tr>
                                <td>#<?= h($pozadavek['id_hr_pozadavek']) ?></td>
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
                <h2>Požadavky zrušené zadavatelem</h2>
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
                            <th>Pozice</th>
                            <th>Upřesnění</th>
                            <th>Zadáno</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pozadavkyZrusene as $pozadavek): ?>
                            <tr>
                                <td>#<?= h($pozadavek['id_hr_pozadavek']) ?></td>
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
