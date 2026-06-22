<?php
// K20 ...
// karty/admin_helpdesk.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpdesk_prava.php';

if (!function_exists('cb_helpdesk_card_h')) {
    function cb_helpdesk_card_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('cb_helpdesk_card_author')) {
    function cb_helpdesk_card_author(array $row): string
    {
        $author = trim((string)($row['jmeno'] ?? '') . ' ' . (string)($row['prijmeni'] ?? ''));
        if ($author !== '') {
            return $author;
        }

        return 'ID ' . (string)(int)($row['id_user_zalozil'] ?? 0);
    }
}

if (!function_exists('cb_helpdesk_card_visibility_label')) {
    function cb_helpdesk_card_visibility_label(int $value): string
    {
        if ($value === 1) {
            return 'Všichni mohou reagovat';
        }
        if ($value === 2) {
            return 'Všichni mohou číst';
        }

        return 'Pouze pro admina';
    }
}

if (!function_exists('cb_helpdesk_card_type_label')) {
    function cb_helpdesk_card_type_label(string $value): string
    {
        if ($value === 'chyba') {
            return 'Chyba systému';
        }
        if ($value === 'dotaz') {
            return 'Dotaz';
        }
        if ($value === 'navrh') {
            return 'Námět na vylepšení';
        }

        return $value;
    }
}

$card_min_html = '';
$card_max_html = '';
$hdBtnBase = 'card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 displ_inline_flex';
$hdBtnPrimary = $hdBtnBase . ' card_btn_primary';

if (empty($_SESSION['login_ok'])) {
    $card_min_html = '<p class="card_text odstup_vnejsi_0">Nutné přihlášení.</p>';
    $card_max_html = $card_min_html;
    return;
}

$idUser = cb_helpdesk_current_user_id();
$isAdmin = cb_helpdesk_is_admin();
$conn = db();

$stats = [
    'total' => 0,
    'open' => 0,
    'closed' => 0,
    'new' => 0,
    'active' => 0,
];
$items = [];

