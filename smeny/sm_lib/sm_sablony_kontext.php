<?php
declare(strict_types=1);

/* Účel souboru: Připraví povolené pobočky, osobu a pozice pro práci se šablonami. */

/** @return array<int,array<string,mixed>> */
function cb_smeny_sablony_pobocky(mysqli $db): array
{
    $sessionUser = $_SESSION['cb_user'] ?? [];
    $idUser = is_array($sessionUser) ? (int)($sessionUser['id_user'] ?? 0) : 0;
    if ($idUser <= 0) {
        return [];
    }

    $allowedRows = cb_pobocky_get_allowed_rows_for_user($idUser);
    $allowed = [];
    foreach ($allowedRows as $row) {
        $idPob = (int)($row['id_pob'] ?? 0);
        if ($idPob > 0) {
            $allowed[$idPob] = true;
        }
    }

    $selected = get_selected_pobocky();
    $ids = array_values(array_filter($selected, static fn(int $idPob): bool => isset($allowed[$idPob])));
    if ($ids === []) {
        $ids = array_keys($allowed);
    }
    if ($ids === []) {
        return [];
    }

    $sqlIds = implode(',', array_map('intval', $ids));
    $result = $db->query('SELECT id_pob, id_firma, kod, nazev, end_po, end_ut, end_st, end_ct, end_pa, end_so, end_ne FROM pobocka WHERE aktivni = 1 AND id_pob IN (' . $sqlIds . ') ORDER BY nazev');
    $branches = [];
    while ($row = $result->fetch_assoc()) {
        $row['id_pob'] = (int)$row['id_pob'];
        $row['id_firma'] = (int)$row['id_firma'];
        $branches[(int)$row['id_pob']] = $row;
    }
    $result->close();
    return $branches;
}

function cb_smeny_sablony_id_person(mysqli $db): int
{
    $sessionUser = $_SESSION['cb_user'] ?? [];
    $idUser = is_array($sessionUser) ? (int)($sessionUser['id_user'] ?? 0) : 0;
    if ($idUser <= 0) {
        return 0;
    }
    $stmt = $db->prepare('SELECT id_person FROM hr_person WHERE id_user = ? ORDER BY id_person DESC LIMIT 1');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $idPerson = (int)($stmt->get_result()->fetch_assoc()['id_person'] ?? 0);
    $stmt->close();
    return $idPerson;
}

/** @return array<int,string> */
function cb_smeny_sablony_pozice(array $branch): array
{
    if (strcasecmp(trim((string)($branch['kod'] ?? '')), 'Vyroba') === 0) {
        return [5 => 'Operátor výroby', 8 => 'Závoz'];
    }
    return [1 => 'Pizzař', 2 => 'Kurýr'];
}

function cb_smeny_sablony_nazev_pozice(array $branch, int $idSlot, string $fallback = ''): string
{
    $positions = cb_smeny_sablony_pozice($branch);
    return $positions[$idSlot] ?? ($fallback !== '' ? $fallback : 'Pozice #' . $idSlot);
}

function cb_smeny_sablony_zaviraci_cas(array $branch, int $day): string
{
    $keys = [1 => 'end_po', 2 => 'end_ut', 3 => 'end_st', 4 => 'end_ct', 5 => 'end_pa', 6 => 'end_so', 7 => 'end_ne'];
    return substr(trim((string)($branch[$keys[$day] ?? ''] ?? '')), 0, 5);
}

function cb_smeny_sablony_cas_minuty(string $time, bool $isEnd): int
{
    if (preg_match('~^(\d{2}):(\d{2})$~', $time, $match) !== 1) {
        throw new CbUserVisibleException('Čas slotu nemá správný formát.');
    }
    $hour = (int)$match[1];
    $minute = (int)$match[2];
    if ($hour > 23 || $minute > 59) {
        throw new CbUserVisibleException('Čas slotu nemá správný formát.');
    }
    $value = ($hour * 60) + $minute;
    if ($value < 360 || ($isEnd && $value <= 240)) {
        $value += 1440;
    }
    return $value;
}

function cb_smeny_sablony_cas_overit(string $time, bool $isEnd, string $closing = ''): int
{
    $value = cb_smeny_sablony_cas_minuty($time, $isEnd);
    $isClosing = $isEnd && $closing !== '' && $time === $closing;
    if (!$isClosing && ((int)substr($time, 3, 2) % 30) !== 0) {
        throw new CbUserVisibleException('Čas slotu musí být nastaven po 30 minutách.');
    }
    if ($value < 360 || $value > ($isEnd ? 1680 : 1650)) {
        throw new CbUserVisibleException('Čas slotu musí být mezi 6:00 a 4:00 následujícího dne.');
    }
    return $value;
}

/** @return string[] */
function cb_smeny_sablony_casy(string $closing = '', bool $isEnd = true): array
{
    $times = [];
    $lastMinute = $isEnd ? 1680 : 1650;
    if ($closing !== '') {
        $closingMinute = cb_smeny_sablony_cas_minuty($closing, true);
        $lastMinute = min($lastMinute, $isEnd ? $closingMinute : $closingMinute - 30);
    }
    for ($minutes = 360; $minutes <= $lastMinute; $minutes += 30) {
        $clock = $minutes % 1440;
        $value = sprintf('%02d:%02d', intdiv($clock, 60), $clock % 60);
        $times[$minutes] = $value;
    }
    if ($isEnd && $closing !== '') {
        $times[cb_smeny_sablony_cas_minuty($closing, true)] = $closing;
    }
    ksort($times);
    return array_values($times);
}
