<?php
// inicializace/objednavky_restia_raw.php * Verze: V1 * Aktualizace: 27.05.2026
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/restia_client.php';
require_once __DIR__ . '/../db/db_api_restia.php';

const CB_RESTIA_RAW_LIMIT = 200;
const CB_RESTIA_RAW_STOP_MS = 45000;

if (!function_exists('cb_raw_h')) {
    function cb_raw_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_raw_now')) {
    function cb_raw_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('cb_raw_import_end_date')) {
    function cb_raw_import_end_date(): string
    {
        return (new DateTimeImmutable('yesterday', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
    }
}

if (!function_exists('cb_raw_normalize_ymd')) {
    function cb_raw_normalize_ymd(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) === 1) {
            $y = (int)$m[1];
            $mo = (int)$m[2];
            $d = (int)$m[3];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        throw new RuntimeException('Neplatné datum: ' . $raw);
    }
}

if (!function_exists('cb_raw_next_date')) {
    function cb_raw_next_date(string $date): string
    {
        $tz = new DateTimeZone('Europe/Prague');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', $tz);
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatné datum pro posun.');
        }
        return $dt->modify('+1 day')->format('Y-m-d');
    }
}

if (!function_exists('cb_raw_prev_date')) {
    function cb_raw_prev_date(string $date): string
    {
        $tz = new DateTimeZone('Europe/Prague');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', $tz);
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatné datum pro posun.');
        }
        return $dt->modify('-1 day')->format('Y-m-d');
    }
}

if (!function_exists('cb_raw_format_date_cs')) {
    function cb_raw_format_date_cs(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '-';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            return $date;
        }
        return $dt->format('j. n. Y');
    }
}

if (!function_exists('cb_raw_day_range')) {
    function cb_raw_day_range(string $date): array
    {
        $tz = new DateTimeZone('Europe/Prague');
        $fromLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $date . ' 00:00:00.000000', $tz);
        $toLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $date . ' 23:59:59.999000', $tz);
        if (!($fromLocal instanceof DateTimeImmutable) || !($toLocal instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatný den pro interval.');
        }

        return [
            'from_z' => $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'to_z' => $toLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'from_db' => $fromLocal->format('Y-m-d H:i:s.v'),
            'to_db' => $toLocal->format('Y-m-d H:i:s.v'),
        ];
    }
}

if (!function_exists('cb_raw_extract_orders')) {
    function cb_raw_extract_orders(array $json): array
    {
        if (array_is_list($json)) {
            return $json;
        }
        if (isset($json['data']) && is_array($json['data']) && array_is_list($json['data'])) {
            return $json['data'];
        }
        if (isset($json['orders']) && is_array($json['orders']) && array_is_list($json['orders'])) {
            return $json['orders'];
        }
        return [];
    }
}

