<?php
// includes/hlavicka.php * Verze: V45 * Aktualizace: 27.04.2026
declare(strict_types=1);
require_once __DIR__ . '/../db/db_user_role.php';
require_once __DIR__ . '/../lib/pobocky_vyber.php';

// Priznak prihlaseni urcuje, zda se vykresli plna hlavicka, nebo guest varianta.
$cbLoginOk = !empty($_SESSION['login_ok']);

// Zakladni data uzivatele pro user blok vpravo.
$cbUser = $_SESSION['cb_user'] ?? [];
$cbUserName = 'Uzivatel';
$cbUserRole = '-';
$cbUserRoleLabel = '-';
$cbUserRoleId = 0;

if (is_array($cbUser)) {
    $fullName = trim((string)($cbUser['name'] ?? '') . ' ' . (string)($cbUser['surname'] ?? ''));
    if ($fullName !== '') {
        $cbUserName = $fullName;
    } else {
        $cbUserName = (string)($cbUser['jmeno'] ?? $cbUser['email'] ?? $cbUser['login'] ?? $cbUserName);
    }

    $cbUserRole = (string)($cbUser['role'] ?? $cbUser['nazev_role'] ?? $cbUserRole);
    $cbUserRoleLabel = $cbUserRole;
    $cbUserRoleId = (int)($cbUser['id_role'] ?? 0);
}

if ($cbUserRole !== '-' && $cbUserRoleId > 0) {
    $cbUserRole .= ' (' . $cbUserRoleId . ')';
}

