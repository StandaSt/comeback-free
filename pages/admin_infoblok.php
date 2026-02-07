<?php
// pages/admin_infoblok.php * Verze: V2 * Aktualizace: 7.2.2026 * Počet řádků: 236
declare(strict_types=1);

/*
 * Informace o systému – přehledná tabulka (Název | Hodnota | Popis).
 *
 * Pravidla:
 * - pouze obsah stránky (bez hlavičky/patičky/layout include)
 * - co se otevře, se zde i zavře
 */

require_once __DIR__ . '/../lib/bootstrap.php';

function h2(mixed $v): string { return h($v); }

$rowsServer = [];
$addS = function(string $nazev, string $hodnota, string $popis) use (&$rowsServer): void {
    $rowsServer[] = [$nazev, $hodnota, $popis];
};

$addS('PHP verze', PHP_VERSION, 'Verze PHP na serveru.');
$addS('SAPI', (string)PHP_SAPI, 'Typ běhu PHP (např. apache2handler, fpm-fcgi).');
$addS('OS', (string)php_uname(), 'Operační systém / kernel.');
$addS('Server name', (string)($_SERVER['SERVER_NAME'] ?? '---'), 'Jméno serveru z HTTP požadavku.');
$addS('HTTP host', (string)($_SERVER['HTTP_HOST'] ?? '---'), 'Host z prohlížeče.');
$addS('Server addr', (string)($_SERVER['SERVER_ADDR'] ?? '---'), 'IP adresa serveru.');
$addS('Remote addr', (string)($_SERVER['REMOTE_ADDR'] ?? '---'), 'IP adresa klienta (z pohledu serveru).');
$addS('HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'ano' : 'ne', 'Zda je požadavek přes HTTPS.');
$addS('Document root', (string)($_SERVER['DOCUMENT_ROOT'] ?? '---'), 'Kořen webu na disku serveru.');
$addS('Script name', (string)($_SERVER['SCRIPT_NAME'] ?? '---'), 'Cesta ke spuštěnému skriptu (URL část).');
$addS('Request URI', (string)($_SERVER['REQUEST_URI'] ?? '---'), 'Celá požadovaná URI.');
$addS('Čas serveru', date('j.n.Y H:i:s'), 'Aktuální čas na serveru.');

$rowsDb = [];
$addD = function(string $nazev, string $hodnota, string $popis) use (&$rowsDb): void {
    $rowsDb[] = [$nazev, $hodnota, $popis];
};

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    // základní info
    $addD('DB host', (string)$conn->host_info, 'Informace o připojení (mysqli host_info).');

    // verze serveru
    $res = $conn->query('SELECT VERSION() AS v');
    if ($res) {
        $r = $res->fetch_assoc();
        $addD('DB verze', (string)($r['v'] ?? '---'), 'Verze databázového serveru.');
        $res->free();
    }

    // vybrané proměnné (bez rizikových/velkých)
    $vars = [
        'version_comment',
        'version_compile_os',
        'version_compile_machine',
        'character_set_server',
        'collation_server',
        'time_zone',
        'max_connections',
    ];

    foreach ($vars as $k) {
        $sql = "SHOW VARIABLES LIKE '" . $conn->real_escape_string($k) . "'";
        $res = $conn->query($sql);
        if ($res) {
            $row = $res->fetch_assoc();
            $val = (string)($row['Value'] ?? '---');
            $addD('VAR: ' . $k, $val, 'MySQL/MariaDB proměnná.');
            $res->free();
        }
    }

} catch (Throwable $e) {
    $addD('DB', 'nelze načíst', 'Chyba při přístupu do DB: ' . $e->getMessage());
}

