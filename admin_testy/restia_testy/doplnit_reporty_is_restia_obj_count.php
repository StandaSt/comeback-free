<?php
// admin_testy/restia_testy/doplnit_reporty_is_restia_obj_count.php * Verze: V1 * Aktualizace: 23.06.2026
declare(strict_types=1);

/*
CO TENHLE SCRIPT DELA

- je to jednorazovy servisni script pro doplneni poctu objednavek do uz ulozenych K10 reportu
- bere jen platne reporty z reporty_is
- navazuje je na reporty_is_restia pres id_reportu
- pro kazdy report vezme id_pob + datum_reportu
- pracovni den pocita stejne jako K10, tedy 06:00-06:00
- pouziva stejnou logiku jako K10 pres:
  - cb_dt_workday_range_utc()
  - cb_denni_report_restia_summary()
- zapisuje pouze sloupce:
  - wolt_obj
  - bolt_obj
  - damejidlo_obj
  - web_obj
  - wolt_cash_obj
  - dj_cash_obj
- nic jineho v reportu nemeni
- je urceny ke spusteni z K4 a po pouziti se muze smazat
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

@set_time_limit(0);

require_once __DIR__ . '/../../lib/app.php';
require_once __DIR__ . '/../../lib/format_datum_cas.php';
require_once __DIR__ . '/../../lib/denni_report_data.php';
require_once __DIR__ . '/../../config/secrets.php';

if (!function_exists('cb_drirc_h')) {
    function cb_drirc_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_drirc_fetch_reports')) {
    function cb_drirc_fetch_reports(mysqli $conn): array
    {
        $sql = "
            SELECT
                r.id_reportu,
                r.id_pob,
                r.datum_reportu,
                CASE WHEN ri.id_reportu IS NULL THEN 0 ELSE 1 END AS has_restia_row
            FROM reporty_is r
            LEFT JOIN reporty_is_restia ri
                ON ri.id_reportu = r.id_reportu
            WHERE r.platny = 1
            ORDER BY r.datum_reportu DESC, r.id_pob ASC, r.id_reportu DESC
        ";

        $result = $conn->query($sql);
        if (!$result) {
            throw new RuntimeException('Nepodarilo se nacist reporty: ' . $conn->error);
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                'id_reportu' => (int)($row['id_reportu'] ?? 0),
                'id_pob' => (int)($row['id_pob'] ?? 0),
                'datum_reportu' => trim((string)($row['datum_reportu'] ?? '')),
                'has_restia_row' => ((int)($row['has_restia_row'] ?? 0)) === 1,
            ];
        }
        $result->free();

        return $rows;
    }
}

if (!function_exists('cb_drirc_preview_data')) {
    function cb_drirc_preview_data(array $reports): array
    {
        $found = count($reports);
        $skipped = 0;

        foreach ($reports as $report) {
            if (!is_array($report) || empty($report['has_restia_row'])) {
                $skipped++;
            }
        }

        return [
            'found' => $found,
            'skipped' => $skipped,
            'updatable' => max(0, $found - $skipped),
        ];
    }
}

if (!function_exists('cb_drirc_run_update')) {
    function cb_drirc_run_update(mysqli $conn, array $reports): array
    {
        $summary = [
            'found' => count($reports),
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $stmt = $conn->prepare('
            UPDATE reporty_is_restia
            SET
                wolt_obj = ?,
                bolt_obj = ?,
                damejidlo_obj = ?,
                web_obj = ?,
                wolt_cash_obj = ?,
                dj_cash_obj = ?
            WHERE id_reportu = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('Nepodarilo se pripravit update reporty_is_restia.');
        }

        foreach ($reports as $report) {
            $idReportu = (int)($report['id_reportu'] ?? 0);
            $idPob = (int)($report['id_pob'] ?? 0);
            $datumReportu = trim((string)($report['datum_reportu'] ?? ''));
            $hasRestiaRow = !empty($report['has_restia_row']);

            if ($idReportu <= 0 || $idPob <= 0 || $datumReportu === '' || !$hasRestiaRow) {
                $summary['skipped']++;
                continue;
            }

            try {
                $workdayRange = cb_dt_workday_range_utc($datumReportu);
                $restiaSummary = cb_denni_report_restia_summary($conn, $idPob, $workdayRange);

                $woltObj = (int)($restiaSummary['wolt_count'] ?? 0);
                $boltObj = (int)($restiaSummary['bolt_count'] ?? 0);
                $damejidloObj = (int)($restiaSummary['dj_count'] ?? 0);
                $webObj = (int)($restiaSummary['web_count'] ?? 0);
                $woltCashObj = (int)($restiaSummary['wolt_cash_count'] ?? 0);
                $djCashObj = (int)($restiaSummary['dj_cash_count'] ?? 0);

                $stmt->bind_param(
                    'iiiiiii',
                    $woltObj,
                    $boltObj,
                    $damejidloObj,
                    $webObj,
                    $woltCashObj,
                    $djCashObj,
                    $idReportu
                );

                if ($stmt->execute() === false) {
                    throw new RuntimeException($stmt->error !== '' ? $stmt->error : 'Neznamy SQL problem pri update.');
                }

                $summary['updated']++;
            } catch (Throwable $e) {
                $summary['errors'][] = [
                    'id_reportu' => $idReportu,
                    'id_pob' => $idPob,
                    'datum_reportu' => $datumReportu,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $stmt->close();

        return $summary;
    }
}

$preview = [
    'found' => 0,
    'skipped' => 0,
    'updatable' => 0,
];
$resultSummary = null;
$error = '';

try {
    $conn = db();
    if (method_exists($conn, 'set_charset')) {
        $conn->set_charset('utf8mb4');
    }

    $reports = cb_drirc_fetch_reports($conn);
    $preview = cb_drirc_preview_data($reports);

    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'run') {
        $resultSummary = cb_drirc_run_update($conn, $reports);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Doplnit pocty objednavek do reporty_is_restia</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 16px; color: #222; }
        h1 { margin: 0 0 12px; font-size: 22px; }
        h2 { margin: 18px 0 10px; font-size: 18px; }
        p { margin: 8px 0; }
        .box { border: 1px solid #d0d7de; border-radius: 10px; padding: 12px; margin: 12px 0; background: #fff; }
        .ok { color: #0a7a28; font-weight: 700; }
        .err { color: #b00020; font-weight: 700; }
        .muted { color: #666; }
        .actions { display: flex; gap: 8px; margin: 12px 0; }
        button { height: 36px; padding: 0 14px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d0d7de; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f6f8fa; }
        .num { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <h1>Doplnit pocty objednavek do `reporty_is_restia`</h1>

    <div class="box">
        <p>Skript doplni do ulozenych K10 reportu pouze tyto sloupce:</p>
        <p><strong>wolt_obj, bolt_obj, damejidlo_obj, web_obj, wolt_cash_obj, dj_cash_obj</strong></p>
        <p class="muted">Pouziva stejnou logiku jako K10 pro pracovni den 06:00-06:00 a pro rozdeleni objednavek podle Restie.</p>
        <p class="muted">Nemeni castky, COL, prava ani zadne jine sloupce.</p>
    </div>

    <?php if ($error !== ''): ?>
        <p class="err"><?= cb_drirc_h($error) ?></p>
    <?php endif; ?>

    <div class="box">
        <h2>Nahled</h2>
        <table>
            <tbody>
                <tr>
                    <th>Nalezenych platnych reportu</th>
                    <td class="num"><?= cb_drirc_h((string)$preview['found']) ?></td>
                </tr>
                <tr>
                    <th>K aktualizaci</th>
                    <td class="num"><?= cb_drirc_h((string)$preview['updatable']) ?></td>
                </tr>
                <tr>
                    <th>Predem preskocenych</th>
                    <td class="num"><?= cb_drirc_h((string)$preview['skipped']) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="actions">
            <form method="post">
                <input type="hidden" name="action" value="preview">
                <button type="submit">Obnovit nahled</button>
            </form>
            <form method="post">
                <input type="hidden" name="action" value="run">
                <button type="submit">Spustit doplneni</button>
            </form>
        </div>
    </div>

    <?php if (is_array($resultSummary)): ?>
        <div class="box">
            <h2>Vysledek</h2>
            <table>
                <tbody>
                    <tr>
                        <th>Pocet nalezenych reportu</th>
                        <td class="num"><?= cb_drirc_h((string)$resultSummary['found']) ?></td>
                    </tr>
                    <tr>
                        <th>Pocet aktualizovanych reportu</th>
                        <td class="num"><?= cb_drirc_h((string)$resultSummary['updated']) ?></td>
                    </tr>
                    <tr>
                        <th>Pocet preskocenych reportu</th>
                        <td class="num"><?= cb_drirc_h((string)$resultSummary['skipped']) ?></td>
                    </tr>
                    <tr>
                        <th>Pocet chyb</th>
                        <td class="num"><?= cb_drirc_h((string)count($resultSummary['errors'])) ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if ($resultSummary['errors'] === []): ?>
                <p class="ok">Script dobehl bez chyb.</p>
            <?php else: ?>
                <h2>Chyby</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID reportu</th>
                            <th>ID pob</th>
                            <th>Datum reportu</th>
                            <th>Popis chyby</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultSummary['errors'] as $row): ?>
                            <tr>
                                <td><?= cb_drirc_h((string)($row['id_reportu'] ?? '')) ?></td>
                                <td><?= cb_drirc_h((string)($row['id_pob'] ?? '')) ?></td>
                                <td><?= cb_drirc_h((string)($row['datum_reportu'] ?? '')) ?></td>
                                <td><?= cb_drirc_h((string)($row['message'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>