// Stavove semafory (zatim staticky, pozdeji se napoji na realna data).
$sysDb = 'ok';
$sysSmeny = 'ok';
if (!function_exists('cb_head_restia_token_is_valid')) {
    function cb_head_restia_token_is_valid(mysqli $conn): bool
    {
        $stmtRestia = $conn->prepare('
            SELECT expires_at
            FROM restia_token
            WHERE id_restia_token = 1
            LIMIT 1
        ');
        if (!$stmtRestia) {
            return false;
        }

        $stmtRestia->execute();
        $stmtRestia->bind_result($restiaExpiresAt);
        $isValid = false;
        if ($stmtRestia->fetch()) {
            $restiaExpiresAt = trim((string)($restiaExpiresAt ?? ''));
            if ($restiaExpiresAt !== '') {
                try {
                    $restiaExp = new DateTimeImmutable($restiaExpiresAt, new DateTimeZone('UTC'));
                    $restiaNow = new DateTimeImmutable('now', new DateTimeZone('UTC'));
                    $isValid = ($restiaExp > $restiaNow->modify('+60 seconds'));
                } catch (Throwable $e) {
                    $isValid = false;
                }
            }
        }
        $stmtRestia->close();
        return $isValid;
    }
}

if (!function_exists('cb_head_restia_online_is_running')) {
    function cb_head_restia_online_is_running(mysqli $conn): bool
    {
        $q = $conn->query('SELECT id_akce FROM online_restia WHERE aktivni = 1 LIMIT 1');
        if (!($q instanceof mysqli_result)) {
            return false;
        }

        $isRunning = ($q->num_rows > 0);
        $q->free();

        return $isRunning;
    }
}

$sysRestia = 'bad';
try {
    $connRestia = db();
    if (cb_head_restia_online_is_running($connRestia)) {
        $sysRestia = 'bad';
    } elseif (cb_head_restia_token_is_valid($connRestia)) {
        $sysRestia = 'ok';
    } else {
        require_once __DIR__ . '/../lib/restia_ziskej_access.php';
        if (cb_head_restia_online_is_running($connRestia)) {
            $sysRestia = 'bad';
        } else {
            $sysRestia = cb_head_restia_token_is_valid($connRestia) ? 'ok' : 'bad';
        }
    }
} catch (Throwable $e) {
    $sysRestia = 'bad';
}

// Vychozi obdobi: vcerejsi kompletni pracovni den 06:00-06:00.
$cbNowPeriod = new DateTimeImmutable('now');
$cbCurrentWorkdayDate = $cbNowPeriod;
if ((int)$cbNowPeriod->format('G') < 6) {
    $cbCurrentWorkdayDate = $cbCurrentWorkdayDate->modify('-1 day');
}
$cbWorkingYesterdayDate = $cbCurrentWorkdayDate->modify('-1 day');
$cbWorkingYesterday = $cbWorkingYesterdayDate->setTime(6, 0, 0)->format('Y-m-d H:i:s');
$cbWorkingEnd = $cbCurrentWorkdayDate->setTime(6, 0, 0)->format('Y-m-d H:i:s');
$cbObdobiMax = $cbNowPeriod->format('Y-m-d H:i:s');
$cbObdobiMaxRes = db()->query('SELECT MAX(konec) AS posledni_konec FROM online_restia WHERE konec IS NOT NULL');
if ($cbObdobiMaxRes instanceof mysqli_result) {
    $cbObdobiMaxRow = $cbObdobiMaxRes->fetch_assoc();
    $cbObdobiMaxRes->free();
    $cbPosledniKonec = trim((string)($cbObdobiMaxRow['posledni_konec'] ?? ''));
    if ($cbPosledniKonec !== '') {
        $cbObdobiMax = $cbPosledniKonec;
    }
}
$today = substr($cbWorkingYesterday, 0, 10);
$tomorrow = substr($cbWorkingEnd, 0, 10);

// Normalizace obdobi: prijima stare datum YYYY-MM-DD i nove datum+cas.
$normalizePeriodDateTime = static function (string $v): string {
    $v = trim(str_replace('T', ' ', $v));
    if ($v === '') {
        return '';
    }
    if (preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $v, $m) === 1) {
        $v = $m[1] . '-' . $m[2] . '-' . $m[3] . ' 06:00:00';
    } elseif (preg_match('~^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$~', $v, $m) === 1) {
        $v .= ':00';
    }
    if (preg_match('~^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$~', $v, $m) !== 1) {
        return '';
    }
    $y = (int)$m[1];
    $mo = (int)$m[2];
    $d = (int)$m[3];
    $h = (int)$m[4];
    $mi = (int)$m[5];
    $s = (int)$m[6];
    if (!checkdate($mo, $d, $y) || $h > 23 || $mi > 59 || $s > 59) {
        return '';
    }
    return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $h, $mi, $s);
};

// Globalni filtr obdobi (bude platit pro karty dashboardu).
$cbObdobiOd = $cbWorkingYesterday;
$cbObdobiDo = $cbWorkingEnd;
$cbObdobiMode = trim((string)($_SESSION['cb_obdobi_mode'] ?? 'manual'));
$cbProdlevaMs = (int)cb_system_setting('pauza_obdobi', 1000);
if (!in_array($cbProdlevaMs, range(1000, 10000, 1000), true)) {
    $cbProdlevaMs = 1000;
}

if ($cbObdobiMode === 'dnes') {
    $cbObdobiMode = 'vcera';
}
if (!in_array($cbObdobiMode, ['vcera', 'tyden', 'mesic', 'rok', 'manual'], true)) {
    $cbObdobiMode = 'manual';
}
$sessionOd = $normalizePeriodDateTime((string)($_SESSION['cb_obdobi_od'] ?? ''));
$sessionDo = $normalizePeriodDateTime((string)($_SESSION['cb_obdobi_do'] ?? ''));
if ($sessionOd !== '' && $sessionDo !== '' && $sessionOd <= $cbObdobiMax && $sessionOd <= $sessionDo && $sessionDo <= $cbObdobiMax) {
    $cbObdobiOd = $sessionOd;
    $cbObdobiDo = $sessionDo;
    $sessionMode = trim((string)($_SESSION['cb_obdobi_mode'] ?? 'manual'));
    if ($sessionMode === 'dnes') {
        $sessionMode = 'vcera';
    }
    if (in_array($sessionMode, ['vcera', 'tyden', 'mesic', 'rok', 'manual'], true)) {
        $cbObdobiMode = $sessionMode;
    }
}

