<?php
declare(strict_types=1);

/*
 * Datova vrstva kategorie Prihlaseni a 2FA v Administraci.
 * Porovnava neuspesne pokusy s uctem, uspesnym loginem a znamym zarizenim.
 * Heslo z user_bad_login tato vrstva zamerne nikdy necte ani nevraci.
 */

/** Omezi pocet radku na hodnoty povolene administracnim prehledem. */
function cb_admin_prihlaseni_limit(mixed $value): int
{
    $limit = (int)$value;
    return in_array($limit, [20, 50, 100, 250], true) ? $limit : 50;
}

/**
 * Nacte posledni neuspesne pokusy a dohleda nasledny uspesny login.
 * Vysledek je pouze podklad pro orientacni hodnoceni, nikoli dukaz identity.
 */
function cb_admin_prihlaseni_pokusy(mysqli $db, int $limit): array
{
    $limit = cb_admin_prihlaseni_limit($limit);
    $sql = '
        SELECT
            b.id_bad_login, b.email, b.ip, b.user_agent, b.screen_w, b.screen_h,
            b.is_touch, b.kdy,
            u.id_user, u.jmeno, u.prijmeni, hp.aktivni,
            (
                SELECT ul.kdy
                FROM user_login ul
                WHERE ul.id_user = u.id_user
                  AND ul.akce = 1
                  AND ul.kdy BETWEEN b.kdy AND DATE_ADD(b.kdy, INTERVAL 30 MINUTE)
                ORDER BY ul.kdy ASC, ul.id_login ASC
                LIMIT 1
            ) AS uspesny_login_kdy,
            (
                SELECT ul.ip
                FROM user_login ul
                WHERE ul.id_user = u.id_user
                  AND ul.akce = 1
                  AND ul.kdy BETWEEN b.kdy AND DATE_ADD(b.kdy, INTERVAL 30 MINUTE)
                ORDER BY ul.kdy ASC, ul.id_login ASC
                LIMIT 1
            ) AS uspesny_login_ip,
            (
                SELECT us.user_agent
                FROM user_login ul
                LEFT JOIN user_spy us ON us.id_login = ul.id_login
                WHERE ul.id_user = u.id_user
                  AND ul.akce = 1
                  AND ul.kdy BETWEEN b.kdy AND DATE_ADD(b.kdy, INTERVAL 30 MINUTE)
                ORDER BY ul.kdy ASC, ul.id_login ASC
                LIMIT 1
            ) AS uspesny_login_user_agent,
            EXISTS(
                SELECT 1
                FROM user_login ul
                WHERE ul.id_user = u.id_user
                  AND ul.akce = 1
                  AND ul.kdy < b.kdy
                  AND b.ip <> \'\'
                  AND ul.ip = b.ip
            ) AS znama_ip,
            EXISTS(
                SELECT 1
                FROM user_login ul
                INNER JOIN user_spy us ON us.id_login = ul.id_login
                WHERE ul.id_user = u.id_user
                  AND ul.akce = 1
                  AND ul.kdy < b.kdy
                  AND b.user_agent IS NOT NULL
                  AND b.user_agent <> \'\'
                  AND us.user_agent = b.user_agent
            ) AS zname_zarizeni,
            (
                SELECT COUNT(DISTINCT LOWER(b2.email))
                FROM user_bad_login b2
                WHERE b.ip <> \'\'
                  AND b2.ip = b.ip
                  AND b2.kdy BETWEEN DATE_SUB(b.kdy, INTERVAL 1 DAY)
                                 AND DATE_ADD(b.kdy, INTERVAL 1 DAY)
            ) AS pocet_emailu_ze_stejne_ip
        FROM (
            SELECT id_bad_login, email, ip, user_agent, screen_w, screen_h, is_touch, kdy
            FROM user_bad_login
            ORDER BY kdy DESC, id_bad_login DESC
            LIMIT ' . $limit . '
        ) b
        LEFT JOIN user u ON LOWER(u.email) = LOWER(b.email)
        LEFT JOIN hr_person hp ON hp.id_user = u.id_user
        ORDER BY b.kdy DESC, b.id_bad_login DESC
    ';
    $result = $db->query($sql);
    if (!($result instanceof mysqli_result)) {
        throw new RuntimeException('Nelze načíst přehled neúspěšných přihlášení.');
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}

/** Priradi pokusu kratke orientacni hodnoceni podle dostupnych stop. */
function cb_admin_prihlaseni_hodnoceni(array $row): array
{
    $maUcet = (int)($row['id_user'] ?? 0) > 0;
    $maUspesnyLogin = trim((string)($row['uspesny_login_kdy'] ?? '')) !== '';
    $stejnaIp = $maUspesnyLogin
        && trim((string)($row['ip'] ?? '')) !== ''
        && hash_equals((string)$row['ip'], (string)($row['uspesny_login_ip'] ?? ''));
    $stejneZarizeni = $maUspesnyLogin
        && trim((string)($row['user_agent'] ?? '')) !== ''
        && hash_equals((string)$row['user_agent'], (string)($row['uspesny_login_user_agent'] ?? ''));
    $viceUctu = (int)($row['pocet_emailu_ze_stejne_ip'] ?? 0) >= 3;

    if (!$maUcet || $viceUctu) {
        return ['key' => 'podezrele', 'label' => 'Podezřelý nebo cizí pokus'];
    }
    if ($maUspesnyLogin && ($stejnaIp || $stejneZarizeni)) {
        return ['key' => 'preklep', 'label' => 'Pravděpodobně vlastní překlep'];
    }
    if ($maUspesnyLogin) {
        return ['key' => 'nove', 'label' => 'Nové zařízení nebo síť'];
    }
    if (!empty($row['znama_ip']) || !empty($row['zname_zarizeni'])) {
        return ['key' => 'znamy', 'label' => 'Známý účet nebo zařízení'];
    }
    return ['key' => 'nepotvrzeno', 'label' => 'Známý účet, nepotvrzeno'];
}

/** Nacte posledni 2FA vyzvy bez citliveho tokenu. */
function cb_admin_prihlaseni_2fa(mysqli $db, int $limit): array
{
    $limit = cb_admin_prihlaseni_limit($limit);
    $result = $db->query('
        SELECT p.id, p.id_user, p.stav, p.ip, p.prohlizec, p.vytvoreno,
               p.vyprsi, p.rozhodnuto, p.id_zarizeni,
               u.jmeno, u.prijmeni, u.email
        FROM push_login_2fa p
        LEFT JOIN user u ON u.id_user = p.id_user
        ORDER BY p.vytvoreno DESC, p.id DESC
        LIMIT ' . $limit
    );
    if (!($result instanceof mysqli_result)) {
        throw new RuntimeException('Nelze načíst přehled 2FA výzev.');
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}
