<?php
// mobil/mobil_helpdesk.php * Verze: V1 * Aktualizace: 22.06.2026
declare(strict_types=1);

require_once __DIR__ . '/../lib/session_boot.php';
require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/system.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/../lib/helpdesk_notifikace.php';

function cb_mobil_helpdesk_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cb_mobil_helpdesk_fetch(string $token): ?array
{
    $data = cb_helpdesk_push_token_parse($token);
    if (!is_array($data)) {
        return null;
    }

    $idUser = (int)($data['id_user'] ?? 0);
    $idNotifikace = (int)($data['id_notifikace'] ?? 0);
    if ($idUser <= 0 || $idNotifikace <= 0) {
        return null;
    }

    $stmt = db()->prepare('
        SELECT
            n.id_helpdesk_notifikace,
            n.id_helpdesk,
            n.id_helpdesk_zprava,
            n.id_user,
            n.typ,
            n.text,
            n.vytvoreno,
            n.precteno,
            h.predmet,
            h.popis,
            h.verejny,
            h.typ AS typ_ticket,
            u.jmeno,
            u.prijmeni
        FROM helpdesk_notifikace n
        INNER JOIN helpdesk h ON h.id_helpdesk = n.id_helpdesk
        LEFT JOIN `user` u ON u.id_user = h.id_user_zalozil
        WHERE n.id_helpdesk_notifikace = ? AND n.id_user = ?
        LIMIT 1
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        return null;
    }

    $stmt->bind_param('ii', $idNotifikace, $idUser);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res instanceof mysqli_result ? $res->fetch_assoc() : null;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    $stmt->close();

    return is_array($row) ? $row : null;
}

function cb_mobil_helpdesk_mark_read(int $idNotifikace, int $idUser): void
{
    if ($idNotifikace <= 0 || $idUser <= 0) {
        return;
    }

    $stmt = db()->prepare('
        UPDATE helpdesk_notifikace
        SET precteno = COALESCE(precteno, NOW())
        WHERE id_helpdesk_notifikace = ? AND id_user = ?
        LIMIT 1
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        return;
    }

    $stmt->bind_param('ii', $idNotifikace, $idUser);
    $stmt->execute();
    $stmt->close();
}

function cb_mobil_helpdesk_format_datetime(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '---';
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, new DateTimeZone('Europe/Prague'));
    if (!($dt instanceof DateTimeImmutable)) {
        return $raw;
    }

    return $dt->format('j. n. Y \v H:i') . ' hod.';
}

function cb_mobil_helpdesk_type_label(string $value): string
{
    return match (trim($value)) {
        'chyba' => 'Chyba systému',
        'dotaz' => 'Dotaz',
        'navrh' => 'Námět na vylepšení',
        default => trim($value) !== '' ? trim($value) : 'HelpDesk',
    };
}

function cb_mobil_helpdesk_visibility_label(mixed $value): string
{
    return match ((int)$value) {
        0 => 'Pouze pro admina',
        2 => 'Všichni mohou číst',
        default => 'Všichni mohou reagovat',
    };
}

function cb_mobil_helpdesk_full_name(?string $jmeno, ?string $prijmeni): string
{
    $fullName = trim(trim((string)$jmeno) . ' ' . trim((string)$prijmeni));
    return $fullName !== '' ? $fullName : '---';
}

$token = trim((string)($_GET['t'] ?? ''));
$row = cb_mobil_helpdesk_fetch($token);

if (is_array($row)) {
    cb_mobil_helpdesk_mark_read((int)($row['id_helpdesk_notifikace'] ?? 0), (int)($row['id_user'] ?? 0));
}

$ticketId = is_array($row) ? (int)($row['id_helpdesk'] ?? 0) : 0;
$title = 'HelpDesk';
$subtitle = $ticketId > 0 ? ('Tiket č. ' . (string)$ticketId) : 'Tiket';
$message = is_array($row) ? trim((string)($row['text'] ?? '')) : 'Notifikace nebyla nalezena nebo už není dostupná.';
$ticketSubject = is_array($row) ? trim((string)($row['predmet'] ?? '')) : '';
$ticketDescription = is_array($row) ? trim((string)($row['popis'] ?? '')) : '';
$ticketType = is_array($row) ? cb_mobil_helpdesk_type_label((string)($row['typ_ticket'] ?? '')) : '';
$ticketVisibility = is_array($row) ? cb_mobil_helpdesk_visibility_label($row['verejny'] ?? null) : '---';
$createdAt = is_array($row) ? cb_mobil_helpdesk_format_datetime((string)($row['vytvoreno'] ?? '')) : '---';
$authorName = is_array($row)
    ? cb_mobil_helpdesk_full_name((string)($row['jmeno'] ?? ''), (string)($row['prijmeni'] ?? ''))
    : '---';