$userProdleva = (int)cb_user_setting('prodleva', $cbProdlevaMs);
if (in_array($userProdleva, range(1000, 10000, 1000), true)) {
    $cbProdlevaMs = $userProdleva;
}

if (in_array($cbObdobiMode, ['tyden', 'mesic', 'rok'], true)) {
    $cbObdobiDo = $cbObdobiMax;
}

$_SESSION['cb_obdobi_od'] = $cbObdobiOd;
$_SESSION['cb_obdobi_do'] = $cbObdobiDo;
$_SESSION['cb_obdobi_mode'] = $cbObdobiMode;

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

$cbTimeoutMin = (int)($_SESSION['cb_timeout_min'] ?? 720);
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
$cbSelectedPobocky = get_selected_pobocky();
$cbSelectedMode = trim((string)($_SESSION['selected_pobocky_mode'] ?? ''));
$cbPobockaMultiFromCard = in_array($cbSelectedMode, ['area', 'custom'], true);
$cbPobockaId = 0;
if (!$cbPobockaMultiFromCard && !empty($cbSelectedPobocky)) {
    $cbPobockaId = (int)$cbSelectedPobocky[0];
}

$cbHelpdeskIsRoleOne = ((int)$cbUserRoleId === 1);
$cbHelpdeskApiUrl = cb_root_url('index.php');

if ($cbLoginOk) {
    try {
        $conn = db();
        $idUser = (int)($cbUser['id_user'] ?? 0);
        if ($idUser > 0) {
            $sql = '
                SELECT p.id_pob, p.nazev, p.oblast
                FROM user_pobocka up
                INNER JOIN pobocka p ON p.id_pob = up.id_pob
                WHERE up.id_user = ?
                ORDER BY p.nazev ASC
            ';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $idUser);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res instanceof mysqli_result) {
                    while ($r = $res->fetch_assoc()) {
                        $id = (int)($r['id_pob'] ?? 0);
                        $nazev = trim((string)($r['nazev'] ?? ''));
                        $oblast = trim((string)($r['oblast'] ?? ''));
                        if ($oblast === '') {
                            $oblast = 'Nezarazeno';
                        }
                        if ($id > 0 && $nazev !== '') {
                            $cbPobocky[] = ['id_pob' => $id, 'nazev' => $nazev, 'oblast' => $oblast];
                        }
                    }
                    $res->close();
                }
                $stmt->close();
            }
        }
    } catch (Throwable $e) {
        $cbPobocky = [];
    }
}

if ($cbPobocky) {
    if (!$cbPobockaMultiFromCard) {
        $exists = false;
        foreach ($cbPobocky as $p) {
            if ((int)$p['id_pob'] === $cbPobockaId) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $cbPobockaId = (int)$cbPobocky[0]['id_pob'];
            cb_pobocky_set_selected([$cbPobockaId]);
        }
    }
}

