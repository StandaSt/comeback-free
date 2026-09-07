<?php
declare(strict_types=1);

/* Dotazy a mazání jednotlivých záznamů přehledu systémových chyb pro Administraci. */

function cb_admin_log_chyby_filtry(array $source): array
{
    $rawFilters = isset($source['err_f']) && is_array($source['err_f']) ? $source['err_f'] : [];
    $textFilter = static function (string $key) use ($rawFilters): string {
        return mb_substr(trim((string)($rawFilters[$key] ?? '')), 0, 160, 'UTF-8');
    };

    $stav = (string)($rawFilters['stav'] ?? 'nevyr');
    if (!in_array($stav, ['vse', 'nevyr', 'vyr'], true)) {
        $stav = 'nevyr';
    }

    $perPage = (int)($source['err_per'] ?? 50);
    if (!in_array($perPage, [20, 50, 100, 500], true)) {
        $perPage = 50;
    }

    $sort = (string)($source['err_sort'] ?? 'kdy');
    if (!array_key_exists($sort, cb_admin_log_chyby_razeni())) {
        $sort = 'kdy';
    }
    $dir = strtoupper((string)($source['err_dir'] ?? 'DESC'));
    if (!in_array($dir, ['ASC', 'DESC'], true)) {
        $dir = 'DESC';
    }

    return [
        'filtry' => [
            'kdy' => $textFilter('kdy'),
            'modul' => $textFilter('modul'),
            'akce' => $textFilter('akce'),
            'zprava' => $textFilter('zprava'),
            'uzivatel' => $textFilter('uzivatel'),
            'stav' => $stav,
        ],
        'per_page' => $perPage,
        'strana' => max(1, (int)($source['err_p'] ?? 1)),
        'sort' => $sort,
        'dir' => $dir,
    ];
}

function cb_admin_log_chyby_razeni(): array
{
    return [
        'kdy' => 'l.kdy',
        'modul' => 'l.modul',
        'akce' => 'l.akce',
        'zprava' => 'l.zprava',
        'uzivatel' => "CONCAT_WS(' ', u.jmeno, u.prijmeni, u.email, l.id_user)",
        'stav' => 'l.vyreseno',
    ];
}

function cb_admin_log_chyby_moduly(mysqli $db): array
{
    $result = $db->query('SELECT DISTINCT modul FROM log_chyby WHERE modul <> \'\' ORDER BY modul ASC');
    if (!($result instanceof mysqli_result)) {
        throw new RuntimeException('Nelze načíst seznam modulů chyb.');
    }

    $moduly = [];
    while ($row = $result->fetch_assoc()) {
        $modul = trim((string)($row['modul'] ?? ''));
        if ($modul !== '') {
            $moduly[] = $modul;
        }
    }
    $result->free();

    return $moduly;
}

function cb_admin_log_chyby_nacti(mysqli $db, array $nastaveni): array
{
    $filtry = is_array($nastaveni['filtry'] ?? null) ? $nastaveni['filtry'] : [];
    $kdy = (string)($filtry['kdy'] ?? '');
    $modul = (string)($filtry['modul'] ?? '');
    $akce = (string)($filtry['akce'] ?? '');
    $zprava = (string)($filtry['zprava'] ?? '');
    $uzivatel = (string)($filtry['uzivatel'] ?? '');
    $stav = match ((string)($filtry['stav'] ?? 'nevyr')) {
        'vyr' => 1,
        'vse' => -1,
        default => 0,
    };
    $perPage = (int)($nastaveni['per_page'] ?? 50);
    $strana = max(1, (int)($nastaveni['strana'] ?? 1));
    $sort = (string)($nastaveni['sort'] ?? 'kdy');
    $dir = (string)($nastaveni['dir'] ?? 'DESC');
    $razeni = cb_admin_log_chyby_razeni();
    $orderBy = ($razeni[$sort] ?? $razeni['kdy']) . ($dir === 'ASC' ? ' ASC' : ' DESC') . ', l.id_log_chyby DESC';

    $where = "
        WHERE (? = '' OR DATE_FORMAT(l.kdy, '%d.%m.%Y %H:%i:%s') LIKE CONCAT('%', ?, '%'))
          AND (? = '' OR l.modul LIKE CONCAT('%', ?, '%'))
          AND (? = '' OR l.akce LIKE CONCAT('%', ?, '%'))
          AND (? = '' OR CONCAT_WS(' ', l.zprava, l.kod) LIKE CONCAT('%', ?, '%'))
          AND (? = '' OR CONCAT_WS(' ', u.jmeno, u.prijmeni, u.email, l.id_user) LIKE CONCAT('%', ?, '%'))
          AND (? < 0 OR l.vyreseno = ?)
    ";

    $countStmt = $db->prepare('SELECT COUNT(*) AS celkem FROM log_chyby l LEFT JOIN user u ON u.id_user = l.id_user' . $where);
    if ($countStmt === false) {
        throw new RuntimeException('Nelze připravit počet chyb.');
    }
    $countStmt->bind_param('ssssssssssii', $kdy, $kdy, $modul, $modul, $akce, $akce, $zprava, $zprava, $uzivatel, $uzivatel, $stav, $stav);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();
    $celkem = (int)($countRow['celkem'] ?? 0);
    $stran = max(1, (int)ceil($celkem / $perPage));
    $strana = min($strana, $stran);
    $offset = ($strana - 1) * $perPage;

    $stmt = $db->prepare('
        SELECT
            l.id_log_chyby, l.kdy, l.id_user, l.modul, l.akce, l.kod, l.zprava,
            l.detail, l.soubor, l.radek, l.url, l.data_json, l.vyreseno, l.poznamka,
            u.jmeno AS user_jmeno, u.prijmeni AS user_prijmeni, u.email AS user_email
        FROM log_chyby l
        LEFT JOIN user u ON u.id_user = l.id_user
        ' . $where . '
        ORDER BY ' . $orderBy . '
        LIMIT ? OFFSET ?
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze načíst přehled chyb.');
    }
    $stmt->bind_param('ssssssssssiiii', $kdy, $kdy, $modul, $modul, $akce, $akce, $zprava, $zprava, $uzivatel, $uzivatel, $stav, $stav, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $chyby = [];
    while ($row = $result->fetch_assoc()) {
        $chyby[] = $row;
    }
    $stmt->close();

    return [
        'chyby' => $chyby,
        'celkem' => $celkem,
        'strana' => $strana,
        'stran' => $stran,
        'per_page' => $perPage,
        'first_row' => $celkem === 0 ? 0 : $offset + 1,
        'last_row' => min($offset + $perPage, $celkem),
    ];
}

function cb_admin_log_chyby_smazat(mysqli $db, int $idLogChyby): bool
{
    if ($idLogChyby < 1) {
        throw new InvalidArgumentException('Chybí platný záznam chyby pro odstranění.');
    }
    $stmt = $db->prepare('DELETE FROM log_chyby WHERE id_log_chyby = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('Nelze připravit odstranění záznamu chyby.');
    }
    $stmt->bind_param('i', $idLogChyby);
    $stmt->execute();
    $smazano = $stmt->affected_rows === 1;
    $stmt->close();

    return $smazano;
}