if (!function_exists('cb_raw_restia_to_local_nullable')) {
    function cb_raw_restia_to_local_nullable(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return null;
        }
        try {
            $dt = new DateTimeImmutable($value);
            return $dt->setTimezone(new DateTimeZone('Europe/Prague'))->format('Y-m-d H:i:s.v');
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('cb_raw_get_auth')) {
    function cb_raw_get_auth(): array
    {
        $user = $_SESSION['cb_user'] ?? null;
        $idUser = (int)(is_array($user) ? ($user['id_user'] ?? 0) : 0);
        $idLogin = (int)($_SESSION['cb_id_login'] ?? 0);

        return [
            'id_user' => $idUser,
            'id_login' => $idLogin,
        ];
    }
}

if (!function_exists('cb_raw_branches')) {
    function cb_raw_branches(mysqli $conn): array
    {
        $res = $conn->query('
            SELECT id_pob, nazev, restia_activePosId, prvni_obj
            FROM pobocka
            ORDER BY id_pob ASC
        ');
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('DB dotaz na pobočky selhal.');
        }

        $out = [];
        while ($row = $res->fetch_assoc()) {
            $activePosId = trim((string)($row['restia_activePosId'] ?? ''));
            $out[] = [
                'id_pob' => (int)($row['id_pob'] ?? 0),
                'nazev' => trim((string)($row['nazev'] ?? '')),
                'active_pos_id' => $activePosId,
                'prvni_obj' => trim((string)($row['prvni_obj'] ?? '')),
                'enabled' => ($activePosId !== ''),
            ];
        }
        $res->free();

        return $out;
    }
}

if (!function_exists('cb_raw_branch_by_id')) {
    function cb_raw_branch_by_id(mysqli $conn, int $idPob): array
    {
        foreach (cb_raw_branches($conn) as $branch) {
            if ((int)$branch['id_pob'] === $idPob) {
                if ((string)$branch['active_pos_id'] === '') {
                    throw new RuntimeException('Pobočka nemá restia_activePosId.');
                }
                return $branch;
            }
        }
        throw new RuntimeException('Pobočka neexistuje.');
    }
}

if (!function_exists('cb_raw_first_branch_with_work')) {
    function cb_raw_first_branch_with_work(mysqli $conn, string $importEndDate): array
    {
        foreach (cb_raw_branches($conn) as $branch) {
            if (!((bool)($branch['enabled'] ?? false))) {
                continue;
            }
            $nextDate = cb_raw_next_work_date($conn, $branch);
            if ($nextDate !== '' && $nextDate <= $importEndDate) {
                return $branch;
            }
        }
        throw new RuntimeException('Není žádná pobočka s prací k importu.');
    }
}

if (!function_exists('cb_raw_next_work_date')) {
    function cb_raw_next_work_date(mysqli $conn, array $branch): string
    {
        $idPob = (int)($branch['id_pob'] ?? 0);
        $startDate = cb_raw_normalize_ymd((string)($branch['prvni_obj'] ?? ''));
        if ($idPob <= 0 || $startDate === '') {
            return '';
        }

        $stmt = $conn->prepare('
            SELECT datum_od
            FROM objednavky_raw_import
            WHERE id_pob = ?
              AND stav = "ok"
            ORDER BY datum_od DESC, id_raw_import DESC
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: RAW další datum.');
        }
        $stmt->bind_param('i', $idPob);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        if (!is_array($row)) {
            return $startDate;
        }

        $datumOd = trim((string)($row['datum_od'] ?? ''));
        if ($datumOd === '') {
            return $startDate;
        }
        $date = substr($datumOd, 0, 10);
        return cb_raw_next_date($date);
    }
}

if (!function_exists('cb_raw_branch_status')) {
    function cb_raw_branch_status(mysqli $conn, array $branch, string $importEndDate): array
    {
        $startDate = cb_raw_normalize_ymd((string)($branch['prvni_obj'] ?? ''));
        $nextDate = ((bool)($branch['enabled'] ?? false)) ? cb_raw_next_work_date($conn, $branch) : '';
        $hasImport = ($nextDate !== '' && $startDate !== '' && $nextDate > $startDate);
        $lastDate = $hasImport ? cb_raw_prev_date($nextDate) : '';
        $finished = ($nextDate !== '' && $nextDate > $importEndDate);

        return [
            'start_date' => $startDate,
            'last_date' => $lastDate,
            'next_date' => $nextDate,
            'has_import' => $hasImport,
            'finished' => $finished,
        ];
    }
}

if (!function_exists('cb_raw_import_begin')) {
    function cb_raw_import_begin(mysqli $conn, int $idPob, array $range): int
    {
        $stmt = $conn->prepare('
            SELECT id_raw_import
            FROM objednavky_raw_import
            WHERE id_pob = ?
              AND datum_od = ?
              AND datum_do = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: RAW import find.');
        }

        $stmt->bind_param('iss', $idPob, $range['from_db'], $range['to_db']);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        if (is_array($row) && (int)($row['id_raw_import'] ?? 0) > 0) {
            $idRawImport = (int)$row['id_raw_import'];
            $stav = 'bezi';
            $stmtUpd = $conn->prepare('
                UPDATE objednavky_raw_import
                SET stav = ?, chyba = NULL, spusteno = NOW(3), dokonceno = NULL
                WHERE id_raw_import = ?
                LIMIT 1
            ');
            if ($stmtUpd === false) {
                throw new RuntimeException('DB prepare selhal: RAW import reset.');
            }
            $stmtUpd->bind_param('si', $stav, $idRawImport);
            $stmtUpd->execute();
            $stmtUpd->close();
            return $idRawImport;
        }

        $stav = 'bezi';
        $stmtIns = $conn->prepare('
            INSERT INTO objednavky_raw_import (
                id_pob, datum_od, datum_do, created_from_utc, created_to_utc, stav, spusteno
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(3))
        ');
        if ($stmtIns === false) {
            throw new RuntimeException('DB prepare selhal: RAW import insert.');
        }
        $stmtIns->bind_param('isssss', $idPob, $range['from_db'], $range['to_db'], $range['from_z'], $range['to_z'], $stav);
        $stmtIns->execute();
        $idRawImport = (int)$conn->insert_id;
        $stmtIns->close();

        return $idRawImport;
    }
}

if (!function_exists('cb_raw_import_finish')) {
    function cb_raw_import_finish(mysqli $conn, int $idRawImport, int $totalCount, int $savedCount, int $pages, ?string $error): void
    {
        $stav = ($error === null || $error === '') ? 'ok' : 'chyba';
        $stmt = $conn->prepare('
            UPDATE objednavky_raw_import
            SET restia_total_count = ?,
                stazeno_pocet = ?,
                pocet_stran = ?,
                stav = ?,
                chyba = ?,
                dokonceno = NOW(3)
            WHERE id_raw_import = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: RAW import finish.');
        }
        $stmt->bind_param('iiissi', $totalCount, $savedCount, $pages, $stav, $error, $idRawImport);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_raw_save_order')) {
    function cb_raw_save_order(mysqli $conn, int $idRawImport, int $idPob, array $order): void
    {
        $restiaIdObj = trim((string)($order['id'] ?? ''));
        if ($restiaIdObj === '') {
            throw new RuntimeException('Objednávka nemá id.');
        }

        $restiaCreatedAt = cb_raw_restia_to_local_nullable($order['createdAt'] ?? null);
        $rawJson = json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($rawJson) || $rawJson === '') {
            throw new RuntimeException('Nepodařilo se uložit JSON objednávky.');
        }

        $stmt = $conn->prepare('
            INSERT INTO objednavky_raw (
                id_raw_import, id_pob, restia_id_obj, restia_created_at, raw_json, zpracovano, zpracovano_kdy, chyba, zadano
            ) VALUES (
                ?, ?, ?, ?, ?, 0, NULL, NULL, NOW(3)
            )
            ON DUPLICATE KEY UPDATE
                id_raw_import = VALUES(id_raw_import),
                id_pob = VALUES(id_pob),
                restia_created_at = VALUES(restia_created_at),
                raw_json = VALUES(raw_json),
                zpracovano = 0,
                zpracovano_kdy = NULL,
                chyba = NULL
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: RAW order upsert.');
        }
        $stmt->bind_param('iisss', $idRawImport, $idPob, $restiaIdObj, $restiaCreatedAt, $rawJson);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_raw_import_day')) {
    function cb_raw_import_day(mysqli $conn, array $auth, array $branch, string $date): array
    {
        $idPob = (int)$branch['id_pob'];
        $activePosId = (string)$branch['active_pos_id'];
        $range = cb_raw_day_range($date);
        $idRawImport = cb_raw_import_begin($conn, $idPob, $range);
        $savedCount = 0;
        $totalCount = 0;
        $pages = 0;

        try {
            $page = 1;
            while (true) {
                $res = cb_restia_get('/api/orders', [
                    'page' => $page,
                    'limit' => CB_RESTIA_RAW_LIMIT,
                    'createdFrom' => $range['from_z'],
                    'createdTo' => $range['to_z'],
                    'activePosId' => $activePosId,
                ], $activePosId, 'raw den=' . $date . ' id_pob=' . $idPob . ' page=' . $page);

                if ((int)($res['ok'] ?? 0) !== 1) {
                    throw new RuntimeException('Restia chyba HTTP=' . (string)($res['http_status'] ?? 0));
                }

                $decoded = json_decode((string)($res['body'] ?? ''), true);
                if (!is_array($decoded)) {
                    throw new RuntimeException('Restia vrátila neplatný JSON.');
                }

                $orders = cb_raw_extract_orders($decoded);
                $countOrders = count($orders);
                $totalCount = max($totalCount, (int)($res['total_count'] ?? 0));
                $pages++;

                $conn->begin_transaction();
                try {
                    foreach ($orders as $order) {
                        if (!is_array($order)) {
                            throw new RuntimeException('Restia vrátila neplatnou objednávku.');
                        }
                        cb_raw_save_order($conn, $idRawImport, $idPob, $order);
                        $savedCount++;
                    }
                    $conn->commit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    throw $e;
                }

                if ($countOrders < CB_RESTIA_RAW_LIMIT) {
                    break;
                }
                if ($totalCount > 0 && ($page * CB_RESTIA_RAW_LIMIT) >= $totalCount) {
                    break;
                }
                if ($countOrders === 0) {
                    break;
                }
                $page++;
            }

            cb_raw_import_finish($conn, $idRawImport, $totalCount, $savedCount, $pages, null);
            db_api_restia_flush($conn, (int)($auth['id_user'] ?? 0), (int)($auth['id_login'] ?? 0));
            return ['date' => $date, 'ok' => 1, 'count' => $savedCount, 'total' => $totalCount, 'pages' => $pages, 'error' => ''];
        } catch (Throwable $e) {
            cb_raw_import_finish($conn, $idRawImport, $totalCount, $savedCount, $pages, $e->getMessage());
            db_api_restia_flush($conn, (int)($auth['id_user'] ?? 0), (int)($auth['id_login'] ?? 0));
            throw $e;
        }
    }
}

if (!function_exists('cb_raw_run_branch')) {
    function cb_raw_run_branch(mysqli $conn, array $auth, array $branch, string $importEndDate): array
    {
        $startedMs = (int)round(microtime(true) * 1000);
        $date = cb_raw_next_work_date($conn, $branch);
        $runFromDate = $date;
        $rows = [];
        $sum = 0;

        while ($date !== '' && $date <= $importEndDate) {
            $row = cb_raw_import_day($conn, $auth, $branch, $date);
            $rows[] = $row;
            $sum += (int)($row['count'] ?? 0);
            $date = cb_raw_next_date($date);

            $nowMs = (int)round(microtime(true) * 1000);
            if (($nowMs - $startedMs) >= CB_RESTIA_RAW_STOP_MS) {
                break;
            }
        }

        return [
            'branch' => $branch,
            'rows' => $rows,
            'sum' => $sum,
            'run_from_date' => $runFromDate,
            'next_date' => cb_raw_next_work_date($conn, $branch),
            'finished' => (cb_raw_next_work_date($conn, $branch) > $importEndDate) ? 1 : 0,
        ];
    }
}

$conn = db();
$auth = cb_raw_get_auth();
$importEndDate = cb_raw_import_end_date();
$run = isset($_REQUEST['run']) && (string)$_REQUEST['run'] === '1';
$idPob = (int)($_REQUEST['id_pob'] ?? 0);
$rawCycle = max(1, (int)($_REQUEST['raw_cycle'] ?? 1));
$result = null;
$error = '';

try {
    if ($run) {
        $branch = $idPob > 0 ? cb_raw_branch_by_id($conn, $idPob) : cb_raw_first_branch_with_work($conn, $importEndDate);
        $result = cb_raw_run_branch($conn, $auth, $branch, $importEndDate);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$branches = cb_raw_branches($conn);
?><!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Restia RAW objednávky</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Restia RAW objednávky</h1>
<?php if ($error !== ''): ?>
  <p style="color:#b00020;">Chyba: <?= cb_raw_h($error) ?></p>
<?php endif; ?>

<?php if (!$run): ?>
  <p>Konec importu: <?= cb_raw_h(cb_raw_format_date_cs($importEndDate)) ?></p>
  <table border="1" cellpadding="4" cellspacing="0" style="width:100%; border-collapse:collapse;">
    <thead>
      <tr>
        <th>Pobočka</th>
        <th>Stav RAW importu</th>
        <th>Pokračování</th>
        <th>Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($branches as $branchOpt): ?>
        <?php
          $enabled = ((bool)($branchOpt['enabled'] ?? false));
          $status = $enabled ? cb_raw_branch_status($conn, $branchOpt, $importEndDate) : [];
          $lastDate = (string)($status['last_date'] ?? '');
          $nextDate = (string)($status['next_date'] ?? '');
          $finished = ((bool)($status['finished'] ?? false));
          $stavText = !$enabled ? 'chybí activePosId' : ($lastDate !== '' ? ('import do ' . cb_raw_format_date_cs($lastDate)) : 'bez importu');
          $nextText = !$enabled ? '-' : ($finished ? 'hotovo' : cb_raw_format_date_cs($nextDate));
        ?>
        <tr>
          <td><?= cb_raw_h((string)$branchOpt['nazev']) ?> (<?= (int)$branchOpt['id_pob'] ?>)</td>
          <td><?= cb_raw_h($stavText) ?></td>
          <td><?= cb_raw_h($nextText) ?></td>
          <td>
            <?php if ($enabled && !$finished): ?>
              <form method="post" class="odstup_vnejsi_0" data-cb-max-form="1" data-cb-loader-text="Stahuji RAW objednávky">
                <input type="hidden" name="open_restia_raw" value="1">
                <input type="hidden" name="run" value="1">
                <input type="hidden" name="id_pob" value="<?= (int)$branchOpt['id_pob'] ?>">
                <input type="hidden" name="raw_cycle" value="1">
                <button type="submit">Stáhnout</button>
              </form>
            <?php else: ?>
              -
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if (is_array($result)): ?>
  <?php
    $branchResult = (array)$result['branch'];
    $runRows = (array)$result['rows'];
    $firstRunDate = (string)($result['run_from_date'] ?? '');
    $lastRunDate = $runRows !== [] ? (string)($runRows[count($runRows) - 1]['date'] ?? '') : '';
    $nextDate = (string)($result['next_date'] ?? '');
    $finished = ((int)($result['finished'] ?? 0) === 1);
  ?>
  <h2><?= cb_raw_h((string)$branchResult['nazev']) ?> (<?= (int)$branchResult['id_pob'] ?>)</h2>
  <p>
    Dokončený cyklus č. <?= (int)$rawCycle ?>: <?= count($runRows) ?> dnů |
    období: <?= cb_raw_h(cb_raw_format_date_cs($firstRunDate)) ?> - <?= cb_raw_h(cb_raw_format_date_cs($lastRunDate)) ?> |
    staženo: <?= (int)$result['sum'] ?> objednávek
  </p>
  <p>Pokračování: <?= $finished ? 'pobočka hotová' : ('cyklus č. ' . ((int)$rawCycle + 1) . ' od ' . cb_raw_h(cb_raw_format_date_cs($nextDate))) ?></p>

  <?php if (!$finished): ?>
    <style>
      @keyframes cbRawSpin { to { transform: rotate(360deg); } }
    </style>
    <div style="display:flex; align-items:center; gap:10px; margin:14px 0; padding:10px 12px; border:1px solid #cbd5e1; background:#f8fafc; border-radius:6px;">
      <span style="width:16px; height:16px; border:3px solid #cbd5e1; border-top-color:#2563eb; border-radius:50%; display:inline-block; animation:cbRawSpin .8s linear infinite;"></span>
      <strong>Automatické pokračování</strong>
      <span id="cbRawAutoInfo">Cyklus č. <?= (int)$rawCycle + 1 ?> začne za 3 s od <?= cb_raw_h(cb_raw_format_date_cs($nextDate)) ?>.</span>
      <button type="button" id="cbRawAutoStop">Stop</button>
    </div>
    <form id="cbRawContinueForm" method="post" class="odstup_vnejsi_0" data-cb-max-form="1" data-cb-loader-text="Stahuji RAW objednávky">
      <input type="hidden" name="open_restia_raw" value="1">
      <input type="hidden" name="run" value="1">
      <input type="hidden" name="id_pob" value="<?= (int)$branchResult['id_pob'] ?>">
      <input type="hidden" name="raw_cycle" value="<?= (int)$rawCycle + 1 ?>">
    </form>
    <div
      id="cb_restia_auto_resume"
      style="display:none;"
      data-cb-restia-auto-resume="1"
      data-cb-restia-auto-resume-delay="3000"
      data-cb-restia-auto-resume-form="#cbRawContinueForm"
      data-cb-restia-auto-resume-info="#cbRawAutoInfo"
      data-cb-restia-auto-resume-stop="#cbRawAutoStop"
      data-cb-restia-auto-resume-cycle="<?= (int)$rawCycle + 1 ?>"
      data-cb-restia-auto-resume-next-text="<?= cb_raw_h(cb_raw_format_date_cs($nextDate)) ?>"
      data-cb-restia-next-date="<?= cb_raw_h($nextDate) ?>"
    ></div>
  <?php else: ?>
    <form method="post" class="odstup_vnejsi_0" data-cb-max-form="1" data-cb-loader-text="Připravuji RAW import">
      <input type="hidden" name="open_restia_raw" value="1">
      <button type="submit">Vybrat další pobočku</button>
    </form>
  <?php endif; ?>
<?php endif; ?>
</body>
</html>