if ($isAdmin) {
    $resStats = $conn->query("
        SELECT
            COUNT(*) AS total,
            SUM(stav IN ('nový', 'řeší se')) AS open_cnt,
            SUM(stav IN ('vyřešeno', 'zamítnuto')) AS closed_cnt,
            SUM(stav = 'nový') AS new_cnt,
            SUM(stav = 'řeší se') AS active_cnt
        FROM helpdesk
    ");
    if ($resStats instanceof mysqli_result) {
        $rowStats = $resStats->fetch_assoc() ?: [];
        $stats['total'] = (int)($rowStats['total'] ?? 0);
        $stats['open'] = (int)($rowStats['open_cnt'] ?? 0);
        $stats['closed'] = (int)($rowStats['closed_cnt'] ?? 0);
        $stats['new'] = (int)($rowStats['new_cnt'] ?? 0);
        $stats['active'] = (int)($rowStats['active_cnt'] ?? 0);
        $resStats->free();
    }

    $stmtItems = $conn->prepare('
        SELECT h.id_helpdesk, h.id_user_zalozil, h.typ, h.stav, h.verejny, h.predmet, h.vytvoreno, h.upraveno,
               u.jmeno, u.prijmeni,
               (SELECT COUNT(*) FROM helpdesk_zprava z WHERE z.id_helpdesk = h.id_helpdesk) AS pocet_zprav
        FROM helpdesk h
        LEFT JOIN `user` u ON u.id_user = h.id_user_zalozil
        ORDER BY FIELD(h.stav, \'nový\', \'řeší se\', \'vyřešeno\', \'zamítnuto\'), h.upraveno DESC, h.vytvoreno DESC
        LIMIT 120
    ');
} else {
    $stmtStats = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(stav IN ('nový', 'řeší se')) AS open_cnt,
            SUM(stav IN ('vyřešeno', 'zamítnuto')) AS closed_cnt,
            SUM(stav = 'nový') AS new_cnt,
            SUM(stav = 'řeší se') AS active_cnt
        FROM helpdesk
        WHERE id_user_zalozil = ?
    ");
    if ($stmtStats instanceof mysqli_stmt) {
        $stmtStats->bind_param('i', $idUser);
        $stmtStats->execute();
        $resStats = $stmtStats->get_result();
        if ($resStats instanceof mysqli_result) {
            $rowStats = $resStats->fetch_assoc() ?: [];
            $stats['total'] = (int)($rowStats['total'] ?? 0);
            $stats['open'] = (int)($rowStats['open_cnt'] ?? 0);
            $stats['closed'] = (int)($rowStats['closed_cnt'] ?? 0);
            $stats['new'] = (int)($rowStats['new_cnt'] ?? 0);
            $stats['active'] = (int)($rowStats['active_cnt'] ?? 0);
            $resStats->free();
        }
        $stmtStats->close();
    }

    $stmtItems = $conn->prepare('
        SELECT h.id_helpdesk, h.id_user_zalozil, h.typ, h.stav, h.verejny, h.predmet, h.vytvoreno, h.upraveno,
               u.jmeno, u.prijmeni,
               (SELECT COUNT(*) FROM helpdesk_zprava z WHERE z.id_helpdesk = h.id_helpdesk) AS pocet_zprav
        FROM helpdesk h
        LEFT JOIN `user` u ON u.id_user = h.id_user_zalozil
        WHERE h.id_user_zalozil = ?
           OR h.verejny IN (1, 2)
           OR EXISTS (SELECT 1 FROM helpdesk_sledujici sx WHERE sx.id_helpdesk = h.id_helpdesk AND sx.id_user = ?)
        ORDER BY h.upraveno DESC, h.vytvoreno DESC
        LIMIT 120
    ');
}

if ($stmtItems instanceof mysqli_stmt) {
    if ($isAdmin) {
        $stmtItems->execute();
    } else {
        $stmtItems->bind_param('ii', $idUser, $idUser);
        $stmtItems->execute();
    }
    $resItems = $stmtItems->get_result();
    if ($resItems instanceof mysqli_result) {
        while ($row = $resItems->fetch_assoc()) {
            $items[] = $row;
        }
        $resItems->free();
    }
    $stmtItems->close();
}

$recentItems = array_slice($items, 0, 4);
$helpdeskApiUrl = cb_url('index.php');

ob_start();
?>
<div class="cb-hd-card-min" style="display:grid;gap:6px;">
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;">
    <button type="button" class="ram_normal zaobleni_10" data-cb-hd-min-filter="nový" style="padding:6px 8px;background:#f8fbff;border:1px solid rgba(15,23,42,.10);text-align:left;cursor:pointer;">
      <div style="font-size:10px;color:#64748b;line-height:1.1;">nový</div>
      <div style="font-size:16px;font-weight:700;line-height:1.05;color:#0f172a;margin-top:1px;"><?= cb_helpdesk_card_h((string)$stats['new']) ?></div>
    </button>
    <button type="button" class="ram_normal zaobleni_10" data-cb-hd-min-filter="řeší se" style="padding:6px 8px;background:#fffaf0;border:1px solid rgba(15,23,42,.10);text-align:left;cursor:pointer;">
      <div style="font-size:10px;color:#64748b;line-height:1.1;">řeší se</div>
      <div style="font-size:16px;font-weight:700;line-height:1.05;color:#9a6700;margin-top:1px;"><?= cb_helpdesk_card_h((string)$stats['active']) ?></div>
    </button>
    <button type="button" class="ram_normal zaobleni_10" data-cb-hd-min-filter="uzavřené" style="padding:6px 8px;background:#f3faf5;border:1px solid rgba(15,23,42,.10);text-align:left;cursor:pointer;">
      <div style="font-size:10px;color:#64748b;line-height:1.1;">uzavřené</div>
      <div style="font-size:16px;font-weight:700;line-height:1.05;color:#166534;margin-top:1px;"><?= cb_helpdesk_card_h((string)$stats['closed']) ?></div>
    </button>
  </div>
</div>
<script>
(function () {
  if (window.__CB_HELPDESK20_INIT__ === true) { return; }
  window.__CB_HELPDESK20_INIT__ = true;

  var apiUrl = <?= json_encode($helpdeskApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
  var activeDetailId = '';

  var btnBaseClass = 'card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 displ_inline_flex';
  var btnPrimaryClass = btnBaseClass + ' card_btn_primary';
  var btnDangerStyle = 'border-color:var(--clr_pruhledna_cervena_28);background:var(--clr_ruzova_5);color:var(--clr_cervena);';
  var btnSuccessStyle = 'border-color:rgba(22,163,74,.24);background:var(--clr_zelena_3);color:var(--clr_zelena);';

  function filterStatusValue(state) {
    var value = text(state).trim();
    if (value === 'vyřešeno' || value === 'zamítnuto' || value === 'uzavřené') {
      return 'uzavřené';
    }
    if (value === 'řeší se') {
      return 'řeší se';
    }
    return 'nový';
  }

  function text(v) {
    if (v === null || v === undefined) { return ''; }
    return String(v);
  }

  function esc(v) {
    return text(v).replace(/[&<>'"]/g, function (ch) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[ch];
    });
  }

  function visibilityText(value) {
    var num = Number(value);
    if (num === 1) { return 'Všichni mohou reagovat'; }
    if (num === 2) { return 'Všichni mohou číst'; }
    return 'Pouze pro admina';
  }

  function typeText(value) {
    var textValue = text(value);
    if (textValue === 'chyba') { return 'Chyba systému'; }
    if (textValue === 'dotaz') { return 'Dotaz'; }
    if (textValue === 'navrh') { return 'Námět na vylepšení'; }
    return textValue;
  }

  function getRoot() {
    var roots = document.querySelectorAll('.card_shell[data-card-id="20"]');
    for (var i = 0; i < roots.length; i++) {
      var item = roots[i];
      if (!(item instanceof HTMLElement)) {
        continue;
      }
      if (item.closest('[data-cb-maxi-clone="1"]') instanceof HTMLElement) {
        continue;
      }
      return item;
    }
    return null;
  }

  function getExpandedBox() {
    var overlayExpanded = document.querySelector('[data-cb-maxi-clone="1"] .card_shell[data-card-id="20"] [data-card-expanded]');
    if (overlayExpanded instanceof HTMLElement) {
      return overlayExpanded;
    }

    var root = getRoot();
    if (!(root instanceof HTMLElement)) {
      return null;
    }

    var localExpanded = root.querySelector('[data-card-expanded]');
    return localExpanded instanceof HTMLElement ? localExpanded : null;
  }

  function getListBox() {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return null; }
    return expanded.querySelector('[data-cb-hd-list]');
  }

  function getItemRow(id) {
    var list = getListBox();
    if (!(list instanceof HTMLElement)) { return null; }
    return list.querySelector('[data-hd-item="' + String(id).replace(/"/g, '') + '"]');
  }

  function getInlineDetailBox(row) {
    if (!(row instanceof HTMLElement)) {
      return null;
    }
    var box = row.querySelector('[data-hd-inline-detail="1"]');
    return box instanceof HTMLElement ? box : null;
  }

  function getRowOpenButton(row) {
    if (!(row instanceof HTMLElement)) {
      return null;
    }
    var button = row.querySelector('[data-cb-hd-open-detail]');
    return button instanceof HTMLButtonElement ? button : null;
  }

  function setRowOpenState(row, isOpen) {
    var button = getRowOpenButton(row);
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }
    button.textContent = isOpen ? 'Zavřít' : 'Otevřít';
    button.className = isOpen ? btnBaseClass : btnPrimaryClass;
    if (isOpen) {
      button.style.background = '#fff';
      button.style.color = 'var(--clr_seda_4)';
      button.style.borderColor = 'var(--clr_pruhledna_tmava_14)';
    } else {
      button.style.background = '';
      button.style.color = '';
      button.style.borderColor = '';
    }
  }

  function closeDetailRow(row) {
    var box = getInlineDetailBox(row);
    if (box instanceof HTMLElement) {
      box.style.display = 'none';
      box.innerHTML = '';
    }
    setRowOpenState(row, false);
  }

  function closeActiveDetail() {
    if (activeDetailId === '') {
      return;
    }
    var row = getItemRow(activeDetailId);
    if (row instanceof HTMLElement) {
      closeDetailRow(row);
    }
    activeDetailId = '';
  }

  function openCardMax() {
    var root = getRoot();
    if (!(root instanceof HTMLElement)) {
      return;
    }
    var toggle = root.querySelector('[data-card-toggle="1"]');
    var expanded = getExpandedBox();
    if (toggle instanceof HTMLButtonElement && expanded instanceof HTMLElement && expanded.classList.contains('is-hidden')) {
      toggle.click();
    }
  }

  function setFilterValue(value) {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return; }
    var select = expanded.querySelector('[data-cb-hd-filter="1"]');
    if (!(select instanceof HTMLSelectElement)) { return; }
    select.value = text(value);
    applyFilter();
  }

  function waitForExpanded(callback, attempt) {
    var tries = Number(attempt || 0);
    var listBox = getListBox();
    if (listBox instanceof HTMLElement) {
      callback();
      return;
    }
    if (tries >= 25) {
      return;
    }
    window.setTimeout(function () {
      waitForExpanded(callback, tries + 1);
    }, 120);
  }

  function reloadOpenDetail(id) {
    var targetId = String(id || '').trim();
    if (targetId === '') {
      return;
    }

    if (activeDetailId === targetId) {
      activeDetailId = '';
    }
    loadDetail(targetId);
  }

  function postJson(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Comeback-Helpdesk': '1'
      },
      credentials: 'same-origin',
      body: JSON.stringify(data)
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (json) {
        return {ok: r.ok, data: json};
      });
    });
  }

  function renderAttachments(prilohy) {
    if (!Array.isArray(prilohy) || !prilohy.length) {
      return '';
    }

    var html = '<div style="display:grid;gap:6px;margin-top:10px;">';
    html += '<div style="font-size:12px;font-weight:700;color:#334155;">Přílohy</div>';
    for (var i = 0; i < prilohy.length; i++) {
      var p = prilohy[i] || {};
      var name = text(p.puvodni_nazev || p.ulozeny_nazev || ('Příloha ' + String(i + 1)));
      var path = text(p.cesta || '');
      if (path === '') { continue; }
      html += '<a href="' + esc(path) + '" target="_blank" rel="noopener" style="color:#0f3f91;text-decoration:underline;">' + esc(name) + '</a>';
    }
    html += '</div>';
    return html;
  }

  function renderMessages(zpravy) {
    if (!Array.isArray(zpravy) || !zpravy.length) {
      return '<div style="font-size:13px;color:#64748b;">Zatím bez reakcí.</div>';
    }

    var html = '<div style="display:grid;gap:8px;">';
    for (var i = 0; i < zpravy.length; i++) {
      var z = zpravy[i] || {};
      var first = text(z.jmeno || '');
      var last = text(z.prijmeni || '');
      var author = (first + ' ' + last).trim();
      if (author === '') {
        author = 'ID ' + text(z.id_user || '0');
      }
      var bg = text(z.typ_autora || '') === 'admin' ? '#f8fbff' : '#ffffff';
      html += '<div class="ram_normal zaobleni_10" style="padding:9px 10px;background:' + bg + ';">';
      html += '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px;">';
      html += '<strong style="font-size:13px;color:#0f172a;">' + esc(author) + '</strong>';
      html += '<span style="font-size:11px;color:#64748b;">' + esc(text(z.vytvoreno || '')) + '</span>';
      html += '</div>';
      html += '<div style="white-space:pre-wrap;font-size:13px;line-height:1.45;color:#1f2937;">' + esc(text(z.zprava || '')) + '</div>';
      html += '</div>';
    }
    html += '</div>';
    return html;
  }

  function renderReplyActions(id, ticket, data) {
    var canWrite = Number(data && data.can_write ? data.can_write : 0) === 1;
    var currentUserId = Number(data && data.current_user_id ? data.current_user_id : 0);
    var ownerId = Number(ticket && ticket.id_user_zalozil ? ticket.id_user_zalozil : 0);
    var html = '';

    if (canWrite) {
      html += '<textarea data-cb-hd-reply-text="1" rows="4" style="width:100%;padding:8px 10px;border:1px solid rgba(15,23,42,.18);border-radius:8px;background:#fff;resize:vertical;"></textarea>';
      html += '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:10px;">';
      if (!isAdmin && ownerId !== currentUserId) {
        html += '<button type="button" class="' + esc(btnBaseClass) + '" data-cb-hd-follow="' + esc(id) + '">Mám stejný problém</button>';
      }
      if (isAdmin) {
        html += '<button type="button" class="' + esc(btnBaseClass) + '" style="' + esc(btnDangerStyle) + '" data-cb-hd-quick-state="' + esc(id) + '" data-cb-hd-quick-value="zamítnuto">Zamítnout</button>';
        html += '<button type="button" class="' + esc(btnBaseClass) + '" style="' + esc(btnSuccessStyle) + '" data-cb-hd-quick-state="' + esc(id) + '" data-cb-hd-quick-value="vyřešeno">Vyřešeno</button>';
      }
      html += '<button type="button" class="' + esc(btnPrimaryClass) + '" data-cb-hd-send-reply="' + esc(id) + '">Odeslat odpověď</button>';
      html += '<button type="button" class="' + esc(btnBaseClass) + '" data-cb-hd-close-detail="1">Zavřít</button>';
      html += '</div>';
    } else {
      html += '<div style="font-size:13px;color:#64748b;margin-top:10px;">Na tento požadavek už nelze odpovídat.</div>';
      html += '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:10px;">';
      if (!isAdmin && ownerId !== currentUserId) {
        html += '<button type="button" class="' + esc(btnBaseClass) + '" data-cb-hd-follow="' + esc(id) + '">Mám stejný problém</button>';
      }
      if (isAdmin) {
        html += '<button type="button" class="' + esc(btnBaseClass) + '" style="' + esc(btnDangerStyle) + '" data-cb-hd-quick-state="' + esc(id) + '" data-cb-hd-quick-value="zamítnuto">Zamítnout</button>';
        html += '<button type="button" class="' + esc(btnBaseClass) + '" style="' + esc(btnSuccessStyle) + '" data-cb-hd-quick-state="' + esc(id) + '" data-cb-hd-quick-value="vyřešeno">Vyřešeno</button>';
      }
      html += '<button type="button" class="' + esc(btnBaseClass) + '" data-cb-hd-close-detail="1">Zavřít</button>';
      html += '</div>';
    }

    return html;
  }

  function renderDetail(data, row) {
    var ticket = data && data.ticket ? data.ticket : {};
    var detailBox = getInlineDetailBox(row);
    if (!(detailBox instanceof HTMLElement)) { return; }

    var id = text(ticket.id_helpdesk || '');
    var html = '<div class="ram_normal zaobleni_10" style="margin-top:10px;padding:12px;background:#f8fafc;">';
    html += '<div style="font-size:12px;line-height:1.4;color:#475569;margin-bottom:10px;">';
    html += '<strong>' + esc(text(ticket.stav || '')) + '</strong>';
    html += ' | ' + esc(typeText(ticket.typ || ''));
    html += ' | ' + esc(visibilityText(ticket.verejny || 0));
    html += '</div>';
    html += '<div style="margin-top:12px;white-space:pre-wrap;font-size:13px;line-height:1.5;color:#1f2937;">' + esc(text(ticket.popis || '')) + '</div>';
    html += renderAttachments(data && data.prilohy ? data.prilohy : []);
    html += '<div style="margin-top:12px;">' + renderMessages(data && data.zpravy ? data.zpravy : []) + '</div>';
    html += '<div style="margin-top:12px;">' + renderReplyActions(id, ticket, data || {}) + '</div>';
    html += '</div>';

    detailBox.innerHTML = html;
    detailBox.style.display = 'block';
  }

  function loadDetail(id) {
    var row = getItemRow(id);
    if (!(row instanceof HTMLElement)) { return; }
    var detailBox = getInlineDetailBox(row);
    if (!(detailBox instanceof HTMLElement)) { return; }

    if (activeDetailId === String(id)) {
      closeActiveDetail();
      return;
    }

    closeActiveDetail();
    activeDetailId = String(id);
    setRowOpenState(row, true);
    detailBox.style.display = 'block';
    detailBox.innerHTML = '<div style="font-size:13px;color:#64748b;">Načítám detail...</div>';

    fetch(apiUrl + '?helpdesk_action=detail&id_helpdesk=' + encodeURIComponent(String(id)), {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Comeback-Helpdesk': '1'
      }
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (data) {
        if (!data || data.ok !== true) {
          detailBox.innerHTML = '<div style="font-size:13px;color:#b91c1c;">Detail se nepodařilo načíst.</div>';
          setRowOpenState(row, false);
          activeDetailId = '';
          return;
        }
        renderDetail(data, row);
      })
      .catch(function () {
        detailBox.innerHTML = '<div style="font-size:13px;color:#b91c1c;">Detail se nepodařilo načíst.</div>';
        setRowOpenState(row, false);
        activeDetailId = '';
      });
  }

  function applyFilter() {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return; }
    var select = expanded.querySelector('[data-cb-hd-filter="1"]');
    if (!(select instanceof HTMLSelectElement)) { return; }
    var value = text(select.value || '');
    var list = getListBox();
    if (!(list instanceof HTMLElement)) { return; }

    list.querySelectorAll('[data-hd-item]').forEach(function (row) {
      if (!(row instanceof HTMLElement)) { return; }
      var rowState = text(row.getAttribute('data-hd-filtr') || '');
      row.style.display = (value === '' || value === rowState) ? '' : 'none';
    });
  }

  function updateRowState(id, state) {
    var row = getItemRow(id);
    if (!(row instanceof HTMLElement)) { return; }
    row.setAttribute('data-hd-stav', text(state));
    row.setAttribute('data-hd-filtr', filterStatusValue(state));
    var stateBox = row.querySelector('[data-hd-state-text="1"]');
    if (stateBox instanceof HTMLElement) {
      stateBox.textContent = text(state);
    }
    applyFilter();
  }

  function bumpRowReplyCount(id) {
    var row = getItemRow(id);
    if (!(row instanceof HTMLElement)) { return; }
    var countBox = row.querySelector('[data-hd-reply-count="1"]');
    if (!(countBox instanceof HTMLElement)) { return; }
    var value = parseInt(String(countBox.getAttribute('data-count') || countBox.textContent || '0'), 10);
    if (!Number.isFinite(value)) {
      value = 0;
    }
    value += 1;
    countBox.setAttribute('data-count', String(value));
    countBox.textContent = String(value);
  }

  function buildListRowHtml(detail) {
    var id = Number(detail && detail.id_helpdesk ? detail.id_helpdesk : 0);
    if (!Number.isFinite(id) || id <= 0) { return ''; }
    var predmet = esc(text(detail.predmet || ''));
    var stav = esc(text(detail.stav || 'nový'));
    var stavFiltr = esc(filterStatusValue(detail.stav || 'nový'));
    var typ = esc(text(detail.typ_label || detail.typ || ''));
    var visibility = esc(text(detail.visibility_label || ''));
    var replies = Number(detail && detail.pocet_zprav ? detail.pocet_zprav : 1);
    if (!Number.isFinite(replies) || replies < 0) { replies = 0; }

    var html = '';
    html += '<article class="ram_normal zaobleni_10" data-hd-item="' + String(id) + '" data-hd-stav="' + stav + '" data-hd-filtr="' + stavFiltr + '" style="padding:10px;background:#fff;">';
    html += '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">';
    html += '<div style="display:grid;gap:4px;">';
    html += '<strong style="font-size:14px;color:#0f172a;">#' + String(id) + ' ' + predmet + '</strong>';
    html += '<div style="font-size:12px;line-height:1.4;color:#64748b;">';
    html += 'Stav: <span data-hd-state-text="1">' + stav + '</span>';
    html += ' | Typ: ' + typ;
    if (visibility !== '') {
      html += ' | Určení: ' + visibility;
    }
    html += ' | Zprávy: <span data-hd-reply-count="1" data-count="' + String(replies) + '">' + String(replies) + '</span>';
    html += '</div>';
    html += '</div>';
    html += '<button type="button" class="' + btnPrimaryClass + '" data-cb-hd-open-detail="' + String(id) + '">Otevřít</button>';
    html += '</div>';
    html += '<div data-hd-inline-detail="1" style="display:none;"></div>';
    html += '</article>';
    return html;
  }

  document.addEventListener('click', function (e) {
    var target = e.target;
    if (!(target instanceof Element)) { return; }

    if (target.closest('[data-cb-hd-card-open="1"]')) {
      openCardMax();
      return;
    }

    var minFilter = target.closest('[data-cb-hd-min-filter]');
    if (minFilter instanceof HTMLElement) {
      var filterValue = minFilter.getAttribute('data-cb-hd-min-filter') || '';
      openCardMax();
      waitForExpanded(function () {
        setFilterValue(filterValue);
      }, 0);
      return;
    }

    var openDetail = target.closest('[data-cb-hd-open-detail]');
    if (openDetail instanceof HTMLElement) {
      openCardMax();
      waitForExpanded(function () {
        loadDetail(openDetail.getAttribute('data-cb-hd-open-detail') || '');
      }, 0);
      return;
    }

    if (target.closest('[data-cb-hd-close-detail="1"]')) {
      closeActiveDetail();
      return;
    }

    var followBtn = target.closest('[data-cb-hd-follow]');
    if (followBtn instanceof HTMLElement) {
      postJson(apiUrl + '?helpdesk_action=sledovat', {
        id_helpdesk: followBtn.getAttribute('data-cb-hd-follow') || '',
        duvod: 'stejny_problem'
      }).then(function (result) {
        if (!result.ok || !result.data || result.data.ok !== true) {
          window.alert(result.data && result.data.err ? String(result.data.err) : 'Zapis sledovani selhal.');
          return;
        }
        window.alert('Zapsáno.');
      });
      return;
    }

    var replyBtn = target.closest('[data-cb-hd-send-reply]');
    if (replyBtn instanceof HTMLElement) {
      var activeRow = getItemRow(activeDetailId);
      var detailBox = getInlineDetailBox(activeRow);
      if (!(detailBox instanceof HTMLElement)) { return; }
      var replyInput = detailBox.querySelector('[data-cb-hd-reply-text="1"]');
      if (!(replyInput instanceof HTMLTextAreaElement)) { return; }
      var replyText = text(replyInput.value || '').trim();
      if (replyText === '') {
        window.alert('Doplň zprávu.');
        return;
      }
      var replyId = replyBtn.getAttribute('data-cb-hd-send-reply') || '';
      postJson(apiUrl + '?helpdesk_action=zprava_pridat', {
        id_helpdesk: replyId,
        zprava: replyText
      }).then(function (result) {
        if (!result.ok || !result.data || result.data.ok !== true) {
          window.alert(result.data && result.data.err ? String(result.data.err) : 'Odeslání odpovědi selhalo.');
          return;
        }
        if (result.data && result.data.stav) {
          updateRowState(replyId, String(result.data.stav));
        }
        bumpRowReplyCount(replyId);
        reloadOpenDetail(replyId);
      });
      return;
    }

    var stateBtn = target.closest('[data-cb-hd-quick-state]');
    if (stateBtn instanceof HTMLElement) {
      var stateId = stateBtn.getAttribute('data-cb-hd-quick-state') || '';
      var stateValue = stateBtn.getAttribute('data-cb-hd-quick-value') || '';
      postJson(apiUrl + '?helpdesk_action=stav_zmenit', {
        id_helpdesk: stateId,
        stav: stateValue
      }).then(function (result) {
        if (!result.ok || !result.data || result.data.ok !== true) {
          window.alert(result.data && result.data.err ? String(result.data.err) : 'Změna stavu selhala.');
          return;
        }
        updateRowState(stateId, stateValue);
        reloadOpenDetail(stateId);
      });
    }
  });

  document.addEventListener('change', function (e) {
    var target = e.target;
    if (!(target instanceof Element)) { return; }
    if (target.matches('[data-cb-hd-filter="1"]')) {
      applyFilter();
    }
  });

  document.addEventListener('cb:helpdesk-created', function (e) {
    if (isAdmin) { return; }
    var detail = e && e.detail ? e.detail : null;
    var list = getListBox();
    if (!(list instanceof HTMLElement) || !detail) { return; }

    var html = buildListRowHtml({
      id_helpdesk: detail.id_helpdesk,
      predmet: detail.predmet,
      stav: 'nový',
      typ: detail.typ,
      typ_label: detail.typ === 'chyba' ? 'Chyba systému' : (detail.typ === 'dotaz' ? 'Dotaz' : 'Námět na vylepšení'),
      visibility_label: '',
      pocet_zprav: 1
    });
    if (html === '') { return; }

    var emptyNote = list.querySelector('[data-cb-hd-empty="1"]');
    if (emptyNote instanceof HTMLElement) {
      emptyNote.remove();
    }
    list.insertAdjacentHTML('afterbegin', html);
  });
})();
</script>
<?php
$card_min_html = (string)ob_get_clean();

