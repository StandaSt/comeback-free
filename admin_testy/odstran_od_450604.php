<?php
// admin_testy/odstran_od_450604.php * Verze: V1 * Aktualizace: 28.04.2026
declare(strict_types=1);

/*
CO TENHLE SCRIPT DELA

- smaze vsechny Restia objednavky s id_obj > 450604
- smaze i navazana data ve vsech pouzivanych tabulkach
- pred smazanim ukaze pocty zasazenych radku
- maze v jedne DB transakci
- pri chybe provede rollback
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../db/db_connect.php';

const CB_RESTIA_DELETE_FROM_ID = 450604;

if (!function_exists('cb_restia_delete_h')) {
    function cb_restia_delete_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_restia_delete_fetch_counts')) {
    function cb_restia_delete_fetch_counts(mysqli $conn, int $fromId): array
    {
        $counts = [];

        $queries = [
            'objednavky_restia' => 'SELECT COUNT(*) AS cnt FROM objednavky_restia WHERE id_obj > ?',
            'obj_casy' => 'SELECT COUNT(*) AS cnt FROM obj_casy WHERE id_obj > ?',
            'obj_ceny' => 'SELECT COUNT(*) AS cnt FROM obj_ceny WHERE id_obj > ?',
            'obj_kuryr' => 'SELECT COUNT(*) AS cnt FROM obj_kuryr WHERE id_obj > ?',
            'obj_sluzba' => 'SELECT COUNT(*) AS cnt FROM obj_sluzba WHERE id_obj > ?',
            'obj_polozky' => 'SELECT COUNT(*) AS cnt FROM obj_polozky WHERE id_obj > ?',
            'obj_polozka_mod' => '
                SELECT COUNT(*) AS cnt
                FROM obj_polozka_mod m
                JOIN obj_polozky p ON p.id_obj_polozka = m.id_obj_polozka
                WHERE p.id_obj > ?
            ',
            'obj_polozka_kds_tag' => '
                SELECT COUNT(*) AS cnt
                FROM obj_polozka_kds_tag t
                JOIN obj_polozky p ON p.id_obj_polozka = t.id_obj_polozka
                WHERE p.id_obj > ?
            ',
        ];

        foreach ($queries as $table => $sql) {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal pro ' . $table . '.');
            }
            $stmt->bind_param('i', $fromId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
            if ($res instanceof mysqli_result) {
                $res->free();
            }
            $stmt->close();
            $counts[$table] = (int)($row['cnt'] ?? 0);
        }

        return $counts;
    }
}

if (!function_exists('cb_restia_delete_run')) {
    function cb_restia_delete_run(mysqli $conn, int $fromId): array
    {
        $affected = [];

        $conn->begin_transaction();
        try {
            $steps = [
                'obj_polozka_kds_tag' => '
                    DELETE t
                    FROM obj_polozka_kds_tag t
                    JOIN obj_polozky p ON p.id_obj_polozka = t.id_obj_polozka
                    WHERE p.id_obj > ?
                ',
                'obj_polozka_mod' => '
                    DELETE m
                    FROM obj_polozka_mod m
                    JOIN obj_polozky p ON p.id_obj_polozka = m.id_obj_polozka
                    WHERE p.id_obj > ?
                ',
                'obj_polozky' => 'DELETE FROM obj_polozky WHERE id_obj > ?',
                'obj_kuryr' => 'DELETE FROM obj_kuryr WHERE id_obj > ?',
                'obj_sluzba' => 'DELETE FROM obj_sluzba WHERE id_obj > ?',
                'obj_casy' => 'DELETE FROM obj_casy WHERE id_obj > ?',
                'obj_ceny' => 'DELETE FROM obj_ceny WHERE id_obj > ?',
                'objednavky_restia' => 'DELETE FROM objednavky_restia WHERE id_obj > ?',
            ];

            foreach ($steps as $table => $sql) {
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    throw new RuntimeException('DB prepare selhal pro delete ' . $table . '.');
                }
                $stmt->bind_param('i', $fromId);
                $stmt->execute();
                $affected[$table] = (int)$stmt->affected_rows;
                $stmt->close();
            }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        return $affected;
    }
}

$conn = db_connect();
$fromId = (int)CB_RESTIA_DELETE_FROM_ID;
$doRun = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') && (string)($_POST['confirm_delete'] ?? '') === 'yes';
$counts = [];
$affected = [];
$error = '';
$done = false;

try {
    $counts = cb_restia_delete_fetch_counts($conn, $fromId);
    if ($doRun) {
        $affected = cb_restia_delete_run($conn, $fromId);
        $done = true;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Odstranit Restia objednavky od 450605</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:Arial,sans-serif;margin:16px;color:#1f2937;background:#fff;}
    table{border-collapse:collapse;width:100%;max-width:780px;margin-top:12px;}
    th,td{border:1px solid #cbd5e1;padding:8px 10px;text-align:left;}
    th{background:#f8fafc;}
    .ok{color:#166534;font-weight:700;}
    .err{color:#b91c1c;font-weight:700;}
    .warn{color:#92400e;font-weight:700;}
    .btn{height:34px;padding:0 14px;border:1px solid #94a3b8;border-radius:8px;background:#f8fafc;cursor:pointer;}
  </style>
</head>
<body>
  <h1>Odstranit Restia objednavky od `id_obj > 450604`</h1>

  <?php if ($error !== ''): ?>
    <p class="err">Chyba: <?= cb_restia_delete_h($error) ?></p>
  <?php endif; ?>

  <?php if ($done): ?>
    <p class="ok">Mazani probehlo v poradku.</p>
  <?php else: ?>
    <p class="warn">Pozor: skript smaze vsechny navazane zaznamy pro `id_obj > 450604`.</p>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>Tabulka</th>
        <th>Radku k zasahu</th>
        <?php if ($done): ?><th>Smazano</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($counts as $table => $count): ?>
        <tr>
          <td><?= cb_restia_delete_h($table) ?></td>
          <td><?= cb_restia_delete_h((string)$count) ?></td>
          <?php if ($done): ?><td><?= cb_restia_delete_h((string)($affected[$table] ?? 0)) ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if (!$done): ?>
    <form method="post" style="margin-top:16px;">
      <input type="hidden" name="confirm_delete" value="yes">
      <button type="submit" class="btn">Potvrdit smazani</button>
    </form>
  <?php endif; ?>
</body>
</html>
