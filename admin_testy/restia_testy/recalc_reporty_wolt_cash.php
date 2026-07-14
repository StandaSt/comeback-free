<?php
// admin_testy/restia_testy/recalc_reporty_wolt_cash.php * Verze: V1 * Aktualizace: 18.06.2026
declare(strict_types=1);

/*
CO TENHLE SCRIPT DELA

- zobrazi nahled reportu, kde je wolt_cash v reporty_is_restia spatne nebo nulove
- prepocet bere jen overenou logiku:
  - platforma = generic
  - doruceni = delivery
  - platba = cash
- vazba na report je pres:
  - reporty_is.id_pob
  - reporty_is.datum_reportu = DATE(objednavky_restia.restia_created_at)
- nic jineho nemeni, updatuje pouze reporty_is_restia.wolt_cash
- zapis provede az po potvrzeni pres POST

*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../lib/app.php';
require_once __DIR__ . '/../../config/secrets.php';

if (!function_exists('cb_rwlc_h')) {
    function cb_rwlc_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_rwlc_db')) {
    function cb_rwlc_db(): mysqli
    {
        return db();
    }
}

if (!function_exists('cb_rwlc_preview_sql')) {
    function cb_rwlc_preview_sql(): string
    {
        return "
            SELECT
                r.id_reportu,
                r.datum_reportu,
                r.id_pob,
                ROUND(COALESCE(ri.wolt_cash, 0), 2) AS old_wolt_cash,
                ROUND(COALESCE(src.live_wolt_cash, 0), 2) AS new_wolt_cash
            FROM reporty_is_restia ri
            INNER JOIN reporty_is r
                ON r.id_reportu = ri.id_reportu
            LEFT JOIN (
                SELECT
                    o.id_pob,
                    DATE(o.restia_created_at) AS datum_reportu,
                    SUM(
                        CASE
                            WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted')
                             AND cp.kod = 'generic'
                             AND COALESCE(d.nazev, '') = 'delivery'
                             AND COALESCE(p.nazev, '') = 'cash'
                            THEN COALESCE(c.cena_celk, 0)
                            ELSE 0
                        END
                    ) AS live_wolt_cash
                FROM objednavky_restia o
                LEFT JOIN cis_obj_platforma cp
                    ON cp.id_platforma = o.id_platforma
                LEFT JOIN cis_doruceni d
                    ON d.id_doruceni = o.id_doruceni
                LEFT JOIN cis_obj_platby p
                    ON p.id_platba = o.id_platba
                LEFT JOIN cis_obj_stav s
                    ON s.id_stav = o.id_stav
                LEFT JOIN obj_ceny c
                    ON c.id_obj = o.id_obj
                WHERE o.restia_created_at IS NOT NULL
                GROUP BY o.id_pob, DATE(o.restia_created_at)
            ) src
                ON src.id_pob = r.id_pob
               AND src.datum_reportu = r.datum_reportu
            WHERE ABS(COALESCE(ri.wolt_cash, 0) - COALESCE(src.live_wolt_cash, 0)) > 0.009
            ORDER BY r.datum_reportu DESC, r.id_pob DESC, r.id_reportu DESC
        ";
    }
}

if (!function_exists('cb_rwlc_update_sql')) {
    function cb_rwlc_update_sql(): string
    {
        return "
            UPDATE reporty_is_restia ri
            INNER JOIN reporty_is r
                ON r.id_reportu = ri.id_reportu
            LEFT JOIN (
                SELECT
                    o.id_pob,
                    DATE(o.restia_created_at) AS datum_reportu,
                    SUM(
                        CASE
                            WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted')
                             AND cp.kod = 'generic'
                             AND COALESCE(d.nazev, '') = 'delivery'
                             AND COALESCE(p.nazev, '') = 'cash'
                            THEN COALESCE(c.cena_celk, 0)
                            ELSE 0
                        END
                    ) AS live_wolt_cash
                FROM objednavky_restia o
                LEFT JOIN cis_obj_platforma cp
                    ON cp.id_platforma = o.id_platforma
                LEFT JOIN cis_doruceni d
                    ON d.id_doruceni = o.id_doruceni
                LEFT JOIN cis_obj_platby p
                    ON p.id_platba = o.id_platba
                LEFT JOIN cis_obj_stav s
                    ON s.id_stav = o.id_stav
                LEFT JOIN obj_ceny c
                    ON c.id_obj = o.id_obj
                WHERE o.restia_created_at IS NOT NULL
                GROUP BY o.id_pob, DATE(o.restia_created_at)
            ) src
                ON src.id_pob = r.id_pob
               AND src.datum_reportu = r.datum_reportu
            SET ri.wolt_cash = COALESCE(src.live_wolt_cash, 0)
            WHERE ABS(COALESCE(ri.wolt_cash, 0) - COALESCE(src.live_wolt_cash, 0)) > 0.009
        ";
    }
}

if (!function_exists('cb_rwlc_preview_rows')) {
    function cb_rwlc_preview_rows(mysqli $conn): array
    {
        $rows = [];
        $result = $conn->query(cb_rwlc_preview_sql());
        if (!$result) {
            throw new RuntimeException('Nahled selhal: ' . $conn->error);
        }

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $result->free();
        return $rows;
    }
}

$message = '';
$error = '';
$updatedRows = null;

try {
    $conn = cb_rwlc_db();
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'update') {
        $previewBefore = cb_rwlc_preview_rows($conn);

        if ($previewBefore !== []) {
            $conn->begin_transaction();
            try {
                if ($conn->query(cb_rwlc_update_sql()) === false) {
                    throw new RuntimeException('Update selhal: ' . $conn->error);
                }
                $updatedRows = $conn->affected_rows;
                $conn->commit();
                $message = 'Update probehl. Upravenych radku: ' . (string)$updatedRows . '.';
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
        } else {
            $updatedRows = 0;
            $message = 'Neni co opravovat.';
        }
    }

    $previewRows = cb_rwlc_preview_rows($conn);
    $previewCount = count($previewRows);
    $previewShow = array_slice($previewRows, 0, 30);
} catch (Throwable $e) {
    $error = $e->getMessage();
    $previewRows = [];
    $previewCount = 0;
    $previewShow = [];
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Recalc reporty wolt_cash</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 16px; color: #222; }
        h1 { margin: 0 0 12px; font-size: 22px; }
        p { margin: 8px 0; }
        .ok { color: #0a7a28; font-weight: 700; }
        .err { color: #b00020; font-weight: 700; }
        .box { border: 1px solid #d0d7de; border-radius: 10px; padding: 12px; margin: 12px 0; background: #fff; }
        .actions { display: flex; gap: 8px; margin: 12px 0; }
        button { height: 36px; padding: 0 14px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d0d7de; padding: 8px; text-align: left; }
        th { background: #f6f8fa; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <h1>Recalc `reporty_is_restia.wolt_cash`</h1>

    <div class="box">
        <p>Skript prepocita pouze sloupec <strong>wolt_cash</strong> podle logiky:</p>
        <p><strong>platforma = generic + doruceni = delivery + platba = cash</strong></p>
        <p class="muted">Nevklada nove reporty. Nemeni trzbu ani zadny jiny sloupec.</p>
    </div>

    <?php if ($message !== ''): ?>
        <p class="ok"><?= cb_rwlc_h($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="err"><?= cb_rwlc_h($error) ?></p>
    <?php endif; ?>

    <div class="actions">
        <form method="post">
            <input type="hidden" name="action" value="preview">
            <button type="submit">Obnovit nahled</button>
        </form>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <button type="submit">Provest update</button>
        </form>
    </div>

    <div class="box">
        <p><strong>Radku k oprave:</strong> <?= cb_rwlc_h((string)$previewCount) ?></p>
        <?php if ($updatedRows !== null): ?>
            <p><strong>Naposledy upraveno:</strong> <?= cb_rwlc_h((string)$updatedRows) ?> radku</p>
        <?php endif; ?>
        <p class="muted">Tlacitko Provest update spusti rovnou zapis. Nejdriv zkontroluj nahled vyse.</p>

        <?php if ($previewShow === []): ?>
            <p class="ok">Nahled je prazdny. Vsechny wolt_cash hodnoty uz sedi.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID reportu</th>
                        <th>Datum</th>
                        <th>ID pob</th>
                        <th class="num">Stare wolt_cash</th>
                        <th class="num">Nove wolt_cash</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewShow as $row): ?>
                        <tr>
                            <td><?= cb_rwlc_h((string)($row['id_reportu'] ?? '')) ?></td>
                            <td><?= cb_rwlc_h((string)($row['datum_reportu'] ?? '')) ?></td>
                            <td><?= cb_rwlc_h((string)($row['id_pob'] ?? '')) ?></td>
                            <td class="num"><?= cb_rwlc_h((string)($row['old_wolt_cash'] ?? '')) ?></td>
                            <td class="num"><?= cb_rwlc_h((string)($row['new_wolt_cash'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($previewCount > count($previewShow)): ?>
                <p class="muted">Zobrazeno prvnich <?= cb_rwlc_h((string)count($previewShow)) ?> radku z <?= cb_rwlc_h((string)$previewCount) ?>.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
