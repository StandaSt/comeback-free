<?php
// admin_testy/kontrola_db_restia_vazby.php * Verze: V1 * Aktualizace: 28.04.2026
declare(strict_types=1);

/*
CO TENHLE SCRIPT DELA

- zkontroluje vazby mezi objednavky_restia, obj_casy a obj_ceny
- najde chybejici obj_casy k objednavkam
- najde chybejici obj_ceny k objednavkam
- najde osiřele obj_casy a obj_ceny bez rodice
- najde duplicity v obj_ceny podle id_obj
- vysledek zapise do log/kontrola_db.txt
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../db/db_connect.php';

const CB_KONTROLA_DB_LIMIT = 500;

if (!function_exists('cb_kontrola_db_h')) {
    function cb_kontrola_db_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_kontrola_db_now')) {
    function cb_kontrola_db_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('cb_kontrola_db_log_path')) {
    function cb_kontrola_db_log_path(): string
    {
        return dirname(__DIR__) . '/log/kontrola_db.txt';
    }
}

if (!function_exists('cb_kontrola_db_fetch_count')) {
    function cb_kontrola_db_fetch_count(mysqli $conn, string $sql): int
    {
        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('DB query selhal: ' . $conn->error);
        }

        $row = $res->fetch_assoc();
        $res->free();

        return (int)($row['cnt'] ?? 0);
    }
}

if (!function_exists('cb_kontrola_db_fetch_rows')) {
    function cb_kontrola_db_fetch_rows(mysqli $conn, string $sql): array
    {
        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('DB query selhal: ' . $conn->error);
        }

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        $res->free();

        return $rows;
    }
}

if (!function_exists('cb_kontrola_db_build_report')) {
    function cb_kontrola_db_build_report(mysqli $conn): string
    {
        $limit = (int)CB_KONTROLA_DB_LIMIT;
        $lines = [];

        $lines[] = 'KONTROLA DB RESTIA VAZEB';
        $lines[] = 'Datum: ' . cb_kontrola_db_now();
        $lines[] = 'Limit ukazek na sekci: ' . (string)$limit;
        $lines[] = '';

        $totals = [
            'objednavky_restia' => cb_kontrola_db_fetch_count($conn, 'SELECT COUNT(*) AS cnt FROM objednavky_restia'),
            'obj_casy' => cb_kontrola_db_fetch_count($conn, 'SELECT COUNT(*) AS cnt FROM obj_casy'),
            'obj_ceny' => cb_kontrola_db_fetch_count($conn, 'SELECT COUNT(*) AS cnt FROM obj_ceny'),
        ];

        $lines[] = 'CELKOVE POCTY';
        foreach ($totals as $table => $count) {
            $lines[] = '- ' . $table . ': ' . (string)$count;
        }
        $lines[] = '';

        $checks = [
            [
                'title' => 'OBJEDNAVKY BEZ OBJ_CASY',
                'count_sql' => '
                    SELECT COUNT(*) AS cnt
                    FROM objednavky_restia o
                    LEFT JOIN obj_casy c ON c.id_obj = o.id_obj
                    WHERE c.id_obj IS NULL
                ',
                'rows_sql' => '
                    SELECT o.id_obj
                    FROM objednavky_restia o
                    LEFT JOIN obj_casy c ON c.id_obj = o.id_obj
                    WHERE c.id_obj IS NULL
                    ORDER BY o.id_obj DESC
                    LIMIT ' . $limit,
            ],
            [
                'title' => 'OBJEDNAVKY BEZ OBJ_CENY',
                'count_sql' => '
                    SELECT COUNT(*) AS cnt
                    FROM objednavky_restia o
                    LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
                    WHERE c.id_obj IS NULL
                ',
                'rows_sql' => '
                    SELECT o.id_obj
                    FROM objednavky_restia o
                    LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
                    WHERE c.id_obj IS NULL
                    ORDER BY o.id_obj DESC
                    LIMIT ' . $limit,
            ],
            [
                'title' => 'OBJ_CASY BEZ OBJEDNAVKY',
                'count_sql' => '
                    SELECT COUNT(*) AS cnt
                    FROM obj_casy c
                    LEFT JOIN objednavky_restia o ON o.id_obj = c.id_obj
                    WHERE o.id_obj IS NULL
                ',
                'rows_sql' => '
                    SELECT c.id_obj
                    FROM obj_casy c
                    LEFT JOIN objednavky_restia o ON o.id_obj = c.id_obj
                    WHERE o.id_obj IS NULL
                    ORDER BY c.id_obj DESC
                    LIMIT ' . $limit,
            ],
            [
                'title' => 'OBJ_CENY BEZ OBJEDNAVKY',
                'count_sql' => '
                    SELECT COUNT(*) AS cnt
                    FROM obj_ceny c
                    LEFT JOIN objednavky_restia o ON o.id_obj = c.id_obj
                    WHERE o.id_obj IS NULL
                ',
                'rows_sql' => '
                    SELECT c.id_obj
                    FROM obj_ceny c
                    LEFT JOIN objednavky_restia o ON o.id_obj = c.id_obj
                    WHERE o.id_obj IS NULL
                    ORDER BY c.id_obj DESC
                    LIMIT ' . $limit,
            ],
            [
                'title' => 'DUPLICITY V OBJ_CENY PODLE ID_OBJ',
                'count_sql' => '
                    SELECT COUNT(*) AS cnt
                    FROM (
                        SELECT c.id_obj
                        FROM obj_ceny c
                        GROUP BY c.id_obj
                        HAVING COUNT(*) > 1
                    ) x
                ',
                'rows_sql' => '
                    SELECT c.id_obj, COUNT(*) AS cnt
                    FROM obj_ceny c
                    GROUP BY c.id_obj
                    HAVING COUNT(*) > 1
                    ORDER BY c.id_obj DESC
                    LIMIT ' . $limit,
            ],
        ];

        foreach ($checks as $check) {
            $title = (string)$check['title'];
            $count = cb_kontrola_db_fetch_count($conn, (string)$check['count_sql']);
            $rows = cb_kontrola_db_fetch_rows($conn, (string)$check['rows_sql']);

            $lines[] = $title;
            $lines[] = 'Pocet: ' . (string)$count;

            if ($rows === []) {
                $lines[] = 'Ukazka: bez zaznamu';
                $lines[] = '';
                continue;
            }

            $lines[] = 'Ukazka:';
            foreach ($rows as $row) {
                $pairs = [];
                foreach ($row as $key => $value) {
                    $pairs[] = (string)$key . '=' . (string)$value;
                }
                $lines[] = '- ' . implode(', ', $pairs);
            }
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }
}

$error = '';
$logPath = cb_kontrola_db_log_path();

try {
    $conn = db_connect();
    $report = cb_kontrola_db_build_report($conn);
    if (@file_put_contents($logPath, $report, LOCK_EX) === false) {
        throw new RuntimeException('Nepodarilo se zapsat do log/kontrola_db.txt.');
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Kontrola DB vazeb</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:Arial,sans-serif;margin:16px;color:#1f2937;background:#fff;}
    .ok{color:#166534;font-weight:700;}
    .err{color:#b91c1c;font-weight:700;}
    code{background:#f1f5f9;padding:2px 4px;border-radius:4px;}
  </style>
</head>
<body>
  <h1>Kontrola DB vazeb</h1>
  <?php if ($error !== ''): ?>
    <p class="err">Chyba: <?= cb_kontrola_db_h($error) ?></p>
  <?php else: ?>
    <p class="ok">Hotovo. Vystup je v <code>log/kontrola_db.txt</code>.</p>
  <?php endif; ?>
</body>
</html>
