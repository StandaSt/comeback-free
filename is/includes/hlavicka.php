<?php
// includes/hlavicka.php * Verze: V45 * Aktualizace: 27.04.2026
declare(strict_types=1);
require_once __DIR__ . '/../../www/db/db_user_role.php';

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
        require_once __DIR__ . '/../../www/lib/restia_ziskej_access.php';
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

// Globalni filtr obdobi (bude platit pro KPI i karty dashboardu).
$cbObdobiOd = $cbWorkingYesterday;
$cbObdobiDo = $cbWorkingEnd;
$cbObdobiMode = trim((string)($_SESSION['cb_obdobi_mode'] ?? 'manual'));
$cbProdlevaMs = (int)cb_system_setting('pauza_obdobi', 1000);
if (!in_array($cbProdlevaMs, [0, 1000, 1500, 2000, 2500, 3000, 3500, 4000, 4500, 5000], true)) {
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
if (in_array($userProdleva, [0, 1000, 1500, 2000, 2500, 3000, 3500, 4000, 4500, 5000], true)) {
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
$cbSelectedPobocky = get_selected_pobocky();
$cbSelectedMode = trim((string)($_SESSION['selected_pobocky_mode'] ?? ''));
$cbPobockaMultiFromCard = in_array($cbSelectedMode, ['area', 'custom'], true);
$cbPobockaId = 0;
if (!$cbPobockaMultiFromCard && !empty($cbSelectedPobocky)) {
    $cbPobockaId = (int)$cbSelectedPobocky[0];
}

$cbHelpdeskIsRoleOne = ((int)$cbUserRoleId === 1);
$cbHelpdeskApiUrl = cb_url('index_is.php');

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
?>
<header class="head_box bg_modra sirka100">
  <div class="head_grid gap_2 displ_grid sirka100">

    <?php require __DIR__ . '/hlavicka/head_logo.php'; ?>

    <?php if ($cbLoginOk): ?>
      <div class="head_controls gap_6 displ_flex flex_sloupec" aria-label="Globální nastavení">
        <?php require __DIR__ . '/hlavicka/head_obdobi.php'; ?>
        <nav class="head_controls_bottom gap_6 displ_grid" aria-label="Výběr poboček">
          <?php require __DIR__ . '/hlavicka/head_pobocka.php'; ?>
        </nav>
      </div>

      <?php require __DIR__ . '/hlavicka/head_kpi.php'; ?>
      <div class="head_gn_placeholder ram_hlavicka zaobleni_10" style="display:flex;flex-direction:column;align-items:stretch;justify-content:flex-start;gap:6px;text-align:center;padding:5px 6px;" aria-label="HelpDesk">
        <?php if ($cbHelpdeskIsRoleOne): ?>
          <button type="button" data-cb-helpdesk-card-open="1" style="display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:20px;padding:0 8px;border:1px solid #e7b7b7;border-radius:8px;background:#f9dede;color:#9f1d1d;font-size:11px;font-weight:700;line-height:18px;cursor:pointer;">HelpDesk</button>
        <?php else: ?>
          <button type="button" data-cb-helpdesk-open="1" style="display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:20px;padding:0 8px;border:1px solid #b8d0ef;border-radius:8px;background:#dcecff;color:#0f3f91;font-size:11px;font-weight:700;line-height:18px;cursor:pointer;">HelpDesk</button>
        <?php endif; ?>
        <div class="cb-head-helpdesk-meter" aria-label="Nepřečtené tikety">
          <button type="button" class="cb-head-helpdesk-meter-part is-all" data-cb-helpdesk-header-filter="all" aria-label="Nepřečtené tikety bez ohledu na stav" style="border:0;padding:2px 3px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;cursor:pointer;font:inherit;color:inherit;">
            <strong data-cb-helpdesk-header-count="all" style="font-size:13px;line-height:1;">0</strong>
            <span class="cb_tooltip_panel cb_tooltip_card" data-cb-helpdesk-head-tooltip="1" style="min-width:0;white-space:nowrap;pointer-events:none;font-size:var(--fs_12);line-height:1.2;">
              <span style="display:grid;gap:3px;">
                <span style="color:var(--clr_cervena);font-weight:700;">Nepřečtené tikety</span>
                <span style="color:var(--clr_cerna);font-weight:400;">bez ohledu na stav</span>
              </span>
            </span>
          </button>
          <button type="button" class="cb-head-helpdesk-meter-part is-new" data-cb-helpdesk-header-filter="new" aria-label="Nepřečtené tikety nové" style="border:0;padding:2px 3px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;cursor:pointer;font:inherit;color:inherit;">
            <strong data-cb-helpdesk-header-count="new" style="font-size:13px;line-height:1;">0</strong>
            <span class="cb_tooltip_panel cb_tooltip_card" data-cb-helpdesk-head-tooltip="1" style="min-width:0;white-space:nowrap;pointer-events:none;font-size:var(--fs_12);line-height:1.2;">
              <span style="display:grid;gap:3px;">
                <span style="color:var(--clr_cervena);font-weight:700;">Nepřečtené tikety</span>
                <span style="color:var(--clr_cerna);font-weight:400;">nové</span>
              </span>
            </span>
          </button>
          <button type="button" class="cb-head-helpdesk-meter-part is-active" data-cb-helpdesk-header-filter="active" aria-label="Nepřečtené tikety v řešení" style="border:0;padding:2px 3px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;cursor:pointer;font:inherit;color:inherit;">
            <strong data-cb-helpdesk-header-count="active" style="font-size:13px;line-height:1;">0</strong>
            <span class="cb_tooltip_panel cb_tooltip_card" data-cb-helpdesk-head-tooltip="1" style="min-width:0;white-space:nowrap;pointer-events:none;font-size:var(--fs_12);line-height:1.2;">
              <span style="display:grid;gap:3px;">
                <span style="color:var(--clr_cervena);font-weight:700;">Nepřečtené tikety</span>
                <span style="color:var(--clr_cerna);font-weight:400;">v řešení</span>
              </span>
            </span>
          </button>
          <button type="button" class="cb-head-helpdesk-meter-part is-resolved" data-cb-helpdesk-header-filter="resolved" aria-label="Nepřečtené tikety vyřešené, uzavřené" style="border:0;padding:2px 3px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;cursor:pointer;font:inherit;color:inherit;">
            <strong data-cb-helpdesk-header-count="resolved" style="font-size:13px;line-height:1;">0</strong>
            <span class="cb_tooltip_panel cb_tooltip_card" data-cb-helpdesk-head-tooltip="1" style="min-width:0;white-space:nowrap;pointer-events:none;font-size:var(--fs_12);line-height:1.2;">
              <span style="display:grid;gap:3px;">
                <span style="color:var(--clr_cervena);font-weight:700;">Nepřečtené tikety</span>
                <span style="color:var(--clr_cerna);font-weight:400;">vyřešené, uzavřené</span>
              </span>
            </span>
          </button>
        </div>
      </div>
      <?php require __DIR__ . '/hlavicka/head_user.php'; ?>
      <div class="head_user_gap">
        <a class="head_user_gap_btn head_user_gap_btn--hr" href="<?= h(cb_module_url('hr')) ?>">HR</a>
        <a class="head_user_gap_btn head_user_gap_btn--smeny" href="<?= h(cb_module_url('smeny')) ?>">směny</a>
      </div>
    <?php else: ?>
      <div class="head_guest ram_hlavicka bg_bila zaobleni_12"></div>
    <?php endif; ?>

  </div>
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
      return fetch(apiUrl + '?helpdesk_action=stav_tiketu', {
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

    function openCard(filter) {
      var filterValue = String(filter || 'all');
      if (window.CB_HELPDESK20 && typeof window.CB_HELPDESK20.openUnreadFilter === 'function') {
        window.CB_HELPDESK20.openUnreadFilter(filterValue);
        return;
      }

      var root = document.querySelector('.card_shell[data-card-id="20"]');
      if (root instanceof HTMLElement) {
        var expanded = root.querySelector('[data-card-expanded]');
        var isHidden = expanded instanceof HTMLElement && expanded.classList.contains('is-hidden');
        if (isHidden && window.CB_KARTY_MINMAX && typeof window.CB_KARTY_MINMAX.openCardMax === 'function') {
          window.CB_KARTY_MINMAX.openCardMax(root);
        }
        root.scrollIntoView({behavior: 'smooth', block: 'start'});
      }

      document.dispatchEvent(new CustomEvent('cb:helpdesk-header-filter', {
        detail: {
          filter: filterValue
        }
      }));
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
      open: openCard
    };

    document.addEventListener('click', function (e) {
      var target = e.target;
      if (!(target instanceof Element)) { return; }
      var item = target.closest('[data-cb-helpdesk-header-filter]');
      if (!(item instanceof HTMLElement)) { return; }
      openCard(item.getAttribute('data-cb-helpdesk-header-filter') || 'all');
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
<?php if ($cbLoginOk && !$cbHelpdeskIsRoleOne): ?>
  <div id="cb-helpdesk-modal" style="display:none;position:fixed;inset:0;z-index:13000;align-items:center;justify-content:center;padding:18px;background:rgba(15,23,42,.48);">
    <div class="ram_normal zaobleni_12 bg_bila" role="dialog" aria-modal="true" aria-labelledby="cb-helpdesk-modal-title" style="width:min(720px,calc(100vw - 32px));max-height:calc(100vh - 36px);overflow:auto;padding:16px 18px 14px;">
      <div style="display:flex;align-items:center;justify-content:flex-start;gap:12px;margin-bottom:12px;">
        <h2 id="cb-helpdesk-modal-title" style="margin:0;font-size:24px;line-height:1.2;color:#0f3f91;">HelpDesk</h2>
      </div>

      <div style="display:grid;grid-template-columns:160px 1fr;gap:10px 12px;align-items:center;">
        <div style="font-weight:700;color:#334155;">Zadává:</div>
        <div style="color:#334155;line-height:1.4;"><?= h($cbUserName) ?> (<?= h($cbUserRoleLabel) ?>)</div>

        <label for="cb-helpdesk-typ">Typ</label>
        <select id="cb-helpdesk-typ" style="width:100%;min-height:34px;padding:6px 10px;border:1px solid rgba(15,23,42,.18);border-radius:8px;background:#fff;">
          <option value="chyba">Chyba systému</option>
          <option value="dotaz">Dotaz</option>
          <option value="navrh">Námět na vylepšení</option>
        </select>

        <label for="cb-helpdesk-predmet">Předmět</label>
        <div>
          <input type="text" id="cb-helpdesk-predmet" maxlength="160" placeholder="Nutno vyplnit" style="width:100%;min-height:34px;padding:6px 10px;border:1px solid rgba(15,23,42,.18);border-radius:8px;background:#fff;">
        </div>

        <div style="align-self:start;padding-top:8px;">Určení:</div>
        <div style="display:grid;gap:8px;padding:4px 0;">
          <label style="display:flex;align-items:flex-start;gap:8px;font-size:12px;line-height:1.35;">
            <input type="radio" name="cb-helpdesk-urceni" value="admin">
            <span>Pouze pro admina</span>
          </label>
          <label style="display:flex;align-items:flex-start;gap:8px;font-size:12px;line-height:1.35;">
            <input type="radio" name="cb-helpdesk-urceni" value="reagovat" checked>
            <span>Všichni mohou reagovat</span>
          </label>
          <label style="display:flex;align-items:flex-start;gap:8px;font-size:12px;line-height:1.35;">
            <input type="radio" name="cb-helpdesk-urceni" value="cist">
            <span>Všichni mohou číst</span>
          </label>
        </div>

        <label for="cb-helpdesk-popis" style="align-self:start;padding-top:8px;">Popis</label>
        <div>
          <textarea id="cb-helpdesk-popis" rows="8" placeholder="Minimální délka zprávy je 25 znaků" style="width:100%;padding:8px 10px;border:1px solid rgba(15,23,42,.18);border-radius:8px;background:#fff;resize:vertical;"></textarea>
        </div>

        <label style="align-self:start;padding-top:8px;">Přílohy</label>
        <div id="cb-helpdesk-prilohy" style="display:grid;gap:8px;">
          <div data-cb-helpdesk-attachment-list="1" style="display:grid;gap:8px;"></div>
        </div>
      </div>

      <p id="cb-helpdesk-msg" style="margin:12px 0 0 0;min-height:20px;color:#b91c1c;font-size:13px;line-height:1.35;"></p>

      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
        <button type="button" data-cb-helpdesk-send="1" style="display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:7px 14px;border:1px solid #cbd5e1;border-radius:8px;background:#e5e7eb;color:#64748b;font-size:13px;font-weight:700;line-height:1.15;cursor:default;" disabled>Odeslat</button>
        <button type="button" data-cb-helpdesk-close="1" style="display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:7px 14px;border:1px solid rgba(15,23,42,.14);border-radius:8px;background:#fff;color:#1f2933;font-size:13px;font-weight:700;line-height:1.15;cursor:pointer;">Zavřít bez odeslání</button>
      </div>
    </div>
  </div>
  <script>
  (function () {
    var modal = document.getElementById('cb-helpdesk-modal');
    if (!modal) { return; }

    var openBtn = document.querySelector('[data-cb-helpdesk-open="1"]');
    var msg = document.getElementById('cb-helpdesk-msg');
    var typ = document.getElementById('cb-helpdesk-typ');
    var predmet = document.getElementById('cb-helpdesk-predmet');
    var popis = document.getElementById('cb-helpdesk-popis');
    var attachmentList = modal.querySelector('[data-cb-helpdesk-attachment-list="1"]');
    var sendBtn = modal.querySelector('[data-cb-helpdesk-send="1"]');
    var lastActive = null;
    var isBusy = false;

    if (!openBtn || !msg || !typ || !predmet || !popis || !attachmentList || !sendBtn) { return; }

    function setBusy(on) {
      isBusy = !!on;
      sendBtn.disabled = !!on;
      sendBtn.style.opacity = on ? '0.7' : '1';
      syncSendButton();
    }

    function getSelectedUrceni() {
      var checked = modal.querySelector('input[name="cb-helpdesk-urceni"]:checked');
      return checked instanceof HTMLInputElement ? String(checked.value || 'reagovat') : 'reagovat';
    }

    function getDescriptionLength() {
      return String(popis.value || '').trim().length;
    }

    function isFormValid() {
      return String(predmet.value || '').trim() !== '' && getDescriptionLength() > 25;
    }

    function syncSendButton() {
      var enabled = !isBusy && isFormValid();
      sendBtn.disabled = !enabled;
      sendBtn.style.cursor = enabled ? 'pointer' : 'default';
      sendBtn.style.background = enabled ? '#0f3f91' : '#e5e7eb';
      sendBtn.style.borderColor = enabled ? '#0f3f91' : '#cbd5e1';
      sendBtn.style.color = enabled ? '#fff' : '#64748b';
    }

    function createAttachmentRow() {
      var row = document.createElement('div');
      row.style.display = 'flex';
      row.style.alignItems = 'center';
      row.style.gap = '8px';
      row.style.minHeight = '34px';
      row.setAttribute('data-cb-helpdesk-attachment-row', '1');

      var input = document.createElement('input');
      input.type = 'file';
      input.name = 'soubor';
      input.style.width = '100%';
      input.style.minHeight = '34px';
      input.style.padding = '6px 10px';
      input.style.border = '1px solid rgba(15,23,42,.18)';
      input.style.borderRadius = '8px';
      input.style.background = '#fff';

      var text = document.createElement('div');
      text.style.display = 'none';
      text.style.width = '100%';
      text.style.minHeight = '34px';
      text.style.padding = '7px 10px';
      text.style.border = '1px solid rgba(15,23,42,.12)';
      text.style.borderRadius = '8px';
      text.style.background = '#f8fafc';
      text.style.color = '#334155';
      text.style.fontSize = '13px';
      text.style.lineHeight = '1.35';
      text.setAttribute('data-cb-helpdesk-attachment-text', '1');

      row.appendChild(input);
      row.appendChild(text);
      attachmentList.appendChild(row);
      return input;
    }

    function ensureEmptyAttachmentRow() {
      var inputs = attachmentList.querySelectorAll('input[type="file"]');
      if (!inputs.length) {
        createAttachmentRow();
        return;
      }

      var last = inputs[inputs.length - 1];
      if (last instanceof HTMLInputElement && last.files && last.files.length > 0) {
        createAttachmentRow();
      }
    }

    function getAttachmentFiles() {
      var out = [];
      attachmentList.querySelectorAll('input[type="file"]').forEach(function (input) {
        if (!(input instanceof HTMLInputElement)) { return; }
        if (input.files && input.files.length > 0) {
          out.push(input.files[0]);
        }
      });
      return out;
    }

    async function uploadAttachment(idHelpdesk, idZprava, file) {
      var formData = new FormData();
      formData.append('id_helpdesk', String(idHelpdesk));
      formData.append('id_helpdesk_zprava', String(idZprava));
      formData.append('soubor', file);

      formData.append('helpdesk_action', 'priloha_nahrat');

      var response = await fetch('<?= h($cbHelpdeskApiUrl) ?>', {
        method: 'POST',
        headers: {
          'X-Comeback-Helpdesk': '1'
        },
        body: formData
      });
      var data = await response.json().catch(function () { return {}; });
      if (!response.ok || !data || data.ok !== true) {
        throw new Error((data && data.err) ? String(data.err) : 'Nahrání přílohy se nepodařilo.');
      }
    }

    function resetForm() {
      typ.value = 'chyba';
      predmet.value = '';
      popis.value = '';
      msg.textContent = '';
      msg.style.color = '#b91c1c';
      attachmentList.innerHTML = '';
      var checked = modal.querySelector('input[name="cb-helpdesk-urceni"][value="reagovat"]');
      if (checked instanceof HTMLInputElement) {
        checked.checked = true;
      }
      ensureEmptyAttachmentRow();
      syncSendButton();
    }

    function openModal() {
      lastActive = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      modal.style.display = 'flex';
      predmet.focus();
    }

    function closeModal(reset) {
      modal.style.display = 'none';
      setBusy(false);
      if (reset) {
        resetForm();
      }
      if (lastActive instanceof HTMLElement) {
        lastActive.focus();
      }
    }

    function postJson(url, data) {
      return fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Comeback-Helpdesk': '1'
        },
        body: JSON.stringify(data)
      }).then(function (r) {
        return r.json().catch(function () { return {}; });
      });
    }

    openBtn.addEventListener('click', function () {
      openModal();
    });

    modal.addEventListener('click', function (e) {
      var target = e.target;
      if (!(target instanceof Element)) { return; }
      if (target.closest('[data-cb-helpdesk-close="1"]')) {
        closeModal(false);
      }
    });

    attachmentList.addEventListener('change', function (e) {
      var target = e.target;
      if (!(target instanceof HTMLInputElement)) { return; }
      if (target.type !== 'file') { return; }
      var row = target.closest('[data-cb-helpdesk-attachment-row="1"]');
      if (row instanceof HTMLElement) {
        var text = row.querySelector('[data-cb-helpdesk-attachment-text="1"]');
        if (text instanceof HTMLElement) {
          if (target.files && target.files.length > 0) {
            text.textContent = target.files[0].name || 'Soubor';
            text.style.display = 'block';
            target.style.display = 'none';
          } else {
            text.textContent = '';
            text.style.display = 'none';
            target.style.display = '';
          }
        }
      }
      ensureEmptyAttachmentRow();
    });

    predmet.addEventListener('input', syncSendButton);
    popis.addEventListener('input', syncSendButton);
    modal.querySelectorAll('input[name="cb-helpdesk-urceni"]').forEach(function (radio) {
      radio.addEventListener('change', syncSendButton);
    });

    sendBtn.addEventListener('click', async function () {
      if (!isFormValid() || isBusy) {
        syncSendButton();
        return;
      }
      msg.textContent = '';
      msg.style.color = '#b91c1c';
      setBusy(true);

      try {
        var data = await postJson('<?= h($cbHelpdeskApiUrl) ?>?helpdesk_action=vytvorit', {
          typ: typ.value,
          predmet: predmet.value,
          urceni: getSelectedUrceni(),
          popis: popis.value
        });
        if (!data || data.ok !== true) {
          msg.textContent = (data && data.err) ? String(data.err) : 'Odeslání se nepodařilo.';
          setBusy(false);
          return;
        }

        var files = getAttachmentFiles();
        var failedUploads = [];
        for (var i = 0; i < files.length; i++) {
          try {
            await uploadAttachment(data.id_helpdesk, data.id_helpdesk_zprava, files[i]);
          } catch (err) {
            failedUploads.push(files[i].name || ('Příloha ' + String(i + 1)));
          }
        }

        if (failedUploads.length > 0) {
          msg.style.color = '#9a6700';
          msg.textContent = 'Požadavek byl odeslán, ale nepodařilo se nahrát: ' + failedUploads.join(', ');
          window.setTimeout(function () {
            closeModal(true);
          }, 1800);
          return;
        }

        msg.style.color = '#166534';
        msg.textContent = 'Požadavek byl odeslán.';
        document.dispatchEvent(new CustomEvent('cb:helpdesk-created', {
          detail: {
            id_helpdesk: data.id_helpdesk,
            id_helpdesk_zprava: data.id_helpdesk_zprava,
            predmet: String(predmet.value || '').trim(),
            typ: String(typ.value || '').trim()
          }
        }));
        window.setTimeout(function () {
          closeModal(true);
        }, 350);
      } catch (e) {
        msg.textContent = 'Odeslání se nepodařilo.';
        setBusy(false);
      }
    });

    resetForm();
  })();
  </script>
<?php endif; ?>
<?php if ($cbLoginOk && $cbHelpdeskIsRoleOne): ?>
  <script>
  (function () {
    var openBtn = document.querySelector('[data-cb-helpdesk-card-open="1"]');
    if (!(openBtn instanceof HTMLButtonElement)) { return; }

    function openHelpdeskCard() {
      var root = document.querySelector('.card_shell[data-card-id="20"]');
      if (!(root instanceof HTMLElement)) { return; }

      var expanded = root.querySelector('[data-card-expanded]');
      var isHidden = expanded instanceof HTMLElement && expanded.classList.contains('is-hidden');
      if (isHidden && window.CB_KARTY_MINMAX && typeof window.CB_KARTY_MINMAX.openCardMax === 'function') {
        window.CB_KARTY_MINMAX.openCardMax(root);
      }

      root.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    openBtn.addEventListener('click', function () {
      openHelpdeskCard();
    });
  })();
  </script>
<?php endif; ?>
