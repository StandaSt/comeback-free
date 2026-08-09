(function () {
  'use strict';

  function initHelpdesk(root) {
    var scope = root && root.querySelector ? root : document;
    var container = scope.querySelector('[data-cb-helpdesk-module="1"]');
    if (!(container instanceof HTMLElement)) { return; }

    if (typeof window.__CB_HELPDESK_CLEANUP__ === 'function') {
      window.__CB_HELPDESK_CLEANUP__();
    }

    var apiUrl = container.getAttribute('data-cb-hd-api-url') || window.CB_ENDPOINT || 'index.php';
    var arrowIconUrl = container.getAttribute('data-cb-hd-arrow-url') || '';
    var isAdmin = container.getAttribute('data-cb-hd-is-admin') === '1';
    var authorId = Number(container.getAttribute('data-cb-hd-author-id') || '0');

  function apiActionUrl(action) {
    var moduleName = window.CB_HELPDESK_SOURCE_MODULE || window.CB_ACTIVE_MAIN_MODULE || 'provoz';
    var sep = apiUrl.indexOf('?') === -1 ? '?' : '&';
    return apiUrl + sep + 'helpdesk_action=' + encodeURIComponent(action) + '&cb_helpdesk_module=' + encodeURIComponent(String(moduleName));
  }
  var activeDetailId = '';
  var pollTimerId = 0;
  var scrollDetailToBottomOnRender = false;

  var btnBaseClass = 'helpdesk_action_btn';
  var btnPrimaryClass = btnBaseClass + ' helpdesk_action_btn_primary';

  function text(v) {
    if (v === null || v === undefined) { return ''; }
    return String(v);
  }

  function esc(v) {
    return text(v).replace(/[&<>'"]/g, function (ch) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[ch];
    });
  }

  function hasNewReplyValue(value) {
    return Number(value || 0) === 1 ? '1' : '0';
  }

  function buildBellHtml(hasNewReply) {
    if (hasNewReplyValue(hasNewReply) === '1') {
      return '<span class="helpdesk_ticket_bell helpdesk_state_unread" data-hd-bell="1" title="Nová reakce" aria-label="Nová reakce">!</span>';
    }

    return '<span class="helpdesk_ticket_bell" data-hd-bell="0" title="Bez nové reakce" aria-label="Bez nové reakce">!</span>';
  }

  function formatMessageDateTime(raw) {
    var value = text(raw).trim();
    if (value === '') { return ''; }

    var match = value.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    if (!match) { return value; }

    var year = Number(match[1]);
    var month = Number(match[2]);
    var day = Number(match[3]);
    var hour = match[4];
    var minute = match[5];
    if (!Number.isFinite(year) || !Number.isFinite(month) || !Number.isFinite(day)) {
      return value;
    }

    var now = new Date();
    var todayKey = (new Date(now.getFullYear(), now.getMonth(), now.getDate())).getTime();
    var messageKey = (new Date(year, month - 1, day)).getTime();
    var yesterdayKey = todayKey - 86400000;
    var timeText = hour + ':' + minute;

    if (messageKey === todayKey) {
      return 'Dnes ' + timeText;
    }
    if (messageKey === yesterdayKey) {
      return 'Včera ' + timeText;
    }

    return String(day) + '. ' + String(month) + '. ' + String(year) + ' ' + timeText;
  }

  function messageCountValue(value) {
    var count = Number(value || 0);
    if (!Number.isFinite(count) || count < 1) {
      count = 1;
    }
    return String(Math.trunc(count));
  }

  function authorColorByRole(roleId) {
    var role = Number(roleId || 0);
    if (role > 0 && role <= 1) { return '#c77b7b'; }
    if (role === 2 || role === 3) { return '#6f8fcf'; }
    if (role >= 4 && role <= 7) { return '#5f8f63'; }
    return '#404040';
  }

  function filterStatusValue(state) {
    var value = text(state).trim();
    if (value === 'vyřešeno' || value === 'uzavřené') {
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
    var roots = document.querySelectorAll('.helpdesk_module_content');
    for (var i = 0; i < roots.length; i++) {
      var item = roots[i];
      if (!(item instanceof HTMLElement)) {
        continue;
      }
      return item;
    }
    return null;
  }

  function getExpandedBox() {
    return getRoot();
  }

  function getListBox() {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return null; }
    return expanded.querySelector('[data-cb-hd-list]');
  }

  function isHelpdeskVisible() {
    var expanded = getExpandedBox();
    return expanded instanceof HTMLElement && !expanded.classList.contains('helpdesk_state_hidden');
  }

  function getDetailPanelBox() {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return null; }
    var box = expanded.querySelector('[data-cb-hd-detail-panel]');
    return box instanceof HTMLElement ? box : null;
  }

  function getDetailScrollBox() {
    var panel = getDetailPanelBox();
    if (!(panel instanceof HTMLElement)) { return null; }
    var box = panel.parentElement;
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

  function updateRowHasNewReply(id, hasNewReply) {
    var row = getItemRow(id);
    if (!(row instanceof HTMLElement)) { return; }

    row.setAttribute('data-hd-has-new-reply', hasNewReplyValue(hasNewReply));

    var bellWrap = row.querySelector('[data-hd-bell-wrap="1"]');
    if (bellWrap instanceof HTMLElement) {
      bellWrap.innerHTML = buildBellHtml(hasNewReply);
    }
  }

  function normalizeFilterValue(value) {
    var normalized = text(value).trim().toLowerCase();
    if (normalized === '' || normalized === 'all' || normalized === 'vše') { return 'all'; }
    if (normalized === 'nový' || normalized === 'novy' || normalized === 'new') { return 'new'; }
    if (normalized === 'řeší se' || normalized === 'resi se' || normalized === 'active') { return 'active'; }
    if (normalized === 'vyřešeno' || normalized === 'vyreseno' || normalized === 'resolved') { return 'resolved'; }
    if (normalized === 'uzavřené' || normalized === 'uzavrene' || normalized === 'closed') { return 'closed'; }
    if (normalized === 'mine' || normalized === 'moje') { return 'mine'; }
    if (normalized === 'watched' || normalized === 'sledovane' || normalized === 'sledované') { return 'watched'; }
    if (normalized === 'admin') { return 'admin'; }
    return 'all';
  }

  function getCurrentFilterValue() {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return 'all'; }
    return normalizeFilterValue(expanded.getAttribute('data-cb-hd-filter-value') || 'all');
  }

  function getUnreadOnlyValue() {
    var expanded = getExpandedBox();
    return expanded instanceof HTMLElement && expanded.getAttribute('data-cb-hd-unread-only') === '1';
  }

  function setFilterValue(value, unreadOnly) {
    var expanded = getExpandedBox();
    if (!(expanded instanceof HTMLElement)) { return; }
    expanded.setAttribute('data-cb-hd-filter-value', normalizeFilterValue(value));
    if (arguments.length > 1) {
      expanded.setAttribute('data-cb-hd-unread-only', unreadOnly ? '1' : '0');
    }
    applyFilter();
  }

  function openUnreadFilter(value) {
    waitForExpanded(function () {
      setFilterValue(value, true);
      pollTicketStates();
    }, 0);
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
    box.innerHTML = '<div class="helpdesk_detail_notice ram_normal zaobleni_10">'
      + '<div class="helpdesk_detail_notice_title">Vyber tiket vlevo</div>'
      + '<div class="helpdesk_detail_notice_text">Tady se otevře pracovní panel vybraného tiketu.</div>'
      + '</div>';
  }

  function refreshActiveRowUi() {
    var list = getListBox();
    if (list instanceof HTMLElement) {
      list.querySelectorAll('[data-hd-item]').forEach(function (row) {
        if (!(row instanceof HTMLElement)) { return; }
        var isActive = activeDetailId !== '' && row.getAttribute('data-hd-item') === activeDetailId;
        var rowState = normalizeFilterValue(row.getAttribute('data-hd-stav') || '');
        row.classList.toggle('helpdesk_state_active', isActive);
        row.classList.toggle('helpdesk_state_read', row.getAttribute('data-hd-has-new-reply') !== '1');
        row.classList.toggle('helpdesk_state_status_active', rowState === 'active');
        row.classList.toggle('helpdesk_state_status_resolved', rowState === 'resolved');
      });
    }

    var marker = getDetailMarkerBox();
    if (!(marker instanceof HTMLElement)) { return; }
    if (activeDetailId === '') {
      marker.classList.remove('helpdesk_state_visible');
      marker.innerHTML = '';
      return;
    }

    var row = getItemRow(activeDetailId);
    if (!(row instanceof HTMLElement) || row.classList.contains('helpdesk_state_hidden')) {
      marker.classList.remove('helpdesk_state_visible');
      marker.innerHTML = '';
      return;
    }

    marker.classList.add('helpdesk_state_visible');
    marker.innerHTML = '<div class="helpdesk_marker_arrow"><img class="helpdesk_marker_arrow_img" src="' + esc(arrowIconUrl) + '" alt=""></div>';
  }

  function closeActiveDetail() {
    activeDetailId = '';
    refreshActiveRowUi();
    renderEmptyDetailPanel();
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

  function scrollDetailToBottom() {
    var box = getDetailScrollBox();
    if (!(box instanceof HTMLElement)) { return; }
    box.scrollTop = box.scrollHeight;
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

    var html = '<div class="helpdesk_attachments">';
    html += '<span class="helpdesk_attachments_label">Přílohy:</span>';
    for (var i = 0; i < prilohy.length; i++) {
      var p = prilohy[i] || {};
      var name = text(p.puvodni_nazev || p.ulozeny_nazev || ('Příloha ' + String(i + 1)));
      var path = text(p.cesta || '');
      if (path === '') { continue; }
      html += '<a class="helpdesk_attachment_link" href="' + esc(path) + '" target="_blank" rel="noopener">' + esc(name) + '</a>';
    }
    html += '</div>';
    return html;
  }

  function renderMessages(zpravy) {
    if (!Array.isArray(zpravy) || !zpravy.length) {
      return '<div class="helpdesk_messages_empty">Zatím bez reakcí.</div>';
    }

    var html = '<div class="helpdesk_messages">';
    var detailBox = getDetailPanelBox();
    var ownerId = 0;
    var currentUserId = 0;
    if (detailBox instanceof HTMLElement) {
      ownerId = Number(detailBox.getAttribute('data-cb-hd-owner-id') || '0');
      currentUserId = Number(detailBox.getAttribute('data-cb-hd-current-user-id') || '0');
    }
    for (var i = 0; i < zpravy.length; i++) {
      var z = zpravy[i] || {};
      var first = text(z.jmeno || '');
      var last = text(z.prijmeni || '');
      var author = (first + ' ' + last).trim();
      var isAdminMessage = text(z.typ_autora || '') === 'admin';
      if (isAdminMessage) {
        author = 'Admin';
      }
      if (author === '') {
        author = 'ID ' + text(z.id_user || '0');
      }
      var messageUserId = Number(z.id_user || 0);
      var messageClass = 'helpdesk_message ram_normal zaobleni_10';
      if (isAdminMessage) {
        messageClass += ' helpdesk_state_admin';
      } else if (!isAdmin && messageUserId === currentUserId) {
        messageClass += ' helpdesk_state_current_user';
      } else if (isAdmin && messageUserId === ownerId) {
        messageClass += ' helpdesk_state_admin_owner';
      }
      html += '<div class="' + esc(messageClass) + '">';
      html += '<div class="helpdesk_message_head">';
      html += '<strong class="helpdesk_message_author">' + esc(author) + '</strong>';
      html += '<span class="helpdesk_message_time">' + esc(formatMessageDateTime(z.vytvoreno || '')) + '</span>';
      html += '</div>';
      html += '<div class="helpdesk_message_text">' + esc(text(z.zprava || '')) + '</div>';
      html += '</div>';
    }
    html += '</div>';
    return html;
  }

  function renderReplyActions(id, ticket, data) {
    var canWrite = Number(data && data.can_write ? data.can_write : 0) === 1;
    var currentUserId = Number(data && data.current_user_id ? data.current_user_id : 0);
    var ownerId = Number(ticket && ticket.id_user_zalozil ? ticket.id_user_zalozil : 0);
    var isResolved = filterStatusValue(ticket && ticket.stav ? ticket.stav : '') === 'uzavřené';
    var html = '';

    if (isResolved) {
      html += '<div class="helpdesk_detail_actions">';
      html += '<button type="button" class="' + esc(btnBaseClass) + ' helpdesk_action_btn_small" data-cb-hd-close-detail="1">Zpět</button>';
      html += '</div>';
    } else if (canWrite) {
      html += '<textarea class="helpdesk_reply_textarea" data-cb-hd-reply-text="1" rows="4"></textarea>';
      html += '<div class="helpdesk_detail_actions">';
      if (!isAdmin && ownerId !== currentUserId) {
        html += '<button type="button" class="' + esc(btnBaseClass) + ' helpdesk_action_btn_small" data-cb-hd-follow="' + esc(id) + '">Mám stejný problém</button>';
      }
      if (isAdmin) {
        html += '<button type="button" class="' + esc(btnBaseClass) + ' helpdesk_action_btn_wide" data-cb-hd-send-reply="' + esc(id) + '" data-cb-hd-resolve="1">Odeslat - tiket vyřešen</button>';
      }
      html += '<button type="button" class="' + esc(btnPrimaryClass) + ' helpdesk_action_btn_wide" data-cb-hd-send-reply="' + esc(id) + '">Odeslat odpověď</button>';
      html += '<button type="button" class="' + esc(btnBaseClass) + ' helpdesk_action_btn_small" data-cb-hd-close-detail="1">Zpět</button>';
      html += '</div>';
    } else {
      html += '<div class="helpdesk_detail_note">Na tento požadavek už nelze odpovídat.</div>';
      html += '<div class="helpdesk_detail_actions">';
      if (!isAdmin && ownerId !== currentUserId) {
        html += '<button type="button" class="' + esc(btnBaseClass) + ' helpdesk_action_btn_small" data-cb-hd-follow="' + esc(id) + '">Mám stejný problém</button>';
      }
      html += '<button type="button" class="' + esc(btnBaseClass) + ' helpdesk_action_btn_small" data-cb-hd-close-detail="1">Zpět</button>';
      html += '</div>';
    }

    return html;
  }

  function renderDetail(data, row) {
    var ticket = data && data.ticket ? data.ticket : {};
    var detailBox = getDetailPanelBox();
    if (!(detailBox instanceof HTMLElement)) { return; }

    var id = text(ticket.id_helpdesk || '');
    detailBox.setAttribute('data-cb-hd-owner-id', text(ticket.id_user_zalozil || '0'));
    detailBox.setAttribute('data-cb-hd-current-user-id', text(data && data.current_user_id ? data.current_user_id : 0));
    var html = '<div class="helpdesk_detail_stack">';
    html += '<div class="helpdesk_detail_title">#' + esc(id) + ' ' + esc(text(ticket.predmet || '')) + '</div>';
    html += renderAttachments(data && data.prilohy ? data.prilohy : []);
    html += renderMessages(data && data.zpravy ? data.zpravy : []);
    html += '<div>' + renderReplyActions(id, ticket, data || {}) + '</div>';
    html += '</div>';

    detailBox.innerHTML = html;
    activeDetailId = text(ticket.id_helpdesk || row.getAttribute('data-hd-item') || '');
    updateRowHasNewReply(activeDetailId, data && data.has_new_reply ? data.has_new_reply : 0);
    if (getUnreadOnlyValue() && row instanceof HTMLElement) {
      row.classList.add('helpdesk_state_hidden');
    }
    if (window.CB_HELPDESK_HEADER && typeof window.CB_HELPDESK_HEADER.refresh === 'function') {
      window.CB_HELPDESK_HEADER.refresh();
    }
    refreshActiveRowUi();
    if (scrollDetailToBottomOnRender) {
      scrollDetailToBottomOnRender = false;
      window.requestAnimationFrame(function () {
        scrollDetailToBottom();
      });
    }
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
    scrollDetailToBottomOnRender = true;
    refreshActiveRowUi();
    detailBox.innerHTML = '<div class="helpdesk_detail_notice helpdesk_state_loading ram_normal zaobleni_10">Načítám detail...</div>';

    fetch(apiActionUrl('detail') + '&id_helpdesk=' + encodeURIComponent(String(id)), {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Comeback-Helpdesk': '1'
      }
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (data) {
        if (!data || data.ok !== true) {
          detailBox.innerHTML = '<div class="helpdesk_detail_notice helpdesk_state_error ram_normal zaobleni_10">Detail se nepodařilo načíst.</div>';
          activeDetailId = '';
          refreshActiveRowUi();
          return;
        }
        renderDetail(data, row);
      })
      .catch(function () {
        detailBox.innerHTML = '<div class="helpdesk_detail_notice helpdesk_state_error ram_normal zaobleni_10">Detail se nepodařilo načíst.</div>';
        activeDetailId = '';
        refreshActiveRowUi();
      });
  }

  function filterMatchesState(filterValue, rowState, row) {
    var state = text(rowState).trim();
    if (filterValue === 'all') { return true; }
    if (filterValue === 'new') { return state === 'nový'; }
    if (filterValue === 'active') { return state === 'řeší se'; }
    if (filterValue === 'resolved') { return state === 'vyřešeno'; }
    if (filterValue === 'closed') { return state === 'vyřešeno'; }
    if (filterValue === 'mine') { return row instanceof HTMLElement && Number(row.getAttribute('data-hd-owner-id') || '0') === authorId; }
    if (filterValue === 'watched') { return row instanceof HTMLElement && row.getAttribute('data-hd-watched') === '1'; }
    if (filterValue === 'admin') { return true; }
    return true;
  }

  function applyFilter() {
    var value = getCurrentFilterValue();
    var unreadOnly = getUnreadOnlyValue();
    var list = getListBox();
    if (!(list instanceof HTMLElement)) { return; }

    list.querySelectorAll('[data-hd-item]').forEach(function (row) {
      if (!(row instanceof HTMLElement)) { return; }
      var rowState = text(row.getAttribute('data-hd-stav') || '');
      var rowUnread = row.getAttribute('data-hd-has-new-reply') === '1';
      row.classList.toggle('helpdesk_state_hidden', !(filterMatchesState(value, rowState, row) && (!unreadOnly || rowUnread)));
    });

    var activeRow = getItemRow(activeDetailId);
    if (activeDetailId !== '' && activeRow instanceof HTMLElement && activeRow.classList.contains('helpdesk_state_hidden')) {
      closeActiveDetail();
      return;
    }
    refreshActiveRowUi();
  }

  function updateRowState(id, state) {
    var row = getItemRow(id);
    if (!(row instanceof HTMLElement)) { return; }
    var oldState = row.getAttribute('data-hd-stav') || '';
    row.setAttribute('data-hd-stav', text(state));
    row.setAttribute('data-hd-filtr', filterStatusValue(state));
    var stateBox = row.querySelector('[data-hd-state-text="1"]');
    if (stateBox instanceof HTMLElement) {
      stateBox.textContent = text(state);
    }
    applyFilter();
  }

  function pollTicketStates() {
    if (!isHelpdeskVisible()) { return; }

    fetch(apiActionUrl('stav_tiketu'), {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Comeback-Helpdesk': '1'
      }
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (data) {
        if (!data || data.ok !== true || !Array.isArray(data.tickets)) {
          return;
        }

        var map = {};
        data.tickets.forEach(function (item) {
          if (!item) { return; }
          map[String(item.id_helpdesk || '')] = Number(item.has_new_reply || 0);
        });

        var list = getListBox();
        if (!(list instanceof HTMLElement)) { return; }

        list.querySelectorAll('[data-hd-item]').forEach(function (row) {
          if (!(row instanceof HTMLElement)) { return; }
          var rowId = String(row.getAttribute('data-hd-item') || '');
          if (!Object.prototype.hasOwnProperty.call(map, rowId)) { return; }
          updateRowHasNewReply(rowId, map[rowId]);
        });
      })
      .catch(function () {
      });
  }

  function handleClick(e) {
    var target = e.target;
    if (!(target instanceof Element)) { return; }

    if (target.closest('[data-cb-hd-close-detail="1"]')) {
      closeActiveDetail();
      return;
    }

    var followBtn = target.closest('[data-cb-hd-follow]');
    if (followBtn instanceof HTMLElement) {
      postJson(apiActionUrl('sledovat'), {
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
      var replyPayload = {
        id_helpdesk: replyId,
        zprava: replyText
      };
      if (replyBtn.getAttribute('data-cb-hd-resolve') === '1') {
        replyPayload.uzavrit = 1;
      }
      postJson(apiActionUrl('zprava_pridat'), replyPayload).then(function (result) {
        if (!result.ok || !result.data || result.data.ok !== true) {
          window.alert(result.data && result.data.err ? String(result.data.err) : 'Odeslání odpovědi selhalo.');
          return;
        }
        if (result.data && result.data.stav) {
          updateRowState(replyId, String(result.data.stav));
        }
        updateRowHasNewReply(replyId, result.data && result.data.has_new_reply ? result.data.has_new_reply : 0);
        if (replyPayload.uzavrit === 1 || (result.data && filterStatusValue(result.data.stav || '') === 'uzavřené')) {
          closeActiveDetail();
          return;
        }
        scrollDetailToBottomOnRender = true;
        reloadOpenDetail(replyId);
      });
      return;
    }

    var row = target.closest('article[data-hd-item]');
    if (row instanceof HTMLElement) {
      loadDetail(row.getAttribute('data-hd-item') || '');
    }
  }

  container.addEventListener('click', handleClick);

  renderEmptyDetailPanel();
  refreshActiveRowUi();
  pollTimerId = window.setInterval(pollTicketStates, 15000);

  window.CB_HELPDESK = {
    openUnreadFilter: openUnreadFilter
  };

    if (window.CB_HELPDESK_PENDING_FILTER) {
      openUnreadFilter(window.CB_HELPDESK_PENDING_FILTER);
      window.CB_HELPDESK_PENDING_FILTER = '';
    }

    window.__CB_HELPDESK_CLEANUP__ = function () {
      container.removeEventListener('click', handleClick);
      if (pollTimerId) {
        window.clearInterval(pollTimerId);
        pollTimerId = 0;
      }
      window.CB_HELPDESK = null;
      window.__CB_HELPDESK_CLEANUP__ = null;
    };
  }

  window.CB_HELPDESK_INIT = initHelpdesk;
  document.addEventListener('DOMContentLoaded', function () {
    initHelpdesk(document);
  });
})();
