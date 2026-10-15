<?php
// Pripravuje filtry, podklady a serverovy snimek rozepsane kontroly trzeb.
declare(strict_types=1);
require_once __DIR__ . '/kontrola_trzeb_vypocet.php';
require_once __DIR__ . '/kontrola_trzeb_pristup.php';
require_once __DIR__ . '/../db/db_kontrola_trzeb_reporty.php';
require_once __DIR__ . '/../db/db_kontrola_trzeb_objednavky.php';
require_once __DIR__ . '/../db/db_kontrola_trzeb_archiv_cteni.php';

// Datum musi opravdu existovat; PHP nesmi tise posunout napr. 31. unor do brezna.
function cb_kt_datum(string $value): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Prague'));
    if (!$date || $date->format('Y-m-d') !== $value) { throw new CbUserVisibleException('Zadejte platné datum.'); }
    return $date;
}

// Tyden ma prednost jen ve vlastnim rezimu; libovolne obdobi zustava presne od-do.
function cb_kt_filtry(array $input, array $branches): array
{
    $monday = (new DateTimeImmutable('today', new DateTimeZone('Europe/Prague')))->modify('monday this week')->modify('-7 days');
    $mode = ($input['kt_rezim'] ?? 'tyden') === 'obdobi' ? 'obdobi' : 'tyden';
    if ($mode === 'tyden') {
        $week = (string)($input['kt_tyden'] ?? $monday->format('o-\WW'));
        if (!preg_match('/^(\d{4})-W(\d{2})$/D', $week, $m)) { throw new CbUserVisibleException('Vyberte platný týden.'); }
        $from = $monday->setISODate((int)$m[1], (int)$m[2], 1);
        if ($from->format('o-\WW') !== $week) { throw new CbUserVisibleException('Vybraný týden neexistuje.'); }
        $shift = max(-1, min(1, (int)($input['kt_posun'] ?? 0)));
        if ($shift !== 0) { $from = $from->modify(($shift > 0 ? '+' : '-') . '7 days'); }
        $to = $from->modify('+6 days');
    } else {
        $from = cb_kt_datum((string)($input['kt_od'] ?? $monday->format('Y-m-d')));
        $to = cb_kt_datum((string)($input['kt_do'] ?? $monday->modify('+6 days')->format('Y-m-d')));
    }
    if ($from > $to) {
        throw new CbUserVisibleException('Datum Od nesmí být pozdější než datum Do.');
    }
    if ($from->diff($to)->days > 366 || (int)$from->format('Y') < 2000 || (int)$to->format('Y') > 2100) {
        throw new CbUserVisibleException('Vyberte období od–do v rozsahu nejvýše 367 dnů (roky 2000–2100).');
    }
    $branch = (int)($input['kt_pob'] ?? array_key_first($branches) ?? 0);
    if (!isset($branches[$branch])) { throw new CbUserVisibleException('Vyberte dostupnou pobočku.'); }
    return ['branch' => $branch, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
        'week' => $from->format('o-\WW'), 'mode' => $mode];
}

// Jedno misto pro odkazy drzi lokalni filtry nezavisle na globalnim obdobi hlavicky.
function cb_kt_url(array $filters, string $page = 'kontrola_trzeb', array $extra = []): string
{
    return cb_root_url('index.php') . '?' . http_build_query(array_merge([
        'm' => 'provoz', 'page' => $page, 'kt_pob' => $filters['branch'], 'kt_rezim' => $filters['mode'],
        'kt_tyden' => $filters['week'], 'kt_od' => $filters['from'], 'kt_do' => $filters['to'],
    ], $extra));
}

// Snimek zdroju zustava na serveru, aby klient nemohl podvrhnout hodnoty z reportu ci objednavek.
function cb_kt_formular_data(mysqli $db, array $filters): array
{
    $reports = cb_kt_reporty($db, $filters['branch'], $filters['from'], $filters['to']);
    $orders = cb_kt_objednavky($db, $filters['branch'], $filters['from'], $filters['to']);
    $token = bin2hex(random_bytes(24));
    $snapshot = $filters + ['left' => $reports['values'], 'automatic' => $orders['values'], 'sources' => [
        'report_ids' => $reports['report_ids'], 'missing' => $reports['missing'],
        'incomplete' => $reports['incomplete'], 'orders' => $orders['count'], 'missing_prices' => $orders['missing_prices'],
    ]];
    // Stare formulare uvolnime; rozepsana kontrola ma nejvyse 12 hodin a kazda karta vlastni token.
    foreach (($_SESSION['kt_forms'] ?? []) as $key => $form) {
        if (($form['time'] ?? 0) < time() - 43200) { unset($_SESSION['kt_forms'][$key]); }
    }
    if (count($_SESSION['kt_forms'] ?? []) >= 20) { array_shift($_SESSION['kt_forms']); }
    $_SESSION['kt_forms'][$token] = ['time' => time(), 'snapshot' => $snapshot];
    return ['token' => $token, 'snapshot' => $snapshot];
}