$cbHeadAktualizaceDat = '---';
try {
    $cbHeadAktualizaceDat = (new DateTimeImmutable((string)$cbObdobiMax))->format('j.n.Y H:i');
} catch (Throwable $e) {
    $cbHeadAktualizaceDat = '---';
}
$cbCurrentModule = function_exists('cb_current_module') ? cb_current_module() : 'provoz';
if (!in_array($cbCurrentModule, ['provoz', 'hr', 'smeny', 'ukoly', 'helpdesk'], true)) {
    $cbCurrentModule = 'provoz';
}
$cbHeaderPostUrl = cb_root_url('index.php');
$cbProvozPostUrl = cb_root_url('index.php');
?>
<header class="blok_hlavicka sirka100">

    <?php require __DIR__ . '/hlavicka/head_logo.php'; ?>
    <strong class="head_title">Comeback</strong>
    <span class="head_subtitle">informační systém</span>

    <?php if ($cbLoginOk): ?>
      <nav class="head_module_nav" aria-label="Moduly">
        <a class="head_module_link head_module_link--provoz<?= $cbCurrentModule === 'provoz' ? ' is-active' : '' ?>" href="<?= h(cb_root_url('')) ?>" data-cb-module-link="1" data-cb-module="provoz">Provoz</a>
        <a class="head_module_link head_module_link--hr<?= $cbCurrentModule === 'hr' ? ' is-active' : '' ?>" href="<?= h(cb_root_url('')) ?>" data-cb-module-link="1" data-cb-module="hr">HR</a>
        <a class="head_module_link head_module_link--smeny<?= $cbCurrentModule === 'smeny' ? ' is-active' : '' ?>" href="<?= h(cb_root_url('')) ?>" data-cb-module-link="1" data-cb-module="smeny">Směny</a>
      </nav>

      <button type="button" class="head_task_btn head_task_btn--todo<?= $cbCurrentModule === 'ukoly' ? ' is-active' : '' ?>" data-cb-module-link="1" data-cb-module="ukoly">
        <span>Úkoly</span>
        <strong class="head_task_count">0</strong>
      </button>
      <?php if ($cbHelpdeskIsRoleOne): ?>
        <button type="button" class="head_task_btn head_task_btn--helpdesk" data-cb-module-link="1" data-cb-module="helpdesk">
          <span>HelpDesk</span>
          <strong class="head_task_count" data-cb-helpdesk-header-count="all">0</strong>
        </button>
      <?php else: ?>
        <button type="button" class="head_task_btn head_task_btn--helpdesk" data-cb-module-link="1" data-cb-module="helpdesk">
          <span>HelpDesk</span>
          <strong class="head_task_count" data-cb-helpdesk-header-count="all">0</strong>
        </button>
      <?php endif; ?>

      <?php require __DIR__ . '/hlavicka/head_pobocka.php'; ?>
      <?php require __DIR__ . '/hlavicka/head_obdobi.php'; ?>

      <div class="head_update" aria-label="Aktualizace dat">
        <span class="head_update_icon" aria-hidden="true">⟳</span>
        <span>
          <span class="head_block_label">Aktualizace dat</span>
          <strong class="head_update_value"><?= h($cbHeadAktualizaceDat) ?></strong>
        </span>
      </div>

    <?php else: ?>
      <div class="head_guest ram_hlavicka bg_bila zaobleni_12"></div>
    <?php endif; ?>

