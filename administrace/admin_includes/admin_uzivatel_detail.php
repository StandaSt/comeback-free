<?php
declare(strict_types=1);

/* HTML detailu účtu user; používá jej plná stránka i lokální AJAXové rozbalení řádku. */

function cb_admin_uzivatele_navrat_url(array $source): string
{
    $sortKeys = ['id', 'uzivatel', 'kontakt', 'firma', 'role', 'slot', 'pobocky', 'stav'];
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

function cb_admin_uzivatel_detail_html(array $detail, array $lists): string
{
    ob_start();
    ?>
    <div class="admin_user_detail_transition"><div class="admin_users_form">
        <p>Jméno, telefon, role, sloty a pobočky se spravují pouze v HR.</p>
        <p><?= h(trim((string)$detail['jmeno'] . ' ' . (string)$detail['prijmeni'])) ?> · přihlašovací e-mail: <?= h((string)$detail['email']) ?></p>
        <p>Stav přístupu: <strong><?= !empty($detail['aktivni']) ? 'aktivní osoba v HR' : 'neaktivní osoba v HR' ?></strong></p>
        <a href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$detail['id_person']) . '&upravit=1')) ?>">Upravit v HR</a>
    </div></div>
    <?php
    return (string)ob_get_clean();
}
