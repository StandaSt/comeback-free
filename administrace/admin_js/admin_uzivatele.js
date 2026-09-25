'use strict';

/* Rozbalení detailu účtu; údaje osoby se upravují výhradně v HR. */
(function () {
  var requestNumber = 0;

  function removeDetail() {
    document.querySelectorAll('[data-admin-user-detail-row]').forEach(function (row) { row.remove(); });
    document.querySelectorAll('[data-admin-user-detail][aria-expanded="true"]').forEach(function (link) { link.setAttribute('aria-expanded', 'false'); });
  }

  document.addEventListener('click', function (event) {
    var target = event.target instanceof Element ? event.target.closest('[data-admin-user-detail]') : null;
    if (!(target instanceof HTMLAnchorElement)) return;
    event.preventDefault();
    event.stopPropagation();

    var row = target.closest('[data-admin-user-row]');
    if (!(row instanceof HTMLTableRowElement)) return;
    var current = document.querySelector('[data-admin-user-detail-row]');
    if (current instanceof HTMLTableRowElement && current.getAttribute('data-admin-user-detail-row') === row.getAttribute('data-admin-user-row')) {
      removeDetail();
      return;
    }

    var currentRequest = ++requestNumber;
    fetch(target.href, { headers: { 'X-Comeback-Admin-User-Detail': '1' }, credentials: 'same-origin' })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (payload) {
          if (!response.ok || !payload || payload.ok !== true) {
            throw new Error(window.cbAdminResponseError(response.status, payload, 'Detail uživatele se nepodařilo načíst.'));
          }
          return payload;
        });
      })
      .then(function (payload) {
        if (currentRequest !== requestNumber || !payload || payload.ok !== true) return;
        removeDetail();
        var detailRow = document.createElement('tr');
        detailRow.className = 'admin_user_detail_row';
        detailRow.setAttribute('data-admin-user-detail-row', String(payload.id_user));
        detailRow.innerHTML = '<td colspan="9">' + payload.detail_html + '</td>';
        row.insertAdjacentElement('afterend', detailRow);
        target.setAttribute('aria-expanded', 'true');
        window.requestAnimationFrame(function () { detailRow.classList.add('is-open'); });
      })
      .catch(function () { window.location.assign(target.href); });
  }, true);

}());
