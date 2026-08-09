(function () {
  'use strict';

  var MIN_LEVEL = 0;
  var MAX_LEVEL = 6;

  function clampLevel(value) {
    var level = parseInt(String(value), 10);
    if (isNaN(level)) {
      level = MIN_LEVEL;
    }
    return Math.max(MIN_LEVEL, Math.min(MAX_LEVEL, level));
  }

  function setFormLevel(form, level) {
    var safeLevel = clampLevel(level);
    var value = form.querySelector('[data-cb-theme-value]');
    var minus = form.querySelector('[data-cb-theme-delta="-1"]');
    var plus = form.querySelector('[data-cb-theme-delta="1"]');

    form.setAttribute('data-theme-level', String(safeLevel));
    document.documentElement.setAttribute('data-theme-level', String(safeLevel));
    if (value) {
      value.textContent = String(safeLevel);
    }
    if (minus) {
      minus.disabled = safeLevel <= MIN_LEVEL;
    }
    if (plus) {
      plus.disabled = safeLevel >= MAX_LEVEL;
    }
  }

  function setPending(form, pending) {
    var buttons = form.querySelectorAll('[data-cb-theme-delta]');
    buttons.forEach(function (button) {
      button.disabled = pending || button.disabled;
    });
    form.setAttribute('data-cb-theme-pending', pending ? '1' : '0');
  }

  function refreshButtons(form) {
    setFormLevel(form, clampLevel(form.getAttribute('data-theme-level')));
  }

  function sendThemeLevel(form, delta, previousLevel) {
    var body = new URLSearchParams(new FormData(form));
    body.set('cb_theme_delta', String(delta));

    setPending(form, true);

    fetch(form.action || window.location.href, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Comeback-Theme': '1',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString(),
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        return response.json();
      })
      .then(function (data) {
        if (!data || data.ok !== true) {
          throw new Error((data && data.err) || 'Ulozeni selhalo');
        }
        setFormLevel(form, data.dark);
      })
      .catch(function () {
        setFormLevel(form, previousLevel);
      })
      .finally(function () {
        form.setAttribute('data-cb-theme-pending', '0');
        refreshButtons(form);
      });
  }

  function initForm(form) {
    if (!form || form.getAttribute('data-cb-theme-ready') === '1') {
      return;
    }
    form.setAttribute('data-cb-theme-ready', '1');
    refreshButtons(form);

    form.addEventListener('click', function (event) {
      var button = event.target.closest('[data-cb-theme-delta]');
      if (!button || !form.contains(button) || button.disabled) {
        return;
      }

      event.preventDefault();

      if (form.getAttribute('data-cb-theme-pending') === '1') {
        return;
      }

      var delta = parseInt(button.getAttribute('data-cb-theme-delta') || '0', 10);
      delta = delta < 0 ? -1 : (delta > 0 ? 1 : 0);
      if (delta === 0) {
        return;
      }

      var previousLevel = clampLevel(form.getAttribute('data-theme-level'));
      var nextLevel = clampLevel(previousLevel + delta);
      if (nextLevel === previousLevel) {
        setFormLevel(form, previousLevel);
        return;
      }

      setFormLevel(form, nextLevel);
      sendThemeLevel(form, delta, previousLevel);
    });
  }

  function init() {
    document.querySelectorAll('[data-cb-theme-form="1"]').forEach(initForm);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  document.addEventListener('cb:main-swapped', init);
}());
