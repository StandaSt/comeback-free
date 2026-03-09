<?php
// includes/hlavicka.php * Verze: V44 * Aktualizace: 07.03.2026
declare(strict_types=1);
require_once __DIR__ . '/../db/db_user_role.php';

// Priznak prihlaseni urcuje, zda se vykresli plna hlavicka, nebo guest varianta.
$cbLoginOk = !empty($_SESSION['login_ok']);

// Zakladni data uzivatele pro user blok vpravo.
$cbUser = $_SESSION['cb_user'] ?? [];
$cbUserName = 'Uzivatel';
$cbUserRole = '-';
$cbUserRoleId = 0;

if (is_array($cbUser)) {
    $fullName = trim((string)($cbUser['name'] ?? '') . ' ' . (string)($cbUser['surname'] ?? ''));
    if ($fullName !== '') {
        $cbUserName = $fullName;
    } else {
        $cbUserName = (string)($cbUser['jmeno'] ?? $cbUser['email'] ?? $cbUser['login'] ?? $cbUserName);
    }

    $cbUserRole = (string)($cbUser['role'] ?? $cbUser['nazev_role'] ?? $cbUserRole);
    $cbUserRoleId = (int)($cbUser['id_role'] ?? 0);
}

if ($cbUserRole !== '-' && $cbUserRoleId > 0) {
    $cbUserRole .= ' (' . $cbUserRoleId . ')';
}

// Stavove semafory (zatim staticky, pozdeji se napoji na realna data).
$sysDb = 'ok';
$sysSmeny = 'ok';
$sysRestia = 'var';

// Vychozi obdobi: od vcera do dneska.
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');

// Globalni filtr obdobi (bude platit pro KPI i karty dashboardu).
$cbObdobiOd = (string)($_SESSION['cb_obdobi_od'] ?? $yesterday);
$cbObdobiDo = (string)($_SESSION['cb_obdobi_do'] ?? $today);
$cbObdobiTyp = (string)($_SESSION['cb_obdobi_typ'] ?? 'vcera');

// Jednoducha validace formatu datumu YYYY-MM-DD.
$isDate = static function (string $v): bool {
    if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $v)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $v));
    return checkdate($m, $d, $y);
};

if (!$isDate($cbObdobiOd)) {
    $cbObdobiOd = $yesterday;
}
if (!$isDate($cbObdobiDo)) {
    $cbObdobiDo = $today;
}
if ($cbObdobiOd > $cbObdobiDo) {
    [$cbObdobiOd, $cbObdobiDo] = [$cbObdobiDo, $cbObdobiOd];
}

// Data do user bloku.
$cbLoginInfo = (is_array($_SESSION['cb_login_info'] ?? null)) ? $_SESSION['cb_login_info'] : [];
$cbCurrent = (is_array($cbLoginInfo['current'] ?? null)) ? $cbLoginInfo['current'] : [];
$cbPrev = (is_array($cbLoginInfo['prev'] ?? null)) ? $cbLoginInfo['prev'] : [];
$cbStats = (is_array($cbLoginInfo['stats'] ?? null)) ? $cbLoginInfo['stats'] : [];

$cbLastLoginRaw = (string)($cbPrev['kdy'] ?? $cbCurrent['kdy'] ?? '');
$cbLastLoginText = '---';
if ($cbLastLoginRaw !== '') {
    try {
        $cbLastLoginText = (new DateTimeImmutable($cbLastLoginRaw))->format('j.n.Y H:i');
    } catch (Throwable $e) {
        $cbLastLoginText = $cbLastLoginRaw;
    }
}

$cbLoginTotal = (int)($cbStats['total'] ?? 0);
$cbLoginToday = (int)($cbStats['today'] ?? 0);
$cbLoginStatsText = 'celkem ' . $cbLoginTotal . 'x / dnes ' . $cbLoginToday . 'x';

$cbTimeoutMin = (int)($_SESSION['cb_timeout_min'] ?? 20);
if ($cbTimeoutMin <= 0) {
    $cbTimeoutMin = 20;
}
$cbStartTs = (int)($_SESSION['cb_session_start_ts'] ?? time());
$cbLastTs = (int)($_SESSION['cb_last_activity_ts'] ?? time());
$cbNowTs = time();
if ($cbStartTs <= 0 || $cbStartTs > $cbNowTs) {
    $cbStartTs = $cbNowTs;
}
if ($cbLastTs <= 0 || $cbLastTs > $cbNowTs || $cbLastTs < $cbStartTs) {
    $cbLastTs = $cbNowTs;
}

$cbRunMin = max(0, (int)floor(($cbNowTs - $cbStartTs) / 60));
$cbIdleMin = max(0, (int)floor(($cbNowTs - $cbLastTs) / 60));
$cbRemainMin = max(0, $cbTimeoutMin - $cbIdleMin);
$cbSessionText = $cbRunMin . ' min';
$cbRemainText = $cbRemainMin . ' min';
$cbSessionComboText = $cbSessionText . '/' . $cbRemainText;
$cbThermoPct = (int)round(min(100, max(0, ($cbTimeoutMin > 0 ? (($cbIdleMin / $cbTimeoutMin) * 100) : 0))));

// Seznam pobocek pro vyber v hlavicce.
$cbPobocky = [];
$cbPobockaId = (int)($_SESSION['cb_pobocka_id'] ?? 0);

if ($cbLoginOk) {
    try {
        $conn = db();
        $sql = 'SELECT id_pob, nazev FROM pobocka ORDER BY nazev ASC';
        $res = $conn->query($sql);
        if ($res instanceof mysqli_result) {
            while ($r = $res->fetch_assoc()) {
                $id = (int)($r['id_pob'] ?? 0);
                $nazev = trim((string)($r['nazev'] ?? ''));
                if ($id > 0 && $nazev !== '') {
                    $cbPobocky[] = ['id_pob' => $id, 'nazev' => $nazev];
                }
            }
            $res->close();
        }
    } catch (Throwable $e) {
        $cbPobocky = [];
    }
}

if ($cbPobocky) {
    $exists = false;
    foreach ($cbPobocky as $p) {
        if ((int)$p['id_pob'] === $cbPobockaId) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $cbPobockaId = (int)$cbPobocky[0]['id_pob'];
        $_SESSION['cb_pobocka_id'] = $cbPobockaId;
    }
}
?>
<header class="head_box">
  <div class="head_grid">

    <?php require __DIR__ . '/hlavicka/head_logo.php'; ?>

    <?php if ($cbLoginOk): ?>
      <div class="head_top" aria-label="Horni radek hlavicky">
        <?php require __DIR__ . '/hlavicka/head_obdobi.php'; ?>
        <?php require __DIR__ . '/hlavicka/head_kpi.php'; ?>
      </div>

      <nav class="head_bottom" aria-label="Dolni radek hlavicky">
        <?php require __DIR__ . '/hlavicka/head_menu.php'; ?>
        <?php require __DIR__ . '/hlavicka/head_pobocka.php'; ?>
        <?php require __DIR__ . '/hlavicka/head_stav.php'; ?>
      </nav>

      <?php require __DIR__ . '/hlavicka/head_user.php'; ?>
    <?php else: ?>
      <div class="head_guest"></div>
    <?php endif; ?>

  </div>
</header>