ob_start();
?>
<section class="cb-hd-card-max" style="display:grid;gap:12px;">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div style="display:grid;gap:4px;">
      <div style="font-size:18px;font-weight:700;line-height:1.2;color:#0f172a;"><?= $isAdmin ? 'Správa HelpDesku' : 'Moje hlášení a historie' ?></div>
      <div style="font-size:13px;line-height:1.4;color:#475569;">
        <?php if ($isAdmin): ?>
          Admin vidí všechny požadavky a může měnit stav.
        <?php else: ?>
          Tady uvidíš svoje hlášení, reakce admina i dostupnou historii.
        <?php endif; ?>
      </div>
    </div>

    <?php if ($isAdmin): ?>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <label for="cb-hd-filter-20" style="font-size:13px;color:#334155;">Stav</label>
        <select id="cb-hd-filter-20" data-cb-hd-filter="1" style="min-height:34px;padding:6px 10px;border:1px solid rgba(15,23,42,.18);border-radius:8px;background:#fff;">
          <option value="">Vše</option>
          <option value="nový">Nový</option>
          <option value="řeší se">Řeší se</option>
          <option value="vyřešeno">Vyřešeno</option>
          <option value="zamítnuto">Zamítnuto</option>
        </select>
      </div>
    <?php else: ?>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <label for="cb-hd-filter-20-user" style="font-size:13px;color:#334155;">Stav</label>
        <select id="cb-hd-filter-20-user" data-cb-hd-filter="1" style="min-height:34px;padding:6px 10px;border:1px solid rgba(15,23,42,.18);border-radius:8px;background:#fff;">
          <option value="">Vše</option>
          <option value="nový">nový</option>
          <option value="řeší se">řeší se</option>
          <option value="uzavřené">uzavřené</option>
        </select>
      </div>
    <?php endif; ?>
  </div>

  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;">
    <div class="ram_normal zaobleni_10" style="padding:10px;background:#f8fbff;">
      <div style="font-size:11px;color:#64748b;line-height:1.2;"><?= $isAdmin ? 'Celkem požadavků' : 'Moje hlášení' ?></div>
      <div style="font-size:22px;font-weight:700;line-height:1.15;color:#0f172a;"><?= cb_helpdesk_card_h((string)$stats['total']) ?></div>
    </div>
    <div class="ram_normal zaobleni_10" style="padding:10px;background:#fffaf0;">
      <div style="font-size:11px;color:#64748b;line-height:1.2;"><?= $isAdmin ? 'Otevřené' : 'Otevřené' ?></div>
      <div style="font-size:22px;font-weight:700;line-height:1.15;color:#9a6700;"><?= cb_helpdesk_card_h((string)$stats['open']) ?></div>
    </div>
    <div class="ram_normal zaobleni_10" style="padding:10px;background:#f3faf5;">
      <div style="font-size:11px;color:#64748b;line-height:1.2;"><?= $isAdmin ? 'Uzavřené' : 'Uzavřené' ?></div>
      <div style="font-size:22px;font-weight:700;line-height:1.15;color:#166534;"><?= cb_helpdesk_card_h((string)$stats['closed']) ?></div>
    </div>
  </div>

  <div data-cb-hd-list="1" style="display:grid;gap:8px;">
    <?php if ($items === []): ?>
      <div data-cb-hd-empty="1" class="ram_normal zaobleni_10" style="padding:12px;background:#fff;color:#64748b;">Zatím bez záznamu.</div>
    <?php else: ?>
      <?php foreach ($items as $item): ?>
        <article class="ram_normal zaobleni_10" data-hd-item="<?= cb_helpdesk_card_h((string)(int)$item['id_helpdesk']) ?>" data-hd-stav="<?= cb_helpdesk_card_h((string)$item['stav']) ?>" data-hd-filtr="<?= cb_helpdesk_card_h(in_array((string)$item['stav'], ['vyřešeno', 'zamítnuto'], true) ? 'uzavřené' : (trim((string)$item['stav']) === 'řeší se' ? 'řeší se' : 'nový')) ?>" style="padding:10px;background:#fff;">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div style="display:grid;gap:4px;">
              <strong style="font-size:14px;color:#0f172a;"><?= cb_helpdesk_card_h('#' . (string)(int)$item['id_helpdesk'] . ' ' . (string)$item['predmet']) ?></strong>
              <div style="font-size:12px;line-height:1.4;color:#64748b;">
                Stav: <span data-hd-state-text="1"><?= cb_helpdesk_card_h((string)$item['stav']) ?></span>
                | Typ: <?= cb_helpdesk_card_h(cb_helpdesk_card_type_label((string)$item['typ'])) ?>
                | Určení: <?= cb_helpdesk_card_h(cb_helpdesk_card_visibility_label((int)$item['verejny'])) ?>
                | Autor: <?= cb_helpdesk_card_h(cb_helpdesk_card_author($item)) ?>
                | Zprávy: <span data-hd-reply-count="1" data-count="<?= cb_helpdesk_card_h((string)(int)$item['pocet_zprav']) ?>"><?= cb_helpdesk_card_h((string)(int)$item['pocet_zprav']) ?></span>
              </div>
            </div>
            <button type="button" class="<?= cb_helpdesk_card_h($hdBtnPrimary) ?>" data-cb-hd-open-detail="<?= cb_helpdesk_card_h((string)(int)$item['id_helpdesk']) ?>">Otevřít</button>
          </div>
          <div data-hd-inline-detail="1" style="display:none;"></div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>
<?php
$card_max_html = (string)ob_get_clean();
