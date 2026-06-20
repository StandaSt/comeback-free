<?php
// K20
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
            return 'Vsichni mohou reagovat';
        }
        if ($value === 2) {
            return 'Vsichni mohou cist';
        }

        return 'Pouze pro admina';
    }
}

if (!function_exists('cb_helpdesk_card_type_label')) {
    function cb_helpdesk_card_type_label(string $value): string
    {
        if ($value === 'chyba') {
            return 'Chyba systemu';
        }
        if ($value === 'dotaz') {
            return 'Dotaz';
        }
        if ($value === 'navrh') {
            return 'Namet na vylepseni';
        }

        return $value;
    }
}

$card_min_html = '';
$card_max_html = '';

if (empty($_SESSION['login_ok'])) {
    $card_min_html = '<p class="card_text odstup_vnejsi_0">Nutne prihlaseni.</p>';
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
            SUM(stav IN ('novy', 'resi_se')) AS open_cnt,
            SUM(stav IN ('vyreseno', 'zamitnuto')) AS closed_cnt,
            SUM(stav = 'novy') AS new_cnt,
            SUM(stav = 'resi_se') AS active_cnt
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
        ORDER BY FIELD(h.stav, \'novy\', \'resi_se\', \'vyreseno\', \'zamitnuto\'), h.upraveno DESC, h.vytvoreno DESC
        LIMIT 120
    ');
} else {
    $stmtStats = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(stav IN ('novy', 'resi_se')) AS open_cnt,
            SUM(stav IN ('vyreseno', 'zamitnuto')) AS closed_cnt
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
<div class="cb-hd-card-min" style="display:grid;gap:10px;">
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;">
    <div class="ram_normal zaobleni_10" style="padding:8px 10px;background:#f8fbff;">
      <div style="font-size:11px;color:#64748b;line-height:1.2;"><?= $isAdmin ? 'Celkem' : 'Moje hlaseni' ?></div>
      <div style="font-size:20px;font-weight:700;line-height:1.15;color:#0f172a;"><?= cb_helpdesk_card_h((string)$stats['total']) ?></div>
    </div>
    <div class="ram_normal zaobleni_10" style="padding:8px 10px;background:#fffaf0;">
      <div style="font-size:11px;color:#64748b;line-height:1.2;"><?= $isAdmin ? 'Otevrene' : 'Otevrene' ?></div>
      <div style="font-size:20px;font-weight:700;line-height:1.15;color:#9a6700;"><?= cb_helpdesk_card_h((string)$stats['open']) ?></div>
    </div>
    <div class="ram_normal zaobleni_10" style="padding:8px 10px;background:#f3faf5;">
      <div style="font-size:11px;color:#64748b;line-height:1.2;"><?= $isAdmin ? 'Uzavrene' : 'Uzavrene' ?></div>
      <div style="font-size:20px;font-weight:700;line-height:1.15;color:#166534;"><?= cb_helpdesk_card_h((string)$stats['closed']) ?></div>
    </div>
  </div>

  <?php if ($isAdmin): ?>
    <div style="font-size:12px;color:#475569;line-height:1.35;">
      Nove: <strong><?= cb_helpdesk_card_h((string)$stats['new']) ?></strong>
      &nbsp;|&nbsp;
      Resi se: <strong><?= cb_helpdesk_card_h((string)$stats['active']) ?></strong>
    </div>
  <?php else: ?>
    <div style="font-size:12px;color:#475569;line-height:1.35;">
      Karta slouzi jako prehled historie, odpovedi admina a dostupnych reakci.
    </div>
  <?php endif; ?>

  <div style="display:grid;gap:6px;">
    <?php if ($recentItems === []): ?>
      <div style="font-size:13px;color:#64748b;">Zatim bez zaznamu.</div>
    <?php else: ?>
      <?php foreach ($recentItems as $item): ?>
        <button
          type="button"
          data-cb-hd-open-detail="<?= cb_helpdesk_card_h((string)(int)$item['id_helpdesk']) ?>"
          class="ram_normal zaobleni_10"
          style="display:block;width:100%;padding:8px 10px;text-align:left;background:#fff;border:1px solid rgba(15,23,42,.10);cursor:pointer;"
        >
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
            <strong style="font-size:13px;color:#0f172a;"><?= cb_helpdesk_card_h('#' . (string)(int)$item['id_helpdesk'] . ' ' . (string)$item['predmet']) ?></strong>
            <span style="font-size:11px;color:#64748b;white-space:nowrap;"><?= cb_helpdesk_card_h((string)$item['stav']) ?></span>
          </div>
        </button>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div style="display:flex;justify-content:flex-end;">
    <button type="button" class="btn" data-cb-hd-card-open="1">Otevrit HelpDesk</button>
  </div>
</div>
<script>
(function () {
  var script = document.currentScript;
  var root = script ? script.closest('.card_shell[data-card-id="20"]') : document.querySelector('.card_shell[data-card-id="20"]');
  if (!(root instanceof HTMLElement)) { return; }
  if (root.getAttribute('data-cb-hd-init') === '1') { return; }
  root.setAttribute('data-cb-hd-init', '1');

  var apiUrl = <?= json_encode($helpdeskApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

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
    if (num === 1) { return 'Vsichni mohou reagovat'; }
    if (num === 2) { return 'Vsichni mohou cist'; }
    return 'Pouze pro admina';
  }

  function typeText(value) {
    var textValue = text(value);
    if (textValue === 'chyba') { return 'Chyba systemu'; }
    if (textValue === 'dotaz') { return 'Dotaz'; }
    if (textValue === 'navrh') { return 'Namet na vylepseni'; }
    return textValue;
  }

  function getExpandedBox() {
    return root.querySelector('[data-card-expanded]');
  }

  function getListBox() {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return null; }
    return expanded.querySelector('[data-cb-hd-list]');
  }

  function getDetailBox() {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return null; }
    return expanded.querySelector('[data-cb-hd-detail]');
  }

  function getItemRow(id) {
    var list = getListBox();
    if (!(list instanceof HTMLElement)) { return null; }
    return list.querySelector('[data-hd-item="' + String(id).replace(/"/g, '') + '"]');
  }

  function openCardMax() {
    var toggle = root.querySelector('[data-card-toggle="1"]');
    var expanded = getExpandedBox();
    if (toggle instanceof HTMLButtonElement && expanded instanceof HTMLElement && expanded.classList.contains('is-hidden')) {
      toggle.click();
    }
  }

  function waitForExpanded(callback, attempt) {
    var tries = Number(attempt || 0);
    var detailBox = getDetailBox();
    if (detailBox instanceof HTMLElement) {
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
    html += '<div style="font-size:12px;font-weight:700;color:#334155;">Prilohy</div>';
    for (var i = 0; i < prilohy.length; i++) {
      var p = prilohy[i] || {};
      var name = text(p.puvodni_nazev || p.ulozeny_nazev || ('Priloha ' + String(i + 1)));
      var path = text(p.cesta || '');
      if (path === '') { continue; }
      html += '<a href="' + esc(path) + '" target="_blank" rel="noopener" style="color:#0f3f91;text-decoration:underline;">' + esc(name) + '</a>';
    }
    html += '</div>';
    return html;
  }

  function renderMessages(zpravy) {
    if (!Array.isArray(zpravy) || !zpravy.length) {
      return '<div style="font-size:13px;color:#64748b;">Zatim bez reakci.</div>';
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

    if (isAdmin) {
      html += '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;">';
      html += '<select data-cb-hd-state-input="1" style="min-height:34px;padding:6px 10px;border:1px solid rgba(15,23,42,.18);border-radius:8px;background:#fff;">';
      html += '<option value="novy">novy</option>';
      html += '<option value="resi_se">resi_se</option>';
      html += '<option value="vyreseno">vyreseno</option>';
      html += '<option value="zamitnuto">zamitnuto</option>';
      html += '</select>';
      html += '<button type="button" class="btn" data-cb-hd-change-state="' + esc(id) + '">Zmenit stav</button>';
      html += '</div>';
    }

    if (canWrite) {
      html += '<textarea data-cb-hd-reply-text="1" rows="4" style="width:100%;padding:8px 10px;border:1px solid rgba(15,23,42,.18);border-radius:8px;background:#fff;resize:vertical;"></textarea>';
      html += '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:10px;">';
      if (!isAdmin && ownerId !== currentUserId) {
        html += '<button type="button" class="btn" data-cb-hd-follow="' + esc(id) + '">Mam stejny problem</button>';
      }
      html += '<button type="button" class="btn" data-cb-hd-send-reply="' + esc(id) + '">Odeslat odpoved</button>';
      html += '<button type="button" class="btn" data-cb-hd-close-detail="1">Zavrit</button>';
      html += '</div>';
    } else {
      html += '<div style="font-size:13px;color:#64748b;margin-top:10px;">Na tento pozadavek uz nelze odpovidat.</div>';
      html += '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:10px;">';
      if (!isAdmin && ownerId !== currentUserId) {
        html += '<button type="button" class="btn" data-cb-hd-follow="' + esc(id) + '">Mam stejny problem</button>';
      }
      html += '<button type="button" class="btn" data-cb-hd-close-detail="1">Zavrit</button>';
      html += '</div>';
    }

    return html;
  }

  function renderDetail(data) {
    var ticket = data && data.ticket ? data.ticket : {};
    var detailBox = getDetailBox();
    if (!(detailBox instanceof HTMLElement)) { return; }

    var id = text(ticket.id_helpdesk || '');
    var html = '<div class="ram_normal zaobleni_10" style="padding:12px;background:#f8fafc;">';
    html += '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">';
    html += '<div>';
    html += '<h4 style="margin:0 0 6px 0;font-size:18px;line-height:1.2;color:#0f172a;">#' + esc(id) + ' ' + esc(text(ticket.predmet || '')) + '</h4>';
    html += '<div style="font-size:12px;line-height:1.4;color:#475569;">';
    html += 'Stav: <strong>' + esc(text(ticket.stav || '')) + '</strong>';
    html += ' | Typ: <strong>' + esc(typeText(ticket.typ || '')) + '</strong>';
    html += ' | Urceni: <strong>' + esc(visibilityText(ticket.verejny || 0)) + '</strong>';
    html += '</div>';
    html += '</div>';
    html += '<button type="button" class="btn" data-cb-hd-close-detail="1">Zavrit</button>';
    html += '</div>';
    html += '<div style="margin-top:12px;white-space:pre-wrap;font-size:13px;line-height:1.5;color:#1f2937;">' + esc(text(ticket.popis || '')) + '</div>';
    html += renderAttachments(data && data.prilohy ? data.prilohy : []);
    html += '<div style="margin-top:12px;">' + renderMessages(data && data.zpravy ? data.zpravy : []) + '</div>';
    html += '<div style="margin-top:12px;">' + renderReplyActions(id, ticket, data || {}) + '</div>';
    html += '</div>';

    detailBox.innerHTML = html;
    detailBox.style.display = 'block';

    var stateInput = detailBox.querySelector('[data-cb-hd-state-input="1"]');
    if (stateInput instanceof HTMLSelectElement) {
      stateInput.value = text(ticket.stav || 'novy');
    }
  }

  function loadDetail(id) {
    var detailBox = getDetailBox();
    if (!(detailBox instanceof HTMLElement)) { return; }
    detailBox.style.display = 'block';
    detailBox.innerHTML = '<div style="font-size:13px;color:#64748b;">Nacitam detail...</div>';

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
          detailBox.innerHTML = '<div style="font-size:13px;color:#b91c1c;">Detail se nepodarilo nacist.</div>';
          return;
        }
        renderDetail(data);
      })
      .catch(function () {
        detailBox.innerHTML = '<div style="font-size:13px;color:#b91c1c;">Detail se nepodarilo nacist.</div>';
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
      var rowState = text(row.getAttribute('data-hd-stav') || '');
      row.style.display = (value === '' || value === rowState) ? '' : 'none';
    });
  }

  function updateRowState(id, state) {
    var row = getItemRow(id);
    if (!(row instanceof HTMLElement)) { return; }
    row.setAttribute('data-hd-stav', text(state));
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
    var stav = esc(text(detail.stav || 'novy'));
    var typ = esc(text(detail.typ_label || detail.typ || ''));
    var visibility = esc(text(detail.visibility_label || ''));
    var replies = Number(detail && detail.pocet_zprav ? detail.pocet_zprav : 1);
    if (!Number.isFinite(replies) || replies < 0) { replies = 0; }

    var html = '';
    html += '<article class="ram_normal zaobleni_10" data-hd-item="' + String(id) + '" data-hd-stav="' + stav + '" style="padding:10px;background:#fff;">';
    html += '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">';
    html += '<div style="display:grid;gap:4px;">';
    html += '<strong style="font-size:14px;color:#0f172a;">#' + String(id) + ' ' + predmet + '</strong>';
    html += '<div style="font-size:12px;line-height:1.4;color:#64748b;">';
    html += 'Stav: <span data-hd-state-text="1">' + stav + '</span>';
    html += ' | Typ: ' + typ;
    if (visibility !== '') {
      html += ' | Urceni: ' + visibility;
    }
    html += ' | Zpravy: <span data-hd-reply-count="1" data-count="' + String(replies) + '">' + String(replies) + '</span>';
    html += '</div>';
    html += '</div>';
    html += '<button type="button" class="btn" data-cb-hd-open-detail="' + String(id) + '">Otevrit</button>';
    html += '</div>';
    html += '</article>';
    return html;
  }

  root.addEventListener('click', function (e) {
    var target = e.target;
    if (!(target instanceof Element)) { return; }

    if (target.closest('[data-cb-hd-card-open="1"]')) {
      openCardMax();
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
      var detailBox = getDetailBox();
      if (detailBox instanceof HTMLElement) {
        detailBox.style.display = 'none';
        detailBox.innerHTML = '';
      }
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
        window.alert('Zapsano.');
      });
      return;
    }

    var replyBtn = target.closest('[data-cb-hd-send-reply]');
    if (replyBtn instanceof HTMLElement) {
      var detailBox = getDetailBox();
      if (!(detailBox instanceof HTMLElement)) { return; }
      var replyInput = detailBox.querySelector('[data-cb-hd-reply-text="1"]');
      if (!(replyInput instanceof HTMLTextAreaElement)) { return; }
      var replyText = text(replyInput.value || '').trim();
      if (replyText === '') {
        window.alert('Dopln zpravu.');
        return;
      }
      var replyId = replyBtn.getAttribute('data-cb-hd-send-reply') || '';
      postJson(apiUrl + '?helpdesk_action=zprava_pridat', {
        id_helpdesk: replyId,
        zprava: replyText
      }).then(function (result) {
        if (!result.ok || !result.data || result.data.ok !== true) {
          window.alert(result.data && result.data.err ? String(result.data.err) : 'Odeslani odpovedi selhalo.');
          return;
        }
        bumpRowReplyCount(replyId);
        loadDetail(replyId);
      });
      return;
    }

    var stateBtn = target.closest('[data-cb-hd-change-state]');
    if (stateBtn instanceof HTMLElement) {
      var detailBox = getDetailBox();
      if (!(detailBox instanceof HTMLElement)) { return; }
      var stateInput = detailBox.querySelector('[data-cb-hd-state-input="1"]');
      if (!(stateInput instanceof HTMLSelectElement)) { return; }
      var stateId = stateBtn.getAttribute('data-cb-hd-change-state') || '';
      postJson(apiUrl + '?helpdesk_action=stav_zmenit', {
        id_helpdesk: stateId,
        stav: stateInput.value
      }).then(function (result) {
        if (!result.ok || !result.data || result.data.ok !== true) {
          window.alert(result.data && result.data.err ? String(result.data.err) : 'Zmena stavu selhala.');
          return;
        }
        updateRowState(stateId, stateInput.value);
        loadDetail(stateId);
      });
    }
  });

  root.addEventListener('change', function (e) {
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
      stav: 'novy',
      typ: detail.typ,
      typ_label: detail.typ === 'chyba' ? 'Chyba systemu' : (detail.typ === 'dotaz' ? 'Dotaz' : 'Namet na vylepseni'),
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
      <div style="font-size:18px;font-weight:700;line-height:1.2;color:#0f172a;"><?= $isAdmin ? 'Sprava HelpDesku' : 'Moje hlaseni a historie' ?></div>
      <div style="font-size:13px;line-height:1.4;color:#475569;">
        <?php if ($isAdmin): ?>
          Admin vidi vsechny pozadavky a muze menit stav.
        <?php else: ?>
          Tady uvidis svoje hlaseni, reakce admina i dostupnou historii.
        <?php endif; ?>
      </div>
    </div>

    <?php if ($isAdmin): ?>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <label for="cb-hd-filter-20" style="font-size:13px;color:#334155;">Stav</label>
        <select id="cb-hd-filter-20" data-cb-hd-filter="1" style="min-height:34px;padding:6px 10px;border:1px solid rgba(15,23,42,.18);border-radius:8px;background:#fff;">
          <option value="">Vse</option>
          <option value="novy">novy</option>
          <option value="resi_se">resi_se</option>
          <option value="vyreseno">vyreseno</option>
          <option value="zamitnuto">zamitnuto</option>
        </select>
      </div>
    <?php endif; ?>
  </div>

  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;">
    <div class="ram_normal zaobleni_10" style="padding:10px;background:#f8fbff;">
      <div style="font-size:11px;color:#64748b;line-height:1.2;"><?= $isAdmin ? 'Celkem pozadavku' : 'Moje hlaseni' ?></div>
      <div style="font-size:22px;font-weight:700;line-height:1.15;color:#0f172a;"><?= cb_helpdesk_card_h((string)$stats['total']) ?></div>
    </div>
    <div class="ram_normal zaobleni_10" style="padding:10px;background:#fffaf0;">
      <div style="font-size:11px;color:#64748b;line-height:1.2;"><?= $isAdmin ? 'Otevrene' : 'Otevrene' ?></div>
      <div style="font-size:22px;font-weight:700;line-height:1.15;color:#9a6700;"><?= cb_helpdesk_card_h((string)$stats['open']) ?></div>
    </div>
    <div class="ram_normal zaobleni_10" style="padding:10px;background:#f3faf5;">
      <div style="font-size:11px;color:#64748b;line-height:1.2;"><?= $isAdmin ? 'Uzavrene' : 'Uzavrene' ?></div>
      <div style="font-size:22px;font-weight:700;line-height:1.15;color:#166534;"><?= cb_helpdesk_card_h((string)$stats['closed']) ?></div>
    </div>
  </div>

  <div data-cb-hd-list="1" style="display:grid;gap:8px;">
    <?php if ($items === []): ?>
      <div data-cb-hd-empty="1" class="ram_normal zaobleni_10" style="padding:12px;background:#fff;color:#64748b;">Zatim bez zaznamu.</div>
    <?php else: ?>
      <?php foreach ($items as $item): ?>
        <article class="ram_normal zaobleni_10" data-hd-item="<?= cb_helpdesk_card_h((string)(int)$item['id_helpdesk']) ?>" data-hd-stav="<?= cb_helpdesk_card_h((string)$item['stav']) ?>" style="padding:10px;background:#fff;">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div style="display:grid;gap:4px;">
              <strong style="font-size:14px;color:#0f172a;"><?= cb_helpdesk_card_h('#' . (string)(int)$item['id_helpdesk'] . ' ' . (string)$item['predmet']) ?></strong>
              <div style="font-size:12px;line-height:1.4;color:#64748b;">
                Stav: <span data-hd-state-text="1"><?= cb_helpdesk_card_h((string)$item['stav']) ?></span>
                | Typ: <?= cb_helpdesk_card_h(cb_helpdesk_card_type_label((string)$item['typ'])) ?>
                | Urceni: <?= cb_helpdesk_card_h(cb_helpdesk_card_visibility_label((int)$item['verejny'])) ?>
                | Autor: <?= cb_helpdesk_card_h(cb_helpdesk_card_author($item)) ?>
                | Zpravy: <span data-hd-reply-count="1" data-count="<?= cb_helpdesk_card_h((string)(int)$item['pocet_zprav']) ?>"><?= cb_helpdesk_card_h((string)(int)$item['pocet_zprav']) ?></span>
              </div>
            </div>
            <button type="button" class="btn" data-cb-hd-open-detail="<?= cb_helpdesk_card_h((string)(int)$item['id_helpdesk']) ?>">Otevrit</button>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div data-cb-hd-detail="1" style="display:none;"></div>
</section>
<?php
$card_max_html = (string)ob_get_clean();
