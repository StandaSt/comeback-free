<?php
// admin_testy/03_restia_test_objednavky_pobocky.php * Verze: V1 * Aktualizace: 09.04.2026
// Počet řádků: 000
// Předchozí počet řádků: 000
declare(strict_types=1);

/*
CO TENHLE SCRIPT DELA

- testuje stazeni objednavek z Restie bez zapisu objednavek do DB
- projde vsechny pobocky z tabulky pobocka s vyplnenym restia_activePosId
- radi je podle id_pob DESC
- pro kazdou pobocku udela jeden testovaci dotaz na /api/orders
- pouzije limit=10, tedy jen prvnich 10 objednavek pro dany rozsah
- testovaci rozsah je 01.04.2026 00:00:00 az 03.04.2026 23:59:59.999 lokalni cas Praha
- pro kazdou pobocku zapise samostatny podrobny TXT log test_nazevPobocky.txt
- do logu uklada parametry dotazu, HTTP status, X-Total-Count, pocet vratcenych zaznamu,
  ukazku odpovedi a srozumitelne shrnuti chyby

POZNAMKA
- script sam neuklada objednavky do DB
- jediny mozny zapis do DB je obnova access tokenu v restia_token,
  pokud by byl stary token expirovany a lib/restia_access_exist.php musela ziskat novy
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/system.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/../lib/restia_access_exist.php';
require_once __DIR__ . '/../lib/restia_client.php';

const CB_RESTIA_TEST_DATE_FROM = '2026-04-01 00:00:00.000';
const CB_RESTIA_TEST_DATE_TO = '2026-04-03 23:59:59.999';
const CB_RESTIA_TEST_LIMIT = 10;
const CB_RESTIA_TEST_VERZE = 'V1';

if (!function_exists('cb_restia_test_h')) {
    function cb_restia_test_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_restia_test_now_cs')) {
    function cb_restia_test_now_cs(): string
    {
        $tz = new DateTimeZone('Europe/Prague');
        return (new DateTimeImmutable('now', $tz))->format('d.m.Y H:i:s');
    }
}

if (!function_exists('cb_restia_test_local_to_utc_z')) {
    function cb_restia_test_local_to_utc_z(string $local): string
    {
        $tzLocal = new DateTimeZone('Europe/Prague');
        $tzUtc = new DateTimeZone('UTC');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.v', $local, $tzLocal);
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatny lokalni cas: ' . $local);
        }
        return $dt->setTimezone($tzUtc)->format('Y-m-d\TH:i:s.v\Z');
    }
}

if (!function_exists('cb_restia_test_branch_file_name')) {
    function cb_restia_test_branch_file_name(string $branchName): string
    {
        $name = trim($branchName);
        if ($name === '') {
            $name = 'pobocka';
        }

        $map = [
            'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
            'í' => 'i', 'ľ' => 'l', 'ĺ' => 'l', 'ň' => 'n', 'ó' => 'o', 'ô' => 'o',
            'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
            'Á' => 'A', 'Ä' => 'A', 'Č' => 'C', 'Ď' => 'D', 'É' => 'E', 'Ě' => 'E',
            'Í' => 'I', 'Ľ' => 'L', 'Ĺ' => 'L', 'Ň' => 'N', 'Ó' => 'O', 'Ô' => 'O',
            'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ú' => 'U', 'Ů' => 'U', 'Ý' => 'Y', 'Ž' => 'Z',
        ];
        $name = strtr($name, $map);
        $name = preg_replace('~[^A-Za-z0-9]+~', '_', $name);
        if (!is_string($name) || $name === '') {
            $name = 'pobocka';
        }
        $name = trim($name, '_');
        if ($name === '') {
            $name = 'pobocka';
        }

        return 'test_' . $name . '.txt';
    }
}

if (!function_exists('cb_restia_test_log_path')) {
    function cb_restia_test_log_path(string $branchName): string
    {
        return __DIR__ . '/' . cb_restia_test_branch_file_name($branchName);
    }
}

if (!function_exists('cb_restia_test_log_init')) {
    function cb_restia_test_log_init(string $branchName): string
    {
        $path = cb_restia_test_log_path($branchName);
        file_put_contents($path, '');
        return $path;
    }
}

if (!function_exists('cb_restia_test_log')) {
    function cb_restia_test_log(string $path, string $line = ''): void
    {
        file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_test_fetch_branches')) {
    function cb_restia_test_fetch_branches(mysqli $conn): array
    {
        $sql = '
            SELECT id_pob, nazev, restia_activePosId
            FROM pobocka
            WHERE aktivni = 1
              AND restia_activePosId IS NOT NULL
              AND restia_activePosId <> ""
            ORDER BY id_pob DESC
        ';

        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('DB dotaz na pobocky selhal.');
        }

        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'id_pob' => (int)($row['id_pob'] ?? 0),
                'nazev' => trim((string)($row['nazev'] ?? '')),
                'active_pos_id' => trim((string)($row['restia_activePosId'] ?? '')),
            ];
        }
        $res->free();

        return $out;
    }
}

if (!function_exists('cb_restia_test_count_orders')) {
    function cb_restia_test_count_orders(array $json): int
    {
        if (array_is_list($json)) {
            return count($json);
        }
        if (isset($json['data']) && is_array($json['data']) && array_is_list($json['data'])) {
            return count($json['data']);
        }
        if (isset($json['orders']) && is_array($json['orders']) && array_is_list($json['orders'])) {
            return count($json['orders']);
        }
        return 0;
    }
}

if (!function_exists('cb_restia_test_error_code')) {
    function cb_restia_test_error_code(array $json): string
    {
        if (!isset($json['error']) || !is_array($json['error'])) {
            return '';
        }
        return trim((string)($json['error']['code'] ?? ''));
    }
}

if (!function_exists('cb_restia_test_error_message')) {
    function cb_restia_test_error_message(array $json): string
    {
        if (!isset($json['error']) || !is_array($json['error'])) {
            return '';
        }
        return trim((string)($json['error']['message'] ?? ''));
    }
}

if (!function_exists('cb_restia_test_run_branch')) {
    function cb_restia_test_run_branch(array $branch, string $fromZ, string $toZ): array
    {
        $idPob = (int)($branch['id_pob'] ?? 0);
        $nazev = (string)($branch['nazev'] ?? '');
        $activePosId = (string)($branch['active_pos_id'] ?? '');
        $logPath = cb_restia_test_log_init($nazev);

        cb_restia_test_log($logPath, '-----');
        cb_restia_test_log($logPath, 'RESTIA TEST OBJEDNAVEK | ' . CB_RESTIA_TEST_VERZE);
        cb_restia_test_log($logPath, 'Spusteno: ' . cb_restia_test_now_cs());
        cb_restia_test_log($logPath, 'Pobocka: ' . $nazev);
        cb_restia_test_log($logPath, 'id_pob: ' . (string)$idPob);
        cb_restia_test_log($logPath, 'activePosId: ' . $activePosId);
        cb_restia_test_log($logPath, 'Rozsah lokalni cas Praha od: ' . CB_RESTIA_TEST_DATE_FROM);
        cb_restia_test_log($logPath, 'Rozsah lokalni cas Praha do: ' . CB_RESTIA_TEST_DATE_TO);
        cb_restia_test_log($logPath, 'Rozsah UTC od: ' . $fromZ);
        cb_restia_test_log($logPath, 'Rozsah UTC do: ' . $toZ);
        cb_restia_test_log($logPath, 'Limit: ' . (string)CB_RESTIA_TEST_LIMIT);
        cb_restia_test_log($logPath, 'Cil: zjistit, jestli Restia vrati objednavky nebo proc je nevrati.');
        cb_restia_test_log($logPath, '');
        cb_restia_test_log($logPath, 'Dotaz: GET /api/orders');
        cb_restia_test_log($logPath, 'Query parametry bez tokenu:');
        cb_restia_test_log($logPath, '  page=1');
        cb_restia_test_log($logPath, '  limit=' . (string)CB_RESTIA_TEST_LIMIT);
        cb_restia_test_log($logPath, '  createdFrom=' . $fromZ);
        cb_restia_test_log($logPath, '  createdTo=' . $toZ);
        cb_restia_test_log($logPath, '  activePosId=' . $activePosId);
        cb_restia_test_log($logPath, '');

        $t0 = microtime(true);
        $res = cb_restia_get(
            '/api/orders',
            [
                'page' => 1,
                'limit' => CB_RESTIA_TEST_LIMIT,
                'createdFrom' => $fromZ,
                'createdTo' => $toZ,
                'activePosId' => $activePosId,
            ],
            $activePosId,
            'test_pobocka id_pob=' . $idPob
        );
        $ms = (int)round((microtime(true) - $t0) * 1000);

        $httpStatus = (int)($res['http_status'] ?? 0);
        $ok = (int)($res['ok'] ?? 0);
        $body = (string)($res['body'] ?? '');
        $headers = $res['headers'] ?? [];
        $totalCount = $res['total_count'] ?? null;
        $totalCountText = 'neznamy';
        if ($totalCount !== null && $totalCount !== '') {
            $totalCountText = (string)((int)$totalCount);
        }

        cb_restia_test_log($logPath, 'HTTP status: ' . (string)$httpStatus);
        cb_restia_test_log($logPath, 'Doba volani: ' . (string)$ms . ' ms');
        cb_restia_test_log($logPath, 'X-Total-Count: ' . $totalCountText);

        if (is_array($headers) && $headers !== []) {
            cb_restia_test_log($logPath, 'Response headery:');
            foreach ($headers as $header) {
                if (!is_string($header)) {
                    continue;
                }
                cb_restia_test_log($logPath, '  ' . $header);
            }
        } else {
            cb_restia_test_log($logPath, 'Response headery: zadne nebo nenactene');
        }

        $json = json_decode($body, true);
        $jsonOk = is_array($json);
        $bodySnippet = mb_substr($body, 0, 2000);

        if ($jsonOk) {
            $orderCount = cb_restia_test_count_orders($json);
            $errorCode = cb_restia_test_error_code($json);
            $errorMessage = cb_restia_test_error_message($json);

            cb_restia_test_log($logPath, 'Pocet objednavek v tele odpovedi: ' . (string)$orderCount);

            if ($errorCode !== '' || $errorMessage !== '') {
                cb_restia_test_log($logPath, 'API chyba kod: ' . $errorCode);
                cb_restia_test_log($logPath, 'API chyba zprava: ' . $errorMessage);
            }

            cb_restia_test_log($logPath, 'Ukazka JSON odpovedi:');
            cb_restia_test_log($logPath, $bodySnippet);
            cb_restia_test_log($logPath, '');

            $summary = 'OK';
            if ($ok !== 1) {
                $summary = 'ERR';
            }
            if ($ok === 1 && $orderCount === 0) {
                $summary = 'OK_BEZ_OBJEDNAVEK';
            }

            cb_restia_test_log($logPath, 'Shrnuti: ' . $summary);
            if ($ok === 1 && $orderCount > 0) {
                cb_restia_test_log($logPath, 'Restia vratila objednavky. Problem neni v autorizaci teto pobocky.');
            }
            if ($ok === 1 && $orderCount === 0) {
                cb_restia_test_log($logPath, 'Restia dotaz prijala, ale v danem rozsahu vratila 0 objednavek.');
            }
            if ($ok !== 1 && $errorCode !== '') {
                cb_restia_test_log($logPath, 'Restia vratila chybu kodu ' . $errorCode . '.');
            }

            return [
                'id_pob' => $idPob,
                'nazev' => $nazev,
                'active_pos_id' => $activePosId,
                'http_status' => $httpStatus,
                'ok' => $ok,
                'order_count' => $orderCount,
                'total_count' => $totalCountText,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'log_file' => basename($logPath),
                'log_path' => $logPath,
            ];
        }

        cb_restia_test_log($logPath, 'Telo odpovedi neni validni JSON.');
        cb_restia_test_log($logPath, 'Ukazka tela odpovedi:');
        cb_restia_test_log($logPath, $bodySnippet);
        cb_restia_test_log($logPath, '');
        cb_restia_test_log($logPath, 'Shrnuti: ERR_NEPLATNY_JSON');

        return [
            'id_pob' => $idPob,
            'nazev' => $nazev,
            'active_pos_id' => $activePosId,
            'http_status' => $httpStatus,
            'ok' => 0,
            'order_count' => 0,
            'total_count' => $totalCountText,
            'error_code' => '',
            'error_message' => 'Neplatny JSON nebo prazdna odpoved.',
            'log_file' => basename($logPath),
            'log_path' => $logPath,
        ];
    }
}

$conn = db();
$branches = [];
$results = [];
$fatalMessage = '';
$fromZ = '';
$toZ = '';

try {
    $fromZ = cb_restia_test_local_to_utc_z(CB_RESTIA_TEST_DATE_FROM);
    $toZ = cb_restia_test_local_to_utc_z(CB_RESTIA_TEST_DATE_TO);
    $branches = cb_restia_test_fetch_branches($conn);

    foreach ($branches as $branch) {
        $results[] = cb_restia_test_run_branch($branch, $fromZ, $toZ);
    }
} catch (Throwable $e) {
    $fatalMessage = $e->getMessage();
}

$cardMinText = 'Restia test objednavek 01.04.2026-03.04.2026, limit 10, bez zapisu objednavek do DB.';

ob_start();
?>
<div class="ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
  <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Restia test objednávek podle poboček</h2>
  <p class="card_text txt_seda">Rozsah lokalně Praha: <?= cb_restia_test_h(CB_RESTIA_TEST_DATE_FROM) ?> až <?= cb_restia_test_h(CB_RESTIA_TEST_DATE_TO) ?></p>
  <p class="card_text txt_seda">Rozsah UTC: <?= cb_restia_test_h($fromZ) ?> až <?= cb_restia_test_h($toZ) ?></p>
  <p class="card_text txt_seda">Limit na pobočku: <?= cb_restia_test_h((string)CB_RESTIA_TEST_LIMIT) ?></p>
  <p class="card_text txt_seda">Pobočky: <?= cb_restia_test_h((string)count($branches)) ?></p>

  <?php if ($fatalMessage !== ''): ?>
    <p class="card_text txt_cervena text_tucny"><?= cb_restia_test_h($fatalMessage) ?></p>
  <?php endif; ?>

  <div class="table-wrap">
    <table class="table ram_normal bg_bila radek_1_35" style="width:100%;">
      <thead>
        <tr>
          <th>id_pob</th>
          <th>Pobočka</th>
          <th>HTTP</th>
          <th>Objednávek v odpovědi</th>
          <th>X-Total-Count</th>
          <th>Kód chyby</th>
          <th>Zpráva</th>
          <th>Log</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($results === []): ?>
          <tr>
            <td colspan="8">Žádná data.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($results as $row): ?>
            <tr>
              <td><?= cb_restia_test_h((string)($row['id_pob'] ?? 0)) ?></td>
              <td><?= cb_restia_test_h((string)($row['nazev'] ?? '')) ?></td>
              <td><?= cb_restia_test_h((string)($row['http_status'] ?? 0)) ?></td>
              <td><?= cb_restia_test_h((string)($row['order_count'] ?? 0)) ?></td>
              <td><?= cb_restia_test_h((string)($row['total_count'] ?? '')) ?></td>
              <td><?= cb_restia_test_h((string)($row['error_code'] ?? '')) ?></td>
              <td style="white-space:pre-wrap;"><?= cb_restia_test_h((string)($row['error_message'] ?? '')) ?></td>
              <td><?= cb_restia_test_h((string)($row['log_file'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="card_text txt_seda odstup_horni_10">TXT logy jsou uložené do admin_testy/ jako test_*.txt.</p>
  <p class="card_text txt_seda">Script neukládá objednávky do DB. Slouží jen pro ověření odpovědi Restie.</p>
</div>
<?php
$card_max_html = (string)ob_get_clean();
$card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0">' . cb_restia_test_h($cardMinText) . '</p>';