?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= cb_mobil_helpdesk_h($title) ?></title>
  <link rel="stylesheet" href="<?= cb_mobil_helpdesk_h(cb_url('style/1/modal_alert.css')) ?>">
  <style>
    .modal{
      width:min(340px, 100%);
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
    .hd-ticket{
      margin-top:10px;
      padding:12px;
      border-radius:14px;
      background:linear-gradient(180deg, rgba(220,236,255,.82) 0%, rgba(248,251,255,.98) 100%);
      border:1px solid rgba(15,63,145,.12);
      color:#0f172a;
    }
    .hd-ticket-id{
      font-size:12px;
      color:#64748b;
    }
    .hd-block{
      margin-top:12px;
      padding:14px;
      border-radius:16px;
      background:rgba(255,255,255,.96);
      border:1px solid rgba(15,23,42,.10);
      box-shadow:0 8px 24px rgba(15,23,42,.06);
    }
    .hd-row{
      font-size:14px;
      line-height:1.55;
      color:#0f172a;
    }
    .hd-row + .hd-row{
      margin-top:6px;
    }
    .hd-label{
      font-weight:700;
      color:#0f3f91;
    }
    .hd-subject{
      margin-top:6px;
      font-size:16px;
      font-weight:700;
      line-height:1.4;
      color:#0f172a;
      word-break:break-word;
    }
    .hd-description{
      margin-top:14px;
      font-size:15px;
      line-height:1.55;
      color:#475569;
      white-space:pre-wrap;
      word-break:break-word;
    }
    .hd-note{
      margin-top:14px;
      font-size:12px;
      line-height:1.45;
      color:#64748b;
      text-align:center;
    }
  </style>
</head>
<body class="modal-page">

  <div class="modal" role="dialog" aria-modal="true" aria-label="HelpDesk notifikace">
    <button type="button" class="modal-x" id="btnClose" aria-label="Zavřít">×</button>

    <div class="modal-head">
      <div class="modal-logo">
        <img src="<?= cb_mobil_helpdesk_h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title"><?= cb_mobil_helpdesk_h($title) ?></p>
        <p class="modal-sub"><?= cb_mobil_helpdesk_h($subtitle) ?></p>
      </div>
    </div>

    <?php if (is_array($row)): ?>
      <div class="hd-ticket">
        <div class="hd-ticket-id">Tiket č. <?= cb_mobil_helpdesk_h((string)$ticketId) ?></div>
      </div>

      <div class="hd-block">
        <div class="hd-row"><span class="hd-label">Typ:</span> <?= cb_mobil_helpdesk_h($ticketType) ?></div>
        <div class="hd-row"><span class="hd-label">Určení:</span> <?= cb_mobil_helpdesk_h($ticketVisibility) ?></div>
        <div class="hd-row"><span class="hd-label">Vytvořeno:</span> <?= cb_mobil_helpdesk_h($createdAt) ?></div>
        <div class="hd-row"><span class="hd-label">Zapsal:</span> <?= cb_mobil_helpdesk_h($authorName) ?></div>
      </div>

      <div class="hd-block">
        <div class="hd-row"><span class="hd-label">Předmět:</span></div>
        <div class="hd-subject"><?= cb_mobil_helpdesk_h($ticketSubject !== '' ? $ticketSubject : 'Bez předmětu') ?></div>
        <div class="hd-description"><span class="hd-label">Popis:</span><br><?= cb_mobil_helpdesk_h($ticketDescription !== '' ? $ticketDescription : '---') ?></div>
      </div>

      <div class="hd-note">Detail byl otevřen přes zabezpečený odkaz z mobilu.</div>
    <?php else: ?>
      <div class="hd-block">
        <div class="hd-description"><?= cb_mobil_helpdesk_h($message) ?></div>
      </div>
    <?php endif; ?>

    <div class="modal-spacer"></div>

    <button class="modal-btn" type="button" id="btnDone">Zavřít</button>
  </div>

<script>
(function(){
  function finish() {
    location.replace('about:blank');
  }

  var btnClose = document.getElementById('btnClose');
  if (btnClose) {
    btnClose.addEventListener('click', finish);
  }

  var btnDone = document.getElementById('btnDone');
  if (btnDone) {
    btnDone.addEventListener('click', finish);
  }
})();
</script>

</body>
</html>
<?php
// mobil/mobil_helpdesk.php * Verze: V1 * Aktualizace: 22.06.2026
// Konec souboru
