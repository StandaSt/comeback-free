<?php
// admin_testy/restia_testy/libus_import_2024_12_01_az_15.php * Verze: V1 * Aktualizace: 04.07.2026
declare(strict_types=1);

/*
CO TENHLE SCRIPT DELA

- jednoúčelový doimport objednávek z Restie pouze pro pobočku Libuš (id_pob=5)
- pevný rozsah 01.12.2024 08:00:00 až 16.12.2024 07:59:59, tedy dny 1.12.-15.12.2024 v historickém režimu
- používá stejnou ověřenou importní logiku jako admin_testy/restia_testy/01_restia_import_den.php
- neřeší žádný formulář ani obecné nastavení, po otevření se spustí rovnou
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

@set_time_limit(0);

const CB_LIBUS_IMPORT_VERZE = 'V1';
const CB_LIBUS_IMPORT_ID_POB = 5;
const CB_LIBUS_IMPORT_OD = '2024-12-01';
const CB_LIBUS_IMPORT_DO = '2024-12-15';
const CB_LIBUS_IMPORT_LOADER_FILE = __DIR__ . '/01_restia_import_den.php';

if (!function_exists('cb_libus_import_h')) {
    function cb_libus_import_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_libus_import_txt_path')) {
    function cb_libus_import_txt_path(): string
    {
        return __DIR__ . '/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.txt';
    }
}

if (!function_exists('cb_libus_import_log_init')) {
    function cb_libus_import_log_init(): void
    {
        @file_put_contents(cb_libus_import_txt_path(), '');
    }
}

if (!function_exists('cb_libus_import_log')) {
    function cb_libus_import_log(string $line = ''): void
    {
        @file_put_contents(cb_libus_import_txt_path(), $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_libus_import_boot_daily_functions')) {
    function cb_libus_import_boot_daily_functions(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        if (!is_file(CB_LIBUS_IMPORT_LOADER_FILE)) {
            throw new RuntimeException('Chybí zdrojový importní script 01_restia_import_den.php.');
        }

        $serverBackup = $_SERVER;
        $postBackup = $_POST;
        $getBackup = $_GET;

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET = [];

        ob_start();
        include CB_LIBUS_IMPORT_LOADER_FILE;
        ob_end_clean();

        $_SERVER = $serverBackup;
        $_POST = $postBackup;
        $_GET = $getBackup;

        $required = [
            'cb_restia_import_a_get_auth',
            'cb_restia_import_a_get_pobocka',
            'cb_restia_import_a_normalize_date',
            'cb_restia_import_a_local_to_utc_z',
            'cb_restia_import_a_extract_orders',
            'cb_restia_import_a_order_exists',
            'cb_restia_import_a_insert_import',
            'cb_restia_import_a_finish_import',
            'cb_restia_import_a_upsert_order',
            'cb_restia_import_a_sync_order_children',
            'cb_restia_import_a_try_flush_api',
            'cb_restia_import_a_format_date_cs',
            'cb_restia_import_a_format_datetime_cs_short',
            'cb_restia_import_a_now',
        ];

        foreach ($required as $fn) {
            if (!function_exists($fn)) {
                throw new RuntimeException('Nepodařilo se načíst funkci ' . $fn . '.');
            }
        }

        $ready = true;
    }
}

if (!function_exists('cb_libus_import_next_date')) {
    function cb_libus_import_next_date(string $date): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatné datum pro posun: ' . $date);
        }

        return $dt->modify('+1 day')->format('Y-m-d');
    }
}

if (!function_exists('cb_libus_import_day_range')) {
    function cb_libus_import_day_range(string $selectedDate): array
    {
        $selectedDate = cb_restia_import_a_normalize_date($selectedDate);
        $nextDate = cb_libus_import_next_date($selectedDate);

        return [
            'datum' => $selectedDate,
            'od_local' => $selectedDate . ' 08:00:00',
            'do_local' => $nextDate . ' 07:59:59',
        ];
    }
}

if (!function_exists('cb_libus_import_day_summary_default')) {
    function cb_libus_import_day_summary_default(string $date): array
    {
        return [
            'datum' => $date,
            'id_import' => 0,
            'pocet_obj' => 0,
            'pocet_novych' => 0,
            'pocet_zmenenych' => 0,
            'pocet_chyb' => 0,
            'page_count' => 0,
            'pocet_polozek' => 0,
            'pocet_modifikatoru' => 0,
            'pocet_kds_tagu' => 0,
            'pocet_kuryru' => 0,
            'pocet_sluzeb' => 0,
            'stav' => 'ceka',
            'poznamka' => '',
            'od_local' => '',
            'do_local' => '',
        ];
    }
}

if (!function_exists('cb_libus_import_day')) {
    function cb_libus_import_day(mysqli $conn, array $auth, array $pob, string $selectedDate): array
    {
        $selectedRange = cb_libus_import_day_range($selectedDate);
        $summary = cb_libus_import_day_summary_default($selectedDate);
        $summary['od_local'] = (string)$selectedRange['od_local'];
        $summary['do_local'] = (string)$selectedRange['do_local'];

        $odUtc = cb_restia_import_a_local_to_utc_z((string)$selectedRange['od_local']);
        $doUtc = cb_restia_import_a_local_to_utc_z((string)$selectedRange['do_local']);

        cb_libus_import_log('DEN START: ' . $selectedDate);
        cb_libus_import_log('  OD_LOCAL: ' . (string)$selectedRange['od_local']);
        cb_libus_import_log('  DO_LOCAL: ' . (string)$selectedRange['do_local']);
        cb_libus_import_log('  OD_UTC: ' . $odUtc);
        cb_libus_import_log('  DO_UTC: ' . $doUtc);

        $idImport = cb_restia_import_a_insert_import($conn, (int)$pob['id_pob'], $odUtc, $doUtc);
        $summary['id_import'] = $idImport;
        cb_libus_import_log('  ID_IMPORT: ' . (string)$idImport);

        $page = 1;
        $limit = (int)CB_RESTIA_IMPORT_A_LIMIT;

        while (true) {
            $res = cb_restia_get(
                '/api/orders',
                [
                    'page' => $page,
                    'limit' => $limit,
                    'createdFrom' => $odUtc,
                    'createdTo' => $doUtc,
                    'activePosId' => (string)$pob['active_pos_id'],
                ],
                (string)$pob['active_pos_id'],
                'Libus doimport: id_pob=' . (string)$pob['id_pob'] . ' page=' . (string)$page . ' datum=' . $selectedDate
            );

            if ((int)($res['ok'] ?? 0) !== 1) {
                throw new RuntimeException((string)($res['chyba'] ?? ('Restia orders vrátila chybu pro den ' . $selectedDate . '.')));
            }

            $decoded = json_decode((string)($res['body'] ?? ''), true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Restia nevrátila validní JSON pro den ' . $selectedDate . '.');
            }

            $orders = cb_restia_import_a_extract_orders($decoded);
            $countOrders = count($orders);
            $summary['page_count']++;

            cb_libus_import_log(
                '  PAGE ' . $page
                . ': http=' . (string)(int)($res['http_status'] ?? 0)
                . ' total=' . (string)($res['total_count'] ?? '')
                . ' count=' . (string)$countOrders
                . ' ms=' . (string)(int)($res['ms'] ?? 0)
            );

            foreach ($orders as $order) {
                if (!is_array($order)) {
                    continue;
                }

                $restiaIdObj = trim((string)($order['id'] ?? ''));
                if ($restiaIdObj === '') {
                    $summary['pocet_chyb']++;
                    continue;
                }

                $exists = cb_restia_import_a_order_exists($conn, $restiaIdObj);

                $conn->begin_transaction();
                try {
                    $idObj = cb_restia_import_a_upsert_order($conn, (int)$pob['id_pob'], (string)$pob['active_pos_id'], $order);
                    $sync = cb_restia_import_a_sync_order_children($conn, $idObj, (int)$pob['id_pob'], $order);
                    $conn->commit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    $summary['pocet_chyb']++;
                    cb_libus_import_log('    ERR order ' . $restiaIdObj . ': ' . $e->getMessage());
                    continue;
                }

                $summary['pocet_obj']++;
                $summary['pocet_polozek'] += (int)($sync['polozky'] ?? 0);
                $summary['pocet_modifikatoru'] += (int)($sync['modifikatory'] ?? 0);
                $summary['pocet_kds_tagu'] += (int)($sync['kds_tagy'] ?? 0);
                $summary['pocet_kuryru'] += (int)($sync['kuryr'] ?? 0);
                $summary['pocet_sluzeb'] += (int)($sync['sluzby'] ?? 0);

                if ($exists) {
                    $summary['pocet_zmenenych']++;
                } else {
                    $summary['pocet_novych']++;
                }
            }

            if ($countOrders < $limit) {
                break;
            }

            $totalCount = isset($res['total_count']) ? (int)$res['total_count'] : 0;
            if ($totalCount > 0 && ($page * $limit) >= $totalCount) {
                break;
            }

            if ($countOrders === 0) {
                break;
            }

            $page++;
        }

        $summary['stav'] = ($summary['pocet_chyb'] > 0) ? 'chyba' : 'ok';
        $summary['poznamka'] =
            'Libuš doimport 1.12.-15.12.2024'
            . ' datum=' . $selectedDate
            . ' pages=' . (string)$summary['page_count']
            . ' polozky=' . (string)$summary['pocet_polozek'];

        cb_restia_import_a_finish_import(
            $conn,
            $idImport,
            (string)$summary['stav'],
            (int)$summary['pocet_obj'],
            (int)$summary['pocet_novych'],
            (int)$summary['pocet_zmenenych'],
            (int)$summary['pocet_chyb'],
            (string)$summary['poznamka']
        );

        cb_libus_import_log(
            'DEN KONEC: ' . $selectedDate
            . ' | stav=' . (string)$summary['stav']
            . ' | obj=' . (string)$summary['pocet_obj']
            . ' | nove=' . (string)$summary['pocet_novych']
            . ' | zmenene=' . (string)$summary['pocet_zmenenych']
            . ' | chyby=' . (string)$summary['pocet_chyb']
        );
        cb_libus_import_log('');

        return $summary;
    }
}

cb_libus_import_boot_daily_functions();

$dayRows = [];
$total = cb_libus_import_day_summary_default('celkem');
$total['stav'] = 'ok';
$fatalError = '';
$pob = null;

cb_libus_import_log_init();
cb_libus_import_log('START: ' . date('Y-m-d H:i:s'));
cb_libus_import_log('SCRIPT: ' . basename(__FILE__));
cb_libus_import_log('VERSION: ' . CB_LIBUS_IMPORT_VERZE);
cb_libus_import_log('ID_POB: ' . (string)CB_LIBUS_IMPORT_ID_POB);
cb_libus_import_log('DATUM_OD: ' . CB_LIBUS_IMPORT_OD);
cb_libus_import_log('DATUM_DO: ' . CB_LIBUS_IMPORT_DO);

try {
    $conn = db();
    $auth = cb_restia_import_a_get_auth();
    $pob = cb_restia_import_a_get_pobocka($conn, CB_LIBUS_IMPORT_ID_POB);

    cb_libus_import_log('POBOCKA: ' . (string)$pob['nazev'] . ' | activePosId=' . (string)$pob['active_pos_id']);

    $current = CB_LIBUS_IMPORT_OD;
    while ($current <= CB_LIBUS_IMPORT_DO) {
        $daySummary = cb_libus_import_day($conn, $auth, $pob, $current);
        $dayRows[] = $daySummary;

        $total['pocet_obj'] += (int)$daySummary['pocet_obj'];
        $total['pocet_novych'] += (int)$daySummary['pocet_novych'];
        $total['pocet_zmenenych'] += (int)$daySummary['pocet_zmenenych'];
        $total['pocet_chyb'] += (int)$daySummary['pocet_chyb'];
        $total['page_count'] += (int)$daySummary['page_count'];
        $total['pocet_polozek'] += (int)$daySummary['pocet_polozek'];
        $total['pocet_modifikatoru'] += (int)$daySummary['pocet_modifikatoru'];
        $total['pocet_kds_tagu'] += (int)$daySummary['pocet_kds_tagu'];
        $total['pocet_kuryru'] += (int)$daySummary['pocet_kuryru'];
        $total['pocet_sluzeb'] += (int)$daySummary['pocet_sluzeb'];
        if ((string)$daySummary['stav'] !== 'ok') {
            $total['stav'] = 'chyba';
        }

        $current = cb_libus_import_next_date($current);
    }

    cb_restia_import_a_try_flush_api($conn, $auth);
    $total['poznamka'] = 'Libuš doimport 1.12.-15.12.2024 dokončen.';
} catch (Throwable $e) {
    $fatalError = $e->getMessage();
    $total['stav'] = 'chyba';
    cb_libus_import_log('FATAL: ' . $fatalError);
}

cb_libus_import_log('TXT: ' . cb_libus_import_txt_path());
cb_libus_import_log('END: ' . date('Y-m-d H:i:s'));
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Libuš doimport 1.12.-15.12.2024</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
  <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Libuš doimport 1.12.-15.12.2024</h2>
  <p class="card_text text_tucny <?= ($fatalError === '' && (string)$total['stav'] === 'ok') ? 'txt_zelena' : 'txt_cervena' ?>">
    Stav: <?= cb_libus_import_h($fatalError === '' ? (string)$total['stav'] : 'chyba') ?>
  </p>

  <?php if ($fatalError !== ''): ?>
    <p class="card_text txt_cervena"><?= cb_libus_import_h($fatalError) ?></p>
  <?php endif; ?>

  <table class="table ram_normal bg_bila radek_1_35 sirka100 odstup_horni_10">
    <tbody>
      <tr><td class="text_tucny">Kdy</td><td><?= cb_libus_import_h(cb_restia_import_a_format_datetime_cs_short(cb_restia_import_a_now())) ?></td></tr>
      <tr><td class="text_tucny">Pobočka</td><td><?= cb_libus_import_h((string)($pob['nazev'] ?? 'Libuš')) ?></td></tr>
      <tr><td class="text_tucny">ID pob</td><td><?= cb_libus_import_h((string)CB_LIBUS_IMPORT_ID_POB) ?></td></tr>
      <tr><td class="text_tucny">Rozsah</td><td><?= cb_libus_import_h(CB_LIBUS_IMPORT_OD) ?> až <?= cb_libus_import_h(CB_LIBUS_IMPORT_DO) ?></td></tr>
      <tr><td class="text_tucny">TXT log</td><td><?= cb_libus_import_h(cb_libus_import_txt_path()) ?></td></tr>
    </tbody>
  </table>

  <h3 class="text_24 txt_seda text_tucny odstup_horni_10 odstup_vnejsi_0">Celkem</h3>
  <table class="table ram_normal bg_bila radek_1_35 sirka100 odstup_horni_10">
    <tbody>
      <tr><th>Pocet obj</th><td><?= cb_libus_import_h((string)$total['pocet_obj']) ?></td></tr>
      <tr><th>Pocet novych</th><td><?= cb_libus_import_h((string)$total['pocet_novych']) ?></td></tr>
      <tr><th>Pocet zmenenych</th><td><?= cb_libus_import_h((string)$total['pocet_zmenenych']) ?></td></tr>
      <tr><th>Pocet chyb</th><td><?= cb_libus_import_h((string)$total['pocet_chyb']) ?></td></tr>
      <tr><th>Pages</th><td><?= cb_libus_import_h((string)$total['page_count']) ?></td></tr>
      <tr><th>Pocet polozek</th><td><?= cb_libus_import_h((string)$total['pocet_polozek']) ?></td></tr>
      <tr><th>Pocet modifikatoru</th><td><?= cb_libus_import_h((string)$total['pocet_modifikatoru']) ?></td></tr>
      <tr><th>Pocet kds tagu</th><td><?= cb_libus_import_h((string)$total['pocet_kds_tagu']) ?></td></tr>
      <tr><th>Pocet kuryru</th><td><?= cb_libus_import_h((string)$total['pocet_kuryru']) ?></td></tr>
      <tr><th>Pocet sluzeb</th><td><?= cb_libus_import_h((string)$total['pocet_sluzeb']) ?></td></tr>
    </tbody>
  </table>

  <h3 class="text_24 txt_seda text_tucny odstup_horni_10 odstup_vnejsi_0">Dny</h3>
  <table class="table ram_normal bg_bila radek_1_35 sirka100 odstup_horni_10">
    <thead>
      <tr>
        <th>Datum</th>
        <th>Import</th>
        <th>Stav</th>
        <th>Obj</th>
        <th>Nove</th>
        <th>Zmenene</th>
        <th>Chyby</th>
        <th>Pages</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($dayRows as $row): ?>
        <tr>
          <td><?= cb_libus_import_h(cb_restia_import_a_format_date_cs((string)$row['datum'])) ?></td>
          <td><?= cb_libus_import_h((string)$row['id_import']) ?></td>
          <td><?= cb_libus_import_h((string)$row['stav']) ?></td>
          <td><?= cb_libus_import_h((string)$row['pocet_obj']) ?></td>
          <td><?= cb_libus_import_h((string)$row['pocet_novych']) ?></td>
          <td><?= cb_libus_import_h((string)$row['pocet_zmenenych']) ?></td>
          <td><?= cb_libus_import_h((string)$row['pocet_chyb']) ?></td>
          <td><?= cb_libus_import_h((string)$row['page_count']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($dayRows === []): ?>
        <tr><td colspan="8">Bez dat.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
