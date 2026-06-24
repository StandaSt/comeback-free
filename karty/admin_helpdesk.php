<?php
// K20 ...
// karty/admin_helpdesk.php * Verze: V1 * Aktualizace: 23.06.2026
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

if (!function_exists('cb_helpdesk_card_author_color')) {
    function cb_helpdesk_card_author_color(array $row): string
    {
        $idRole = (int)($row['id_role'] ?? 0);
        if ($idRole === 3) {
            return '#c77b7b';
        }
        if ($idRole === 5 || $idRole === 7) {
            return '#6f8fcf';
        }
        if ($idRole === 9) {
            return '#334155';
        }

        return '#64748b';
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
    $card_min_html = '<p class="card_mini_text">Nutné přihlášení.</p>';
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
    'resolved' => 0,
    'rejected' => 0,
];
$items = [];

if ($isAdmin) {
    $resStats = $conn->query("
        SELECT
            COUNT(*) AS total,
            SUM(stav IN ('nový', 'řeší se')) AS open_cnt,
            SUM(stav IN ('vyřešeno', 'zamítnuto')) AS closed_cnt,
            SUM(stav = 'nový') AS new_cnt,
            SUM(stav = 'řeší se') AS active_cnt,
            SUM(stav = 'vyřešeno') AS resolved_cnt,
            SUM(stav = 'zamítnuto') AS rejected_cnt
        FROM helpdesk
    ");
    if ($resStats instanceof mysqli_result) {
        $rowStats = $resStats->fetch_assoc() ?: [];
        $stats['total'] = (int)($rowStats['total'] ?? 0);
        $stats['open'] = (int)($rowStats['open_cnt'] ?? 0);
        $stats['closed'] = (int)($rowStats['closed_cnt'] ?? 0);
        $stats['new'] = (int)($rowStats['new_cnt'] ?? 0);
        $stats['active'] = (int)($rowStats['active_cnt'] ?? 0);
        $stats['resolved'] = (int)($rowStats['resolved_cnt'] ?? 0);
        $stats['rejected'] = (int)($rowStats['rejected_cnt'] ?? 0);
        $resStats->free();
    }

    $stmtItems = $conn->prepare('
        SELECT h.id_helpdesk, h.id_user_zalozil, h.typ, h.stav, h.verejny, h.predmet, h.vytvoreno, h.upraveno,
               u.jmeno, u.prijmeni, u.id_role,
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
            SUM(stav = 'řeší se') AS active_cnt,
            SUM(stav = 'vyřešeno') AS resolved_cnt,
            SUM(stav = 'zamítnuto') AS rejected_cnt
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
            $stats['resolved'] = (int)($rowStats['resolved_cnt'] ?? 0);
            $stats['rejected'] = (int)($rowStats['rejected_cnt'] ?? 0);
            $resStats->free();
        }
        $stmtStats->close();
    }

    $stmtItems = $conn->prepare('
        SELECT h.id_helpdesk, h.id_user_zalozil, h.typ, h.stav, h.verejny, h.predmet, h.vytvoreno, h.upraveno,
               u.jmeno, u.prijmeni, u.id_role,
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

$helpdeskApiUrl = cb_url('index.php');
$arrowIconUrl = cb_url('img/icons/arrow-32.png');

ob_start();
?>
<div class="cb-hd-card-min" style="display:grid;gap:6px;">
  <div style="display:flex;gap:6px;flex-wrap:nowrap;">
    <button type="button" class="ram_normal zaobleni_10" data-cb-hd-min-filter="all" style="flex:1 1 calc(20% - 5px);min-width:0;display:flex;align-items:center;justify-content:space-between;gap:6px;padding:6px 8px;background:#eff6ff;border:1px solid rgba(15,23,42,.10);text-align:left;cursor:pointer;">
      <span class="card_mini_text" style="color:#0f172a;">Vše</span>
      <strong class="card_mini_text text_tucny" style="color:#355c9a;"><?= cb_helpdesk_card_h((string)$stats['total']) ?></strong>
    </button>
    <button type="button" class="ram_normal zaobleni_10" data-cb-hd-min-filter="new" style="flex:1 1 calc(20% - 5px);min-width:0;display:flex;align-items:center;justify-content:space-between;gap:6px;padding:6px 8px;background:#eef8ef;border:1px solid rgba(34,120,70,.28);text-align:left;cursor:pointer;">
      <span class="card_mini_text" style="color:#0f172a;">Nové</span>
      <strong class="card_mini_text text_tucny" style="color:#355c9a;"><?= cb_helpdesk_card_h((string)$stats['new']) ?></strong>
    </button>
    <button type="button" class="ram_normal zaobleni_10" data-cb-hd-min-filter="active" style="flex:1 1 calc(20% - 5px);min-width:0;display:flex;align-items:center;justify-content:space-between;gap:6px;padding:6px 8px;background:#eff6ff;border:1px solid rgba(15,23,42,.10);text-align:left;cursor:pointer;">
      <span class="card_mini_text" style="color:#0f172a;">Řeší se</span>
      <strong class="card_mini_text text_tucny" style="color:#355c9a;"><?= cb_helpdesk_card_h((string)$stats['active']) ?></strong>
    </button>
    <button type="button" class="ram_normal zaobleni_10" data-cb-hd-min-filter="resolved" style="flex:1 1 calc(20% - 5px);min-width:0;display:flex;align-items:center;justify-content:space-between;gap:6px;padding:6px 8px;background:#eff6ff;border:1px solid rgba(15,23,42,.10);text-align:left;cursor:pointer;">
      <span class="card_mini_text" style="color:#0f172a;">Uzavřeno</span>
      <strong class="card_mini_text text_tucny" style="color:#355c9a;"><?= cb_helpdesk_card_h((string)$stats['resolved']) ?></strong>
    </button>
    <button type="button" class="ram_normal zaobleni_10" data-cb-hd-min-filter="rejected" style="flex:1 1 calc(20% - 5px);min-width:0;display:flex;align-items:center;justify-content:space-between;gap:6px;padding:6px 8px;background:#eff6ff;border:1px solid rgba(15,23,42,.10);text-align:left;cursor:pointer;">
      <span class="card_mini_text" style="color:#0f172a;">Zamítnuto</span>
      <strong class="card_mini_text text_tucny" style="color:#355c9a;"><?= cb_helpdesk_card_h((string)$stats['rejected']) ?></strong>
    </button>
  </div>
</div>
<script>
(function () {
  if (window.__CB_HELPDESK20_INIT__ === true) { return; }
  window.__CB_HELPDESK20_INIT__ = true;

  var apiUrl = <?= json_encode($helpdeskApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var arrowIconUrl = <?= json_encode($arrowIconUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
  var activeDetailId = '';

  var btnBaseClass = 'card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 displ_inline_flex';
  var btnPrimaryClass = btnBaseClass + ' card_btn_primary';
  var btnDangerStyle = 'border-color:var(--clr_pruhledna_cervena_28);background:var(--clr_ruzova_5);color:var(--clr_cervena);';
  var btnSuccessStyle = 'border-color:rgba(22,163,74,.24);background:var(--clr_zelena_3);color:var(--clr_zelena);';

  function text(v) {
    if (v === null || v === undefined) { return ''; }
    return String(v);
  }

  function esc(v) {
    return text(v).replace(/[&<>'"]/g, function (ch) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[ch];
    });
  }

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

  function getDetailPanelBox() {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return null; }
    var box = expanded.querySelector('[data-cb-hd-detail-panel]');
    return box instanceof HTMLElement ? box : null;
  }

  function getDetailMarkerBox() {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return null; }
    var box = expanded.querySelector('[data-cb-hd-detail-marker]');
    return box instanceof HTMLElement ? box : null;
  }

  function getItemRow(id) {
    var list = getListBox();
    if (!(list instanceof HTMLElement)) { return null; }
    return list.querySelector('[data-hd-item="' + String(id).replace(/"/g, '') + '"]');
  }

  function normalizeFilterValue(value) {
    var normalized = text(value).trim().toLowerCase();
    if (normalized === '' || normalized === 'all' || normalized === 'vše') { return 'all'; }
    if (normalized === 'nový' || normalized === 'novy' || normalized === 'new') { return 'new'; }
    if (normalized === 'řeší se' || normalized === 'resi se' || normalized === 'active') { return 'active'; }
    if (normalized === 'vyřešeno' || normalized === 'vyreseno' || normalized === 'resolved') { return 'resolved'; }
    if (normalized === 'zamítnuto' || normalized === 'zamitnuto' || normalized === 'rejected') { return 'rejected'; }
    if (normalized === 'uzavřené' || normalized === 'uzavrene' || normalized === 'closed') { return 'closed'; }
    return 'all';
  }

  function getCurrentFilterValue() {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return 'new'; }
    return normalizeFilterValue(expanded.getAttribute('data-cb-hd-filter-value') || 'new');
  }

  function refreshFilterBlocks() {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return; }
    var current = getCurrentFilterValue();
    expanded.querySelectorAll('[data-cb-hd-filter-block]').forEach(function (button) {
      if (!(button instanceof HTMLButtonElement)) { return; }
      var isActive = normalizeFilterValue(button.getAttribute('data-cb-hd-filter-block') || '') === current;
      button.style.background = isActive ? '#eef8ef' : '#eff6ff';
      button.style.borderColor = isActive ? 'rgba(34,120,70,.28)' : 'rgba(15,23,42,.10)';
      button.style.boxShadow = isActive ? 'inset 0 0 0 1px rgba(15,23,42,.08)' : 'none';
      button.style.transform = isActive ? 'translateY(-1px)' : 'none';
      var label = button.querySelector('[data-cb-hd-filter-label]');
      if (label instanceof HTMLElement) {
        label.style.fontWeight = isActive ? '700' : '500';
        label.style.fontSize = isActive ? '13px' : '12px';
        label.style.color = '#0f172a';
      }
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  function setFilterValue(value) {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return; }
    expanded.setAttribute('data-cb-hd-filter-value', normalizeFilterValue(value));
    refreshFilterBlocks();
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

  function renderEmptyDetailPanel() {
    var box = getDetailPanelBox();
    if (!(box instanceof HTMLElement)) { return; }
    box.innerHTML = '<div class="ram_normal zaobleni_10" style="padding:14px 16px;background:#fff;">'
      + '<div style="font-size:16px;font-weight:700;line-height:1.2;color:#0f172a;">Vyber tiket vlevo</div>'
      + '<div style="font-size:13px;line-height:1.5;color:#64748b;margin-top:6px;">Tady se otevře pracovní panel vybraného tiketu.</div>'
      + '</div>';
  }

  function refreshActiveRowUi() {
    var list = getListBox();
    if (list instanceof HTMLElement) {
      list.querySelectorAll('[data-hd-item]').forEach(function (row) {
        if (!(row instanceof HTMLElement)) { return; }
        var isActive = activeDetailId !== '' && row.getAttribute('data-hd-item') === activeDetailId;
        row.style.background = isActive ? '#dbeafe' : '#fff';
        row.style.borderColor = isActive ? '#2563eb' : '';
        row.style.boxShadow = isActive ? 'inset 0 0 0 1px rgba(37,99,235,.22)' : 'none';
      });
    }

    var marker = getDetailMarkerBox();
    if (!(marker instanceof HTMLElement)) { return; }
    if (activeDetailId === '') {
      marker.style.display = 'none';
      marker.innerHTML = '';
      return;
    }

    var row = getItemRow(activeDetailId);
    if (!(row instanceof HTMLElement) || row.style.display === 'none') {
      marker.style.display = 'none';
      marker.innerHTML = '';
      return;
    }

    var markerRect = marker.getBoundingClientRect();
    var rowRect = row.getBoundingClientRect();
    var markerTop = (rowRect.top - markerRect.top) + (rowRect.height / 2);

    marker.style.display = 'block';
    marker.innerHTML = '<div style="position:absolute;top:' + String(markerTop) + 'px;left:-18px;transform:translateY(-50%);width:24px;height:24px;"><img src="' + esc(arrowIconUrl) + '" alt="" style="display:block;width:24px;height:24px;"></div>';
  }

  function closeActiveDetail() {
    activeDetailId = '';
    refreshActiveRowUi();
    renderEmptyDetailPanel();
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

    var html = '<div style="display:grid;gap:6px;">';
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
      var isAdminMessage = text(z.typ_autora || '') === 'admin';
      var bg = isAdminMessage ? '#fff1f1' : '#eef6ff';
      html += '<div class="ram_normal zaobleni_10" style="padding:6px 10px;background:' + bg + ';">';
      html += '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:2px;">';
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
    var actionBtnStyle = 'width:150px;justify-content:center;';
    var sendBtnStyle = 'width:308px;justify-content:center;';
    var html = '';

    if (canWrite) {
      html += '<textarea data-cb-hd-reply-text="1" rows="4" style="width:100%;padding:8px 10px;border:1px solid rgba(15,23,42,.18);border-radius:8px;background:#fff;resize:vertical;"></textarea>';
      html += '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:10px;">';
      if (!isAdmin && ownerId !== currentUserId) {
        html += '<button type="button" class="' + esc(btnBaseClass) + '" style="' + esc(actionBtnStyle) + '" data-cb-hd-follow="' + esc(id) + '">Mám stejný problém</button>';
      }
      if (isAdmin) {
        html += '<button type="button" class="' + esc(btnBaseClass) + '" style="' + esc(btnDangerStyle . actionBtnStyle) + '" data-cb-hd-quick-state="' + esc(id) + '" data-cb-hd-quick-value="zamítnuto">Zamítnout</button>';
        html += '<button type="button" class="' + esc(btnBaseClass) + '" style="' + esc(btnSuccessStyle . actionBtnStyle) + '" data-cb-hd-quick-state="' + esc(id) + '" data-cb-hd-quick-value="vyřešeno">Vyřešeno</button>';
      }
      html += '<button type="button" class="' + esc(btnPrimaryClass) + '" style="' + esc(sendBtnStyle) + '" data-cb-hd-send-reply="' + esc(id) + '">Odeslat odpověď</button>';
      html += '<button type="button" class="' + esc(btnBaseClass) + '" style="' + esc(actionBtnStyle) + '" data-cb-hd-close-detail="1">Zavřít</button>';
      html += '</div>';
    } else {
      html += '<div style="font-size:13px;color:#64748b;margin-top:10px;">Na tento požadavek už nelze odpovídat.</div>';
      html += '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:10px;">';
      if (!isAdmin && ownerId !== currentUserId) {
        html += '<button type="button" class="' + esc(btnBaseClass) + '" style="' + esc(actionBtnStyle) + '" data-cb-hd-follow="' + esc(id) + '">Mám stejný problém</button>';
      }
      if (isAdmin) {
        html += '<button type="button" class="' + esc(btnBaseClass) + '" style="' + esc(btnDangerStyle . actionBtnStyle) + '" data-cb-hd-quick-state="' + esc(id) + '" data-cb-hd-quick-value="zamítnuto">Zamítnout</button>';
        html += '<button type="button" class="' + esc(btnBaseClass) + '" style="' + esc(btnSuccessStyle . actionBtnStyle) + '" data-cb-hd-quick-state="' + esc(id) + '" data-cb-hd-quick-value="vyřešeno">Vyřešeno</button>';
      }
      html += '<button type="button" class="' + esc(btnBaseClass) + '" style="' + esc(actionBtnStyle) + '" data-cb-hd-close-detail="1">Zavřít</button>';
      html += '</div>';
    }

    return html;
  }

  function renderDetail(data, row) {
    var ticket = data && data.ticket ? data.ticket : {};
    var detailBox = getDetailPanelBox();
    if (!(detailBox instanceof HTMLElement)) { return; }

    var id = text(ticket.id_helpdesk || '');
    var html = '<div style="display:grid;gap:10px;">';
    html += '<div style="font-size:18px;font-weight:700;line-height:1.2;color:#0f172a;padding:2px 0 0 0;">#' + esc(id) + ' ' + esc(text(ticket.predmet || '')) + '</div>';
    html += renderAttachments(data && data.prilohy ? data.prilohy : []);
    html += renderMessages(data && data.zpravy ? data.zpravy : []);
    html += '<div>' + renderReplyActions(id, ticket, data || {}) + '</div>';
    html += '</div>';

    detailBox.innerHTML = html;
    activeDetailId = text(ticket.id_helpdesk || row.getAttribute('data-hd-item') || '');
    refreshActiveRowUi();
  }

  function loadDetail(id) {
    var row = getItemRow(id);
    if (!(row instanceof HTMLElement)) { return; }
    var detailBox = getDetailPanelBox();
    if (!(detailBox instanceof HTMLElement)) { return; }

    if (activeDetailId === String(id)) {
      closeActiveDetail();
      return;
    }

    closeActiveDetail();
    activeDetailId = String(id);
    refreshActiveRowUi();
    detailBox.innerHTML = '<div class="ram_normal zaobleni_10" style="padding:14px 16px;background:#fff;font-size:13px;color:#64748b;">Načítám detail...</div>';

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
          detailBox.innerHTML = '<div class="ram_normal zaobleni_10" style="padding:14px 16px;background:#fff;font-size:13px;color:#b91c1c;">Detail se nepodařilo načíst.</div>';
          activeDetailId = '';
          refreshActiveRowUi();
          return;
        }
        renderDetail(data, row);
      })
      .catch(function () {
        detailBox.innerHTML = '<div class="ram_normal zaobleni_10" style="padding:14px 16px;background:#fff;font-size:13px;color:#b91c1c;">Detail se nepodařilo načíst.</div>';
        activeDetailId = '';
        refreshActiveRowUi();
      });
  }

  function filterMatchesState(filterValue, rowState) {
    var state = text(rowState).trim();
    if (filterValue === 'all') { return true; }
    if (filterValue === 'new') { return state === 'nový'; }
    if (filterValue === 'active') { return state === 'řeší se'; }
    if (filterValue === 'resolved') { return state === 'vyřešeno'; }
    if (filterValue === 'rejected') { return state === 'zamítnuto'; }
    if (filterValue === 'closed') { return state === 'vyřešeno' || state === 'zamítnuto'; }
    return true;
  }

  function applyFilter() {
    var value = getCurrentFilterValue();
    var list = getListBox();
    if (!(list instanceof HTMLElement)) { return; }

    list.querySelectorAll('[data-hd-item]').forEach(function (row) {
      if (!(row instanceof HTMLElement)) { return; }
      var rowState = text(row.getAttribute('data-hd-stav') || '');
      row.style.display = filterMatchesState(value, rowState) ? '' : 'none';
    });

    var activeRow = getItemRow(activeDetailId);
    if (activeDetailId !== '' && activeRow instanceof HTMLElement && activeRow.style.display === 'none') {
      closeActiveDetail();
      return;
    }
    refreshActiveRowUi();
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

  function buildListRowHtml(detail) {
    var id = Number(detail && detail.id_helpdesk ? detail.id_helpdesk : 0);
    if (!Number.isFinite(id) || id <= 0) { return ''; }
    var predmet = esc(text(detail.predmet || ''));
    var stav = esc(text(detail.stav || 'nový'));
    var stavFiltr = esc(filterStatusValue(detail.stav || 'nový'));
    var typ = esc(text(detail.typ_label || detail.typ || ''));
    var visibility = esc(text(detail.visibility_label || ''));

    var html = '';
    html += '<article class="ram_normal zaobleni_10" data-hd-item="' + String(id) + '" data-hd-stav="' + stav + '" data-hd-filtr="' + stavFiltr + '" style="padding:10px;background:#fff;cursor:pointer;">';
    html += '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">';
    html += '<div style="display:grid;gap:4px;width:100%;">';
    html += '<strong style="font-size:14px;color:#0f172a;">#' + String(id) + ' ' + predmet + '</strong>';
    html += '<div style="font-size:12px;line-height:1.4;color:#64748b;">';
    html += 'Stav: <span data-hd-state-text="1">' + stav + '</span>';
    html += ' | Typ: ' + typ;
    if (visibility !== '') {
      html += ' | Určení: ' + visibility;
    }
    html += '</div>';
    html += '</div>';
    html += '</div>';
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
      var filterValue = minFilter.getAttribute('data-cb-hd-min-filter') || 'new';
      openCardMax();
      waitForExpanded(function () {
        setFilterValue(filterValue);
      }, 0);
      return;
    }

    var filterBlock = target.closest('[data-cb-hd-filter-block]');
    if (filterBlock instanceof HTMLElement) {
      setFilterValue(filterBlock.getAttribute('data-cb-hd-filter-block') || 'all');
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
          window.alert(result.data && result.data.err ? String(result.data.err) : 'Zápis sledování selhal.');
          return;
        }
        window.alert('Zapsáno.');
      });
      return;
    }

    var replyBtn = target.closest('[data-cb-hd-send-reply]');
    if (replyBtn instanceof HTMLElement) {
      var detailBox = getDetailPanelBox();
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
      return;
    }

    var row = target.closest('article[data-hd-item]');
    if (row instanceof HTMLElement) {
      loadDetail(row.getAttribute('data-hd-item') || '');
    }
  });

  refreshFilterBlocks();
  renderEmptyDetailPanel();
  refreshActiveRowUi();

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
<table style="width:100%;table-layout:fixed;border-collapse:collapse;">
  <tr>
    <td style="width:34%;vertical-align:top;padding:0 18px 0 0;">
      <section class="cb-hd-card-max" style="display:grid;gap:10px;height:100%;max-height:100%;overflow-y:auto;padding-right:4px;" data-cb-hd-filter-value="new">
        <div>
          <div style="font-size:18px;font-weight:700;line-height:1.2;color:#0f172a;"><?= $isAdmin ? 'Správa HelpDesku' : 'Moje hlášení a historie' ?></div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;">
          <button type="button" class="ram_normal zaobleni_10" data-cb-hd-filter-block="all" aria-pressed="false" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;background:#eff6ff;border:1px solid rgba(15,23,42,.10);text-align:left;cursor:pointer;">
            <span data-cb-hd-filter-label="1" style="font-size:12px;font-weight:500;color:#0f172a;line-height:1.2;">Vše</span>
            <strong style="font-size:18px;font-weight:700;line-height:1;color:#355c9a;"><?= cb_helpdesk_card_h((string)$stats['total']) ?></strong>
          </button>
          <button type="button" class="ram_normal zaobleni_10" data-cb-hd-filter-block="new" aria-pressed="true" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;background:#eef8ef;border:1px solid rgba(34,120,70,.28);text-align:left;cursor:pointer;">
            <span data-cb-hd-filter-label="1" style="font-size:13px;font-weight:700;color:#0f172a;line-height:1.2;">Nové</span>
            <strong style="font-size:18px;font-weight:700;line-height:1;color:#355c9a;"><?= cb_helpdesk_card_h((string)$stats['new']) ?></strong>
          </button>
          <button type="button" class="ram_normal zaobleni_10" data-cb-hd-filter-block="active" aria-pressed="false" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;background:#eff6ff;border:1px solid rgba(15,23,42,.10);text-align:left;cursor:pointer;">
            <span data-cb-hd-filter-label="1" style="font-size:12px;font-weight:500;color:#0f172a;line-height:1.2;">Řeší se</span>
            <strong style="font-size:18px;font-weight:700;line-height:1;color:#355c9a;"><?= cb_helpdesk_card_h((string)$stats['active']) ?></strong>
          </button>
          <button type="button" class="ram_normal zaobleni_10" data-cb-hd-filter-block="resolved" aria-pressed="false" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;background:#eff6ff;border:1px solid rgba(15,23,42,.10);text-align:left;cursor:pointer;">
            <span data-cb-hd-filter-label="1" style="font-size:12px;font-weight:500;color:#0f172a;line-height:1.2;">Uzavřeno</span>
            <strong style="font-size:18px;font-weight:700;line-height:1;color:#355c9a;"><?= cb_helpdesk_card_h((string)$stats['resolved']) ?></strong>
          </button>
          <button type="button" class="ram_normal zaobleni_10" data-cb-hd-filter-block="rejected" aria-pressed="false" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;background:#eff6ff;border:1px solid rgba(15,23,42,.10);text-align:left;cursor:pointer;">
            <span data-cb-hd-filter-label="1" style="font-size:12px;font-weight:500;color:#0f172a;line-height:1.2;">Zamítnuto</span>
            <strong style="font-size:18px;font-weight:700;line-height:1;color:#355c9a;"><?= cb_helpdesk_card_h((string)$stats['rejected']) ?></strong>
          </button>
        </div>

        <div data-cb-hd-list="1" style="display:grid;gap:8px;">
          <?php if ($items === []): ?>
            <div data-cb-hd-empty="1" class="ram_normal zaobleni_10" style="padding:12px;background:#fff;color:#64748b;">Zatím bez záznamu.</div>
          <?php else: ?>
            <?php foreach ($items as $item): ?>
              <article class="ram_normal zaobleni_10" data-hd-item="<?= cb_helpdesk_card_h((string)(int)$item['id_helpdesk']) ?>" data-hd-stav="<?= cb_helpdesk_card_h((string)$item['stav']) ?>" data-hd-filtr="<?= cb_helpdesk_card_h(in_array((string)$item['stav'], ['vyřešeno', 'zamítnuto'], true) ? 'uzavřené' : (trim((string)$item['stav']) === 'řeší se' ? 'řeší se' : 'nový')) ?>" style="padding:10px;background:#fff;cursor:pointer;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                  <div style="display:grid;gap:4px;width:100%;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                      <strong style="flex:1 1 auto;min-width:0;font-size:14px;color:#0f172a;"><?= cb_helpdesk_card_h('#' . (string)(int)$item['id_helpdesk'] . ' ' . (string)$item['predmet']) ?></strong>
                      <?php if ($isAdmin): ?>
                        <strong style="flex:0 0 auto;margin-left:auto;font-size:12px;color:<?= cb_helpdesk_card_h(cb_helpdesk_card_author_color($item)) ?>;white-space:nowrap;text-align:right;"><?= cb_helpdesk_card_h(cb_helpdesk_card_author($item)) ?></strong>
                      <?php endif; ?>
                    </div>
                    <div style="font-size:12px;line-height:1.4;color:#64748b;">
                      Stav: <span data-hd-state-text="1"><?= cb_helpdesk_card_h((string)$item['stav']) ?></span>
                      | Typ: <?= cb_helpdesk_card_h(cb_helpdesk_card_type_label((string)$item['typ'])) ?>
                      | Určení: <?= cb_helpdesk_card_h(cb_helpdesk_card_visibility_label((int)$item['verejny'])) ?>
                    </div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </td>
    <td style="width:28px;vertical-align:top;padding:0 8px 0 0;">
      <div style="position:relative;height:100%;min-height:100%;">
        <div data-cb-hd-detail-marker="1" style="display:none;position:relative;height:100%;min-height:100%;"></div>
      </div>
    </td>
    <td style="width:auto;vertical-align:top;">
      <div data-cb-hd-detail-panel="1" style="display:grid;gap:12px;align-content:start;"></div>
    </td>
  </tr>
</table>
<?php
$card_max_html = (string)ob_get_clean();
