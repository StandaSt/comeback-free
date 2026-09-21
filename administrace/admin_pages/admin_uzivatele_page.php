<?php
declare(strict_types=1);

/* Jednotná obsluha filtrů je provoz/js/filtry.js. */

/* Přehled, založení a editace účtů user. Údaje person se zde nikdy nemění. */
if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(107)) {
    http_response_code(403);
    echo '<section class="blok"><h2 class="blok_title">Přístup zamítnut</h2><p>Nemáte právo spravovat uživatele.</p></section>';
    return;
}

$notice = $_SESSION['cb_admin_uzivatele_notice'] ?? null;
unset($_SESSION['cb_admin_uzivatele_notice']);
$source = $_GET;
$detailId = (int)($source['usr_id'] ?? 0);
$data = cb_admin_uzivatele_nacti(db(), $source);
$lists = cb_admin_uzivatele_ciselniky(db());
$detail = $detailId > 0 ? cb_admin_uzivatel_detail(db(), $detailId) : null;
$roleOptions = $lists['role'];
$csrfToken = cb_admin_uzivatele_csrf_token();
$pobockyByFirma = [];
foreach ($lists['pobocky'] as $pobocka) {
    $pobockyByFirma[(int)$pobocka['id_firma']][] = $pobocka;
}
$url = static function (array $change = []) use ($data): string {
    return cb_root_url('index.php?m=administrace&page=uzivatele&' . http_build_query(array_merge([
        'usr_f' => $data['filters'], 'usr_per' => $data['per_page'], 'usr_p' => $data['page'],
        'usr_sort' => $data['sort'], 'usr_dir' => $data['dir'],
    ], $change), '', '&', PHP_QUERY_RFC3986));
};
$sortUrl = static function (string $key) use ($url, $data): string {
    return $url(['usr_sort' => $key, 'usr_dir' => $data['sort'] === $key && $data['dir'] === 'asc' ? 'desc' : 'asc', 'usr_p' => 1]);
};
?>
<section class="blok admin_users cb_table_panel">
    <?php if (is_array($notice)): ?><p class="admin_script_result<?= !empty($notice['success']) ? '' : ' is-error' ?>"><?= h((string)$notice['message']) ?></p><?php endif; ?>

    <details class="admin_users_create">
        <summary>+ Přidat uživatele</summary>
        <form method="post" action="<?= h(cb_root_url('index.php?m=administrace&page=uzivatele')) ?>" class="admin_users_form" data-admin-user-form>
            <input type="hidden" name="cb_action" value="admin_uzivatel_vytvorit">
            <label>Firma: <select name="id_firma" required data-admin-user-firma><?php foreach ($lists['firmy'] as $firma): ?><option value="<?= h((string)$firma['id']) ?>"><?= h((string)$firma['nazev']) ?></option><?php endforeach; ?></select></label>
            <label>Jméno: <input name="jmeno" maxlength="60" required></label>
            <label>Příjmení: <input name="prijmeni" maxlength="80" required></label>
            <label>E-mail: <input type="email" name="email" maxlength="150" required></label>
            <label>Telefon: <input name="telefon" maxlength="30"></label>
            <label>Role: <select name="id_role" required><option value="">Vyberte roli</option><?php foreach ($roleOptions as $role): ?><option value="<?= h((string)$role['id']) ?>"><?= h((string)$role['nazev']) ?></option><?php endforeach; ?></select></label>
            <fieldset><legend>Sloty:</legend><?= cb_admin_uzivatel_sloty_html($lists['sloty']) ?></fieldset>
            <fieldset><legend>Pobočky:</legend><?= cb_admin_uzivatel_pobocky_html($pobockyByFirma) ?></fieldset>
            <button type="submit">Založit a odeslat pozvánku</button>
        </form>
    </details>

    <form method="get" action="<?= h(cb_root_url('index.php')) ?>" class="cb_table_view" autocomplete="off">
        <input type="hidden" name="m" value="administrace">
        <input type="hidden" name="page" value="uzivatele">
        <input type="hidden" name="usr_sort" value="<?= h($data['sort']) ?>">
        <input type="hidden" name="usr_dir" value="<?= h($data['dir']) ?>">
        <input type="hidden" name="usr_per" value="<?= h((string)$data['per_page']) ?>">
        <input type="hidden" name="usr_p" value="1">
        <div class="cb_table_wrap table-wrap">
            <table class="cb_table admin_users_table">
                <colgroup><col style="width:72px"><col span="8"><col style="width:110px"></colgroup>
                <thead>
                    <tr class="admin_users_filter_row filter-row">
                        <th><input class="filter-input" name="usr_f[id]" value="<?= h($data['filters']['id']) ?>" aria-label="Filtrovat ID"></th>
                        <th><input class="filter-input" name="usr_f[uzivatel]" value="<?= h($data['filters']['uzivatel']) ?>" aria-label="Filtrovat uživatele"></th>
                        <th><input class="filter-input" name="usr_f[kontakt]" value="<?= h($data['filters']['kontakt']) ?>" aria-label="Filtrovat kontakt"></th>
                        <th><select class="filter-input" name="usr_f[firma]"><option value="0">Vše</option><?php foreach ($lists['firmy'] as $firma): ?><option value="<?= h((string)$firma['id']) ?>"<?= (int)$data['filters']['firma'] === (int)$firma['id'] ? ' selected' : '' ?>><?= h((string)$firma['nazev']) ?></option><?php endforeach; ?></select></th>
                        <th><select class="filter-input" name="usr_f[role]"><option value="0">Vše</option><?php foreach ($roleOptions as $role): ?><option value="<?= h((string)$role['id']) ?>"<?= (int)$data['filters']['role'] === (int)$role['id'] ? ' selected' : '' ?>><?= h((string)$role['nazev']) ?></option><?php endforeach; ?></select></th>
                        <th><select class="filter-input" name="usr_f[slot]"><option value="-1">Vše</option><?php foreach ($lists['sloty'] as $slot): ?><option value="<?= h((string)$slot['id']) ?>"<?= (int)$data['filters']['slot'] === (int)$slot['id'] ? ' selected' : '' ?>><?= h(cb_admin_uzivatele_sloty_text((string)$slot['nazev'])) ?></option><?php endforeach; ?></select></th>
                        <th><select class="filter-input" name="usr_f[pobocka]"><option value="-1">Vše</option><?php foreach ($lists['pobocky'] as $pobocka): ?><option value="<?= h((string)$pobocka['id_pob']) ?>"<?= (int)$data['filters']['pobocka'] === (int)$pobocka['id_pob'] ? ' selected' : '' ?>><?= h((string)$pobocka['nazev']) ?></option><?php endforeach; ?></select></th>
                        <th><select class="filter-input" name="usr_f[zdroj]"><option value="vse">Vše</option><option value="1"<?= $data['filters']['zdroj'] === '1' ? ' selected' : '' ?>>Směny</option><option value="2"<?= $data['filters']['zdroj'] === '2' ? ' selected' : '' ?>>Manuál</option><option value="3"<?= $data['filters']['zdroj'] === '3' ? ' selected' : '' ?>>HR</option></select></th>
                        <th><select class="filter-input" name="usr_f[stav]"><option value="vse">Vše</option><option value="aktivni"<?= $data['filters']['stav'] === 'aktivni' ? ' selected' : '' ?>>Aktivní</option><option value="neaktivni"<?= $data['filters']['stav'] === 'neaktivni' ? ' selected' : '' ?>>Neaktivní</option></select></th>
                        <th aria-label="Akce"></th>
                    </tr>
                    <tr>
                        <?php foreach (['id'=>'ID','uzivatel'=>'Uživatel','kontakt'=>'Kontakt','firma'=>'Firma','role'=>'Role','slot'=>'Slot','pobocky'=>'Pobočky','zdroj'=>'Zdroj','stav'=>'Stav'] as $key => $label): $arrow = $data['sort'] === $key ? ($data['dir'] === 'asc' ? '↑' : '↓') : '↕'; ?>
                            <th<?= $key === 'id' ? ' class="cb_table_number"' : '' ?>><a href="<?= h($sortUrl($key)) ?>"><?= h($label) ?> <span><?= h($arrow) ?></span></a></th>
                        <?php endforeach; ?>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['rows'] as $user):
                        $isDetail = is_array($detail) && (int)$detail['id_user'] === (int)$user['id_user'];
                        $mainBranch = trim((string)$user['pobocka_main']);
                        $branchCount = max(0, (int)$user['pobocky_pocet']);
                        if ($mainBranch !== '') {
                            $branchText = $mainBranch;
                            $otherBranches = max(0, $branchCount - 1);
                            if ($otherBranches > 0) { $branchText .= ' + ' . $otherBranches; }
                        } else {
                            $branchText = 'MAIN není + ' . $branchCount;
                        }
                        $inactiveTitle = empty($user['aktivni']) ? 'Osoba je v HR neaktivní a nemůže se přihlásit do IS.' : '';
                    ?>
                        <tr data-admin-user-row="<?= h((string)$user['id_user']) ?>">
                            <td class="cb_table_number"><?= h((string)$user['id_user']) ?></td>
                            <td><a href="<?= h($url(['usr_id' => $isDetail ? null : (int)$user['id_user']])) ?>" data-admin-user-detail data-cb-filter-ignore="1" aria-expanded="<?= $isDetail ? 'true' : 'false' ?>"><?= h(trim((string)$user['prijmeni'] . ' ' . (string)$user['jmeno'])) ?></a></td>
                            <td><?= h((string)$user['email']) ?><br><?= h((string)$user['telefon']) ?></td>
                            <td><?= h((string)$user['obchodni_jmeno']) ?></td>
                            <td><?= h((string)$user['role']) ?></td>
                            <td class="admin_users_slot_cell"><?php if ((string)$user['slot'] === ''): ?>—<?php else: ?><?php foreach (explode(', ', (string)$user['slot']) as $slotLabel): ?><span><?= h($slotLabel) ?></span><?php endforeach; ?><?php endif; ?></td>
                            <td><?= h($branchText) ?></td>
                            <td><?= match ((int)$user['zdroj']) { 2 => 'Manuál', 3 => 'HR', default => 'Směny' } ?></td>
                            <td<?= $inactiveTitle !== '' ? ' title="' . h($inactiveTitle) . '"' : '' ?>><?= !empty($user['aktivni']) ? (!empty($user['ma_heslo']) ? 'Aktivní' : 'Aktivní – bez hesla') : 'Neaktivní' ?></td>
                            <td class="admin_users_action_cell">—</td>
                        </tr>
                        <?php if ($isDetail): ?>
                            <tr class="admin_user_detail_row is-open" data-admin-user-detail-row="<?= h((string)$detail['id_user']) ?>"><td colspan="10"><?= cb_admin_uzivatel_detail_html($detail, $lists) ?></td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($data['rows'] === []): ?><tr><td colspan="10">Žádný uživatel neodpovídá zvolenému filtru.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="cb_table_footer list-bottom">
            <div class="cb_table_footer_summary">Zobrazuji řádky <?= h((string)$data['first_row']) ?>-<?= h((string)$data['last_row']) ?> z celkem <strong><?= h((string)$data['total']) ?></strong></div>
            <div class="pagination-icon">
                <?php $previous = $data['page'] <= 1; $next = $data['page'] >= $data['pages']; ?>
                <a class="icon-btn<?= $previous ? ' disabled' : '' ?>" href="<?= $previous ? '#' : h($url(['usr_p' => 1])) ?>">«</a><a class="icon-btn<?= $previous ? ' disabled' : '' ?>" href="<?= $previous ? '#' : h($url(['usr_p' => $data['page'] - 1])) ?>">‹</a>
                <span class="page-current"><?= h((string)$data['page']) ?> / <?= h((string)$data['pages']) ?></span>
                <a class="icon-btn<?= $next ? ' disabled' : '' ?>" href="<?= $next ? '#' : h($url(['usr_p' => $data['page'] + 1])) ?>">›</a><a class="icon-btn<?= $next ? ' disabled' : '' ?>" href="<?= $next ? '#' : h($url(['usr_p' => $data['pages']])) ?>">»</a>
            </div>
            <label class="cb_table_footer_size">Zobrazovat <select name="usr_per" class="filter-input"><?php foreach ($data['per_options'] as $option): ?><option value="<?= h((string)$option) ?>"<?= $data['per_page'] === $option ? ' selected' : '' ?>><?= h((string)$option) ?> řádků</option><?php endforeach; ?></select></label>
        </div>
    </form>
    <?php if (is_array($detail)): ?><?= cb_admin_uzivatel_edit_form_html((int)$detail['id_user'], $source) ?><?php endif; ?>
</section>
