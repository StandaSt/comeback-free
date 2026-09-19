<?php
declare(strict_types=1);

/* HTML detailu účtu user; používá jej plná stránka i lokální AJAXové rozbalení řádku. */

function cb_admin_uzivatele_navrat_url(array $source): string
{
    $sortKeys = ['id', 'uzivatel', 'kontakt', 'firma', 'role', 'slot', 'pobocky', 'zdroj', 'stav'];
    $perOptions = [20, 50, 100, 500];
    $perPage = (int)($source['usr_per'] ?? 50);
    $params = [
        'm' => 'administrace',
        'page' => 'uzivatele',
        'usr_f' => cb_admin_uzivatele_filtry($source),
        'usr_per' => in_array($perPage, $perOptions, true) ? $perPage : 50,
        'usr_p' => max(1, (int)($source['usr_p'] ?? 1)),
        'usr_sort' => in_array((string)($source['usr_sort'] ?? ''), $sortKeys, true) ? (string)$source['usr_sort'] : 'uzivatel',
        'usr_dir' => strtolower((string)($source['usr_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
    ];
    $detailId = (int)($source['usr_id'] ?? 0);
    if ($detailId > 0) {
        $params['usr_id'] = $detailId;
    }
    return cb_root_url('index.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
}

function cb_admin_uzivatel_pobocky_html(array $pobockyByFirma, ?array $user = null, string $formId = ''): string
{
    $idFirma = (int)($user['id_firma'] ?? 0);
    $selected = is_array($user) ? array_flip($user['pobocky']) : [];
    $pobAll = !empty($user['pob_all']);
    $main = (int)($user['id_pob_hlavni'] ?? -1);

    ob_start();
    ?>
    <div class="admin_user_branch_picker" data-admin-user-branches>
        <label class="admin_user_branch_all"><input type="checkbox" name="pob_all" value="1"<?= $formId !== '' ? ' form="' . h($formId) . '"' : '' ?><?= $pobAll ? ' checked' : '' ?> data-admin-user-pob-all> Všechny aktivní pobočky firmy</label>
        <div class="admin_user_branch_choices" data-admin-user-pob-choices>
            <?php foreach ($pobockyByFirma as $firmaId => $pobocky): ?>
                <div data-admin-user-pob-firma="<?= h((string)$firmaId) ?>"<?= $idFirma > 0 && $idFirma !== $firmaId ? ' hidden' : '' ?>>
                    <?php foreach ($pobocky as $pobocka): $idPob = (int)$pobocka['id_pob']; ?>
                        <label><input type="checkbox" name="id_pob[]" value="<?= h((string)$idPob) ?>"<?= $formId !== '' ? ' form="' . h($formId) . '"' : '' ?> data-admin-user-pob-option<?= isset($selected[$idPob]) || ($pobAll && $idFirma === $firmaId) ? ' checked' : '' ?>> <?= h((string)$pobocka['nazev']) ?></label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <label>Hlavní pobočka: <select name="id_pob_hlavni"<?= $formId !== '' ? ' form="' . h($formId) . '"' : '' ?> data-admin-user-main-pob><option value="">Bez hlavní pobočky</option>
            <?php foreach ($pobockyByFirma as $firmaId => $pobocky): foreach ($pobocky as $pobocka): ?>
                <option value="<?= h((string)$pobocka['id_pob']) ?>" data-admin-user-main-firma="<?= h((string)$firmaId) ?>"<?= $main === (int)$pobocka['id_pob'] ? ' selected' : '' ?>><?= h((string)$pobocka['nazev']) ?></option>
            <?php endforeach; endforeach; ?>
        </select></label>
    </div>
    <?php
    return (string)ob_get_clean();
}

function cb_admin_uzivatel_sloty_html(array $slotOptions, array $selectedIds = [], string $formId = ''): string
{
    $selected = array_flip(array_map('intval', $selectedIds));
    ob_start();
    ?>
    <div class="admin_user_slot_picker">
        <?php foreach ($slotOptions as $slot): $idSlot = (int)($slot['id'] ?? 0); ?>
            <label><input type="checkbox" name="id_slot[]" value="<?= h((string)$idSlot) ?>"<?= $formId !== '' ? ' form="' . h($formId) . '"' : '' ?><?= isset($selected[$idSlot]) ? ' checked' : '' ?>> <?= h(cb_admin_uzivatele_sloty_text((string)($slot['nazev'] ?? ''))) ?></label>
        <?php endforeach; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

function cb_admin_uzivatel_detail_html(array $detail, array $lists): string
{
    $pobockyByFirma = [];
    foreach ($lists['pobocky'] as $pobocka) {
        $pobockyByFirma[(int)$pobocka['id_firma']][] = $pobocka;
    }
    $editFormId = 'admin_user_edit_' . (int)$detail['id_user'];
    $selectedRole = (int)($detail['role'][0] ?? 0);

    ob_start();
    ?>
    <div class="admin_user_detail_transition"><div class="admin_users_form" data-admin-user-form>
        <p>Upravujete pouze údaje účtu user.</p>
        <label>Firma: <select form="<?= h($editFormId) ?>" name="id_firma" required data-admin-user-firma><?php foreach ($lists['firmy'] as $firma): ?><option value="<?= h((string)$firma['id']) ?>"<?= (int)$detail['id_firma'] === (int)$firma['id'] ? ' selected' : '' ?>><?= h((string)$firma['nazev']) ?></option><?php endforeach; ?></select></label>
        <label>Jméno: <input form="<?= h($editFormId) ?>" name="jmeno" maxlength="60" value="<?= h((string)$detail['jmeno']) ?>" required></label><label>Příjmení: <input form="<?= h($editFormId) ?>" name="prijmeni" maxlength="80" value="<?= h((string)$detail['prijmeni']) ?>" required></label>
        <label>E-mail: <input form="<?= h($editFormId) ?>" type="email" name="email" maxlength="150" value="<?= h((string)$detail['email']) ?>" required></label><label>Telefon: <input form="<?= h($editFormId) ?>" name="telefon" maxlength="30" value="<?= h((string)$detail['telefon']) ?>"></label>
        <label>Role: <select form="<?= h($editFormId) ?>" name="id_role" required><?php foreach ($lists['role'] as $role): ?><option value="<?= h((string)$role['id']) ?>"<?= $selectedRole === (int)$role['id'] ? ' selected' : '' ?>><?= h((string)$role['nazev']) ?></option><?php endforeach; ?></select></label>
        <fieldset><legend>Sloty:</legend><?= cb_admin_uzivatel_sloty_html($lists['sloty'], (array)($detail['slot_ids'] ?? []), $editFormId) ?></fieldset>
        <label><input form="<?= h($editFormId) ?>" type="checkbox" name="aktivni" value="1"<?= !empty($detail['aktivni']) ? ' checked' : '' ?>> Aktivní účet</label>
        <fieldset><legend>Pobočky:</legend><?= cb_admin_uzivatel_pobocky_html($pobockyByFirma, $detail, $editFormId) ?></fieldset><button form="<?= h($editFormId) ?>" type="submit">Uložit změny</button>
    </div></div>
    <?php
    return (string)ob_get_clean();
}

function cb_admin_uzivatel_edit_form_html(int $idUser, array $source = []): string
{
    $formId = 'admin_user_edit_' . $idUser;
    return '<form id="' . h($formId) . '" method="post" action="' . h(cb_admin_uzivatele_navrat_url($source)) . '" data-admin-user-edit-form><input type="hidden" name="cb_action" value="admin_uzivatel_ulozit"><input type="hidden" name="id_user" value="' . h((string)$idUser) . '"></form>';
}