?>
<section class="card">

  <style>
    /* lokální úpravy jen pro tuto stránku */
    .infoblok-wrap { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start; }
    .infoblok-box { flex: 0 0 auto; }
    .infoblok-title { margin: 0 0 8px; font-size: 16px; font-weight: 700; }
    .infoblok-table.table { width: auto; }
    .infoblok-table th, .infoblok-table td { font-size: 13px; }
    .infoblok-table .c-nazev { width: 160px; }
    .infoblok-table .c-hodnota { width: 420px; }
    .infoblok-table .c-popis { width: 260px; }
    .infoblok-muted { color: #666; font-size: 13px; margin: 0 0 12px; }
  </style>

  <p class="infoblok-muted">Přehled informací, které umíme zjistit (server, DB, klient).</p>

  <div class="infoblok-wrap">

    <div class="infoblok-box">
      <div class="infoblok-title">Server</div>
      <table class="table table-fixed infoblok-table">
        <thead>
          <tr>
            <th class="c-nazev">Název</th>
            <th class="c-hodnota">Hodnota</th>
            <th class="c-popis">Popis</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rowsServer as $r): ?>
            <tr>
              <td class="c-nazev"><?= h2($r[0]) ?></td>
              <td class="c-hodnota"><?= h2($r[1]) ?></td>
              <td class="c-popis"><?= h2($r[2]) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="infoblok-box">
      <div class="infoblok-title">DB</div>
      <table class="table table-fixed infoblok-table">
        <thead>
          <tr>
            <th class="c-nazev">Název</th>
            <th class="c-hodnota">Hodnota</th>
            <th class="c-popis">Popis</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rowsDb as $r): ?>
            <tr>
              <td class="c-nazev"><?= h2($r[0]) ?></td>
              <td class="c-hodnota"><?= h2($r[1]) ?></td>
              <td class="c-popis"><?= h2($r[2]) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="infoblok-box">
      <div class="infoblok-title">Klient</div>
      <table class="table table-fixed infoblok-table" id="cbClientTable">
        <thead>
          <tr>
            <th class="c-nazev">Název</th>
            <th class="c-hodnota">Hodnota</th>
            <th class="c-popis">Popis</th>
          </tr>
        </thead>
        <tbody>
          <tr><td class="c-nazev">User-Agent</td><td class="c-hodnota" id="cbC_ua">---</td><td class="c-popis">Identifikace prohlížeče (zkrácená).</td></tr>
          <tr><td class="c-nazev">Jazyk</td><td class="c-hodnota" id="cbC_lang">---</td><td class="c-popis">navigator.language</td></tr>
          <tr><td class="c-nazev">Rozlišení</td><td class="c-hodnota" id="cbC_screen">---</td><td class="c-popis">screen.width × screen.height</td></tr>
          <tr><td class="c-nazev">Okno</td><td class="c-hodnota" id="cbC_window">---</td><td class="c-popis">window.innerWidth × window.innerHeight</td></tr>
          <tr><td class="c-nazev">DPR</td><td class="c-hodnota" id="cbC_dpr">---</td><td class="c-popis">devicePixelRatio (hustota pixelů).</td></tr>
          <tr><td class="c-nazev">Platform</td><td class="c-hodnota" id="cbC_platform">---</td><td class="c-popis">navigator.platform / userAgentData.</td></tr>
          <tr><td class="c-nazev">Online</td><td class="c-hodnota" id="cbC_online">---</td><td class="c-popis">navigator.onLine</td></tr>
          <tr><td class="c-nazev">Cookies</td><td class="c-hodnota" id="cbC_cookie">---</td><td class="c-popis">navigator.cookieEnabled</td></tr>
          <tr><td class="c-nazev">Timezone</td><td class="c-hodnota" id="cbC_tz">---</td><td class="c-popis">Intl.DateTimeFormat().resolvedOptions().timeZone</td></tr>
        </tbody>
      </table>
    </div>

  </div>

  <script>
  (function () {
    function setText(id, value) {
      var el = document.getElementById(id);
      if (!el) return false;
      el.textContent = value;
      return true;
    }

    function shortUa(ua) {
      ua = String(ua || '');
      if (ua.length <= 70) return ua;
      return ua.slice(0, 67) + '…';
    }

    function fillClient() {
      var ok = true;

      ok = setText('cbC_ua', shortUa(navigator.userAgent)) && ok;
      ok = setText('cbC_lang', String(navigator.language || '---')) && ok;
      ok = setText('cbC_screen', String(screen.width) + ' × ' + String(screen.height)) && ok;
      ok = setText('cbC_window', String(window.innerWidth) + ' × ' + String(window.innerHeight)) && ok;
      ok = setText('cbC_dpr', String(window.devicePixelRatio || '---')) && ok;
      ok = setText('cbC_online', (navigator.onLine ? 'ano' : 'ne')) && ok;
      ok = setText('cbC_cookie', (navigator.cookieEnabled ? 'ano' : 'ne')) && ok;

      var tz = '---';
      try {
        tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '---';
      } catch (e) {}
      ok = setText('cbC_tz', tz) && ok;

      var plat = '---';
      try {
        if (navigator.userAgentData && navigator.userAgentData.platform) plat = navigator.userAgentData.platform;
        else if (navigator.platform) plat = navigator.platform;
      } catch (e) {}
      ok = setText('cbC_platform', String(plat || '---')) && ok;

      if (!ok) {
        var t = document.getElementById('cbClientTable');
        if (t) {
          var tr = document.createElement('tr');
          tr.innerHTML = '<td class="c-nazev">JS</td><td class="c-hodnota">chyba</td><td class="c-popis">Některé ID v tabulce nebylo nalezeno (špatná kotva v HTML).</td>';
          t.querySelector('tbody').appendChild(tr);
        }
      }
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fillClient);
    } else {
      fillClient();
    }

    window.addEventListener('resize', function () {
      setText('cbC_window', String(window.innerWidth) + ' × ' + String(window.innerHeight));
    });

  })();
  </script>

</section>
<?php
// pages/admin_infoblok.php * Verze: V2 * Aktualizace: 7.2.2026 * Počet řádků: 236
// konec souboru