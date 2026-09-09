'use strict';

/* Ovládá výběr poboček formulářů Správy uživatelů i po AJAXové výměně PP. */
(function () {
  var requestNumber = 0;

  function bind(root) {
    root.querySelectorAll('[data-admin-user-form]').forEach(function (form) {
      if (form.dataset.adminUserBound === '1') return;
      form.dataset.adminUserBound = '1';
      var firma = form.querySelector('[data-admin-user-firma]');
      var picker = form.querySelector('[data-admin-user-branches]');
      if (!(firma instanceof HTMLSelectElement) || !(picker instanceof HTMLElement)) return;
      var all = picker.querySelector('[data-admin-user-pob-all]');
      var options = Array.prototype.slice.call(picker.querySelectorAll('[data-admin-user-pob-option]'));
      var groups = Array.prototype.slice.call(picker.querySelectorAll('[data-admin-user-pob-firma]'));
      var main = picker.querySelector('[data-admin-user-main-pob]');

      function sync() {
        var company = firma.value;
        groups.forEach(function (group) { group.hidden = group.getAttribute('data-admin-user-pob-firma') !== company; });
        options.forEach(function (option) {
          var visible = option.closest('[data-admin-user-pob-firma]').getAttribute('data-admin-user-pob-firma') === company;
          option.disabled = !visible;
          if (!visible) option.checked = false;
        });
        if (all instanceof HTMLInputElement && all.checked) {
          options.forEach(function (option) { if (!option.disabled) option.checked = true; });
        }
        if (main instanceof HTMLSelectElement) {
          Array.prototype.slice.call(main.options).forEach(function (option) {
            option.hidden = option.value !== '' && option.getAttribute('data-admin-user-main-firma') !== company;
            option.disabled = option.hidden;
          });
          var selected = options.filter(function (option) { return option.checked && !option.disabled; }).map(function (option) { return option.value; });
          if (main.value !== '' && selected.indexOf(main.value) === -1) main.value = '';
        }
      }
      firma.addEventListener('change', sync);
      if (all instanceof HTMLInputElement) all.addEventListener('change', sync);
      options.forEach(function (option) { option.addEventListener('change', function () { if (all instanceof HTMLInputElement && !option.checked) all.checked = false; sync(); }); });
      sync();
    });
  }

  function removeDetail() {
    document.querySelectorAll('[data-admin-user-detail-row]').forEach(function (row) { row.remove(); });
    document.querySelectorAll('[data-admin-user-edit-form]').forEach(function (form) { form.remove(); });
    document.querySelectorAll('[data-admin-user-detail][aria-expanded="true"]').forEach(function (link) { link.setAttribute('aria-expanded', 'false'); });
  }

  function appendForm(html) {
    var holder = document.createElement('div');
    holder.innerHTML = html;
    var form = holder.firstElementChild;
    if (!(form instanceof HTMLFormElement)) return;
    form.setAttribute('data-admin-user-edit-form', '');
    var section = document.querySelector('.admin_users');
    if (section instanceof HTMLElement) section.appendChild(form);
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
        if (!response.ok) throw new Error('Detail uživatele se nepodařilo načíst.');
        return response.json();
      })
      .then(function (payload) {
        if (currentRequest !== requestNumber || !payload || payload.ok !== true) return;
        removeDetail();
        var detailRow = document.createElement('tr');
        detailRow.className = 'admin_user_detail_row';
        detailRow.setAttribute('data-admin-user-detail-row', String(payload.id_user));
        detailRow.innerHTML = '<td colspan="8">' + payload.detail_html + '</td>';
        row.insertAdjacentElement('afterend', detailRow);
        appendForm(payload.form_html);
        target.setAttribute('aria-expanded', 'true');
        bind(detailRow);
        window.requestAnimationFrame(function () { detailRow.classList.add('is-open'); });
      })
      .catch(function () { window.location.assign(target.href); });
  }, true);

  bind(document);
  document.addEventListener('cb:main-swapped', function () { bind(document); });
}());
