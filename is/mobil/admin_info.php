<?php
// mobil/admin_info.php * Verze: V1 * Aktualizace: 05.06.2026
declare(strict_types=1);

require_once __DIR__ . '/../lib/session_boot.php';

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/system.php';
require_once __DIR__ . '/../config/secrets.php';

function cb_admin_info_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cb_admin_info_fetch(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $stmt = db()->prepare('
        SELECT aiu.id_admin_info_user, ai.id_admin_info, ai.nadpis, ai.obsah, ai.vytvoreno, ai.id_odeslal,
               u.jmeno, u.prijmeni
        FROM admin_info_user aiu
        INNER JOIN admin_info ai ON ai.id_admin_info = aiu.id_admin_info
        LEFT JOIN `user` u ON u.id_user = ai.id_odeslal
        WHERE aiu.token = ?
        LIMIT 1
    ');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->bind_result($idAdminInfoUser, $idAdminInfo, $nadpis, $obsah, $vytvoreno, $idOdeslal, $jmeno, $prijmeni);
    $ok = $stmt->fetch();
    $stmt->close();

    if (!$ok) {
        return null;
    }

    return [
        'id_admin_info_user' => (int)$idAdminInfoUser,
        'id_admin_info' => (int)$idAdminInfo,
        'nadpis' => (string)$nadpis,
        'obsah' => (string)$obsah,
        'vytvoreno' => (string)$vytvoreno,
        'id_odeslal' => $idOdeslal === null ? null : (int)$idOdeslal,
        'odeslal' => trim((string)($jmeno ?? '') . ' ' . (string)($prijmeni ?? '')),
    ];
}

function cb_admin_info_seen(int $idAdminInfoUser): void
{
    if ($idAdminInfoUser <= 0) {
        return;
    }

    $stmt = db()->prepare('
        UPDATE admin_info_user
        SET zobrazeno = COALESCE(zobrazeno, NOW())
        WHERE id_admin_info_user = ?
        LIMIT 1
    ');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $idAdminInfoUser);
    $stmt->execute();
    $stmt->close();
}

function cb_admin_info_format_datetime(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return date('j. n. Y \v H:i') . ' hod.';
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, new DateTimeZone('Europe/Prague'));
    if (!($dt instanceof DateTimeImmutable)) {
        return $raw;
    }

    return $dt->format('j. n. Y \v H:i') . ' hod.';
}

function cb_admin_info_content_html(string $content): string
{
    $lines = preg_split('/\R/u', trim($content));
    if (!is_array($lines)) {
        return cb_admin_info_h($content);
    }

    $out = [];
    $firstLine = true;
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }

        if ($firstLine) {
            $out[] = '<div>' . cb_admin_info_h($line) . '</div>';
            $out[] = '<div class="admin-info-gap"></div>';
            $firstLine = false;
            continue;
        }

        if (preg_match('/^(zápisy|aktualizace|ignore|celkem)\s+(\d+)$/u', $line, $m) === 1) {
            $out[] = '<div class="admin-info-row"><span>' . cb_admin_info_h((string)$m[1]) . '</span><strong>' . cb_admin_info_h((string)$m[2]) . '</strong></div>';
            continue;
        }

        $out[] = '<div>' . cb_admin_info_h($line) . '</div>';
    }

    return implode("\n", $out);
}

$token = trim((string)($_GET['t'] ?? ''));
$row = cb_admin_info_fetch($token);

if (is_array($row)) {
    cb_admin_info_seen((int)$row['id_admin_info_user']);
}

$title = 'Admin info';
$sentAt = is_array($row) ? cb_admin_info_format_datetime((string)($row['vytvoreno'] ?? '')) : cb_admin_info_format_datetime('');
$content = is_array($row) ? (string)($row['obsah'] ?? '') : 'Zpráva nebyla nalezena nebo už není dostupná.';
$sender = 'systém';
if (is_array($row) && (int)($row['id_odeslal'] ?? 0) > 0) {
    $sender = trim((string)($row['odeslal'] ?? ''));
    if ($sender === '') {
        $sender = 'ID ' . (string)(int)$row['id_odeslal'];
    }
}

?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= cb_admin_info_h($title) ?></title>
  <link rel="stylesheet" href="<?= cb_admin_info_h(cb_public_url('style/1/modal_alert.css')) ?>">
  <style>
    .modal{
      width:min(315px, 100%);
    }
    .modal-logo{
      display:flex;
      align-items:center;
      justify-content:center;
    }
    .modal-logo img{
      width:100%;
      height:100%;
      object-fit:contain;
      object-position:center center;
      padding:7px;
      display:block;
    }
    .admin-info-box{
      margin-top:10px;
      padding:12px;
      border-radius:14px;
      background:rgba(15,23,42,.06);
      border:1px solid rgba(0,0,0,.10);
      color:#0f172a;
      font-size:15px;
      font-weight:700;
      line-height:1.45;
      word-break:break-word;
      font-family:"Segoe UI", "Trebuchet MS", Arial, sans-serif;
    }
    .admin-info-row{
      display:grid;
      grid-template-columns:1fr auto;
      gap:16px;
      align-items:baseline;
    }
    .admin-info-row strong{
      text-align:right;
      font-weight:800;
      min-width:42px;
    }
    .admin-info-gap{
      height:10px;
    }
    .admin-info-sender{
      margin-top:20px;
    }
  </style>
</head>
<body class="modal-page">

  <div class="modal" role="dialog" aria-modal="true" aria-label="Admin info">
    <button type="button" class="modal-x" id="btnClose" aria-label="Zavřít">×</button>

    <div class="modal-head">
      <div class="modal-logo">
        <img src="<?= cb_admin_info_h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title"><?= cb_admin_info_h($title) ?></p>
        <p class="modal-sub">Spuštěn CRON</p>
      </div>
    </div>

    <div class="admin-info-box">
      <div class="admin-info-body"><?= cb_admin_info_content_html($content) ?></div>
      <div class="admin-info-sender">Odeslal: <?= cb_admin_info_h($sender) ?></div>
    </div>
  </div>

<script>
(function(){
  var btnClose = document.getElementById('btnClose');
  if (btnClose) {
    btnClose.addEventListener('click', function(){ location.replace('about:blank'); });
  }
})();
</script>

</body>
</html>
<?php
// mobil/admin_info.php * Verze: V1 * Aktualizace: 05.06.2026
// Konec souboru