</header>
<?php if ($cbLoginOk): ?>
  <script>
  (function () {
    var apiUrl = <?= json_encode($cbHelpdeskApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function countBox(key) {
      return document.querySelector('[data-cb-helpdesk-header-count="' + key + '"]');
    }

    function numberValue(value) {
      var n = Number(value || 0);
      if (!Number.isFinite(n) || n < 0) {
        return 0;
      }
      return Math.trunc(n);
    }

    function setCounts(counts) {
      var source = counts || {};
      ['all', 'new', 'active', 'resolved'].forEach(function (key) {
        var box = countBox(key);
        if (box instanceof HTMLElement) {
          box.textContent = String(numberValue(source[key]));
        }
      });
    }

    function refresh() {
      var moduleName = window.CB_HELPDESK_SOURCE_MODULE || window.CB_ACTIVE_MAIN_MODULE || 'provoz';
      return fetch(apiUrl + '?helpdesk_action=stav_tiketu&cb_helpdesk_module=' + encodeURIComponent(String(moduleName)), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'X-Comeback-Helpdesk': '1'
        }
      })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (data) {
          if (data && data.ok === true && data.counts) {
            setCounts(data.counts);
          }
        })
        .catch(function () {
        });
    }

    function openHelpdesk(filter) {
      var filterValue = String(filter || 'all');
      if (window.CB_HELPDESK && typeof window.CB_HELPDESK.openUnreadFilter === 'function') {
        window.CB_HELPDESK.openUnreadFilter(filterValue);
        return;
      }

      window.CB_HELPDESK_PENDING_FILTER = filterValue;

      if (typeof window.CB_LOAD_MODULE === 'function') {
        window.CB_LOAD_MODULE('helpdesk', true);
      }
    }

    function placeTooltip(root, event) {
      var panel = root.querySelector('[data-cb-helpdesk-head-tooltip="1"]');
      if (!(panel instanceof HTMLElement)) { return; }
      panel.classList.add('is-visible');

      var rect = root.getBoundingClientRect();
      var clientX = event && typeof event.clientX === 'number' ? event.clientX : rect.right;
      var clientY = event && typeof event.clientY === 'number' ? event.clientY : rect.top;
      var panelRect = panel.getBoundingClientRect();
      var gap = 8;
      var left = clientX + 14;
      var top = clientY - panelRect.height - gap;
      var viewWidth = window.innerWidth || document.documentElement.clientWidth || 0;

      if (left + panelRect.width + gap > viewWidth) {
        left = Math.max(gap, viewWidth - panelRect.width - gap);
      }
      if (top < gap) {
        top = clientY + gap;
      }

      panel.style.left = String(left) + 'px';
      panel.style.top = String(top) + 'px';
    }

    function hideTooltip(root) {
      var panel = root.querySelector('[data-cb-helpdesk-head-tooltip="1"]');
      if (!(panel instanceof HTMLElement)) { return; }
      panel.classList.remove('is-visible');
      panel.style.left = '';
      panel.style.top = '';
    }

    window.CB_HELPDESK_HEADER = {
      refresh: refresh,
      open: openHelpdesk
    };

    document.addEventListener('click', function (e) {
      var target = e.target;
      if (!(target instanceof Element)) { return; }
      var item = target.closest('[data-cb-helpdesk-header-filter]');
      if (!(item instanceof HTMLElement)) { return; }
      openHelpdesk(item.getAttribute('data-cb-helpdesk-header-filter') || 'all');
    });

    document.addEventListener('mouseenter', function (e) {
      var target = e.target;
      var item = target instanceof Element ? target.closest('[data-cb-helpdesk-header-filter]') : null;
      if (item instanceof HTMLElement) {
        placeTooltip(item, e);
      }
    }, true);

    document.addEventListener('mousemove', function (e) {
      var target = e.target;
      var item = target instanceof Element ? target.closest('[data-cb-helpdesk-header-filter]') : null;
      if (item instanceof HTMLElement) {
        placeTooltip(item, e);
      }
    }, true);

    document.addEventListener('mouseleave', function (e) {
      var target = e.target;
      var item = target instanceof Element ? target.closest('[data-cb-helpdesk-header-filter]') : null;
      var related = e.relatedTarget;
      if (item instanceof HTMLElement && !(related instanceof Node && item.contains(related))) {
        hideTooltip(item);
      }
    }, true);

    document.addEventListener('focusin', function (e) {
      var target = e.target;
      var item = target instanceof Element ? target.closest('[data-cb-helpdesk-header-filter]') : null;
      if (item instanceof HTMLElement) {
        placeTooltip(item, null);
      }
    });

    document.addEventListener('focusout', function (e) {
      var target = e.target;
      var item = target instanceof Element ? target.closest('[data-cb-helpdesk-header-filter]') : null;
      var related = e.relatedTarget;
      if (item instanceof HTMLElement && !(related instanceof Node && item.contains(related))) {
        hideTooltip(item);
      }
    });

    refresh();
  })();
  </script>
<?php endif; ?>
