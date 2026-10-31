// Vyhledava uzivatele s ID, kontakty a serverovym jmenem vcetne titulu; vysledky oddeluje od seznamu vyjimek prav.
'use strict';

(function () {
  function endpoint() {
    return String(window.CB_ENDPOINT || 'index.php');
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function post(action, fields) {
    var body = new URLSearchParams();
    body.set('action', action);
    Object.keys(fields || {}).forEach(function (key) {
      body.set(key, String(fields[key]));
    });

    return fetch(endpoint(), {
      method: 'POST',
      headers: {
        'X-Comeback-Admin-Individual': '1',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept': 'application/json'
      },
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (data) {
          return {response: response, data: data};
        });
      })
      .then(function (result) {
        var data = result.data;
        if (!result.response.ok) {
          throw new Error(window.cbAdminResponseError(result.response.status, data, 'Akci individuálních práv se nepodařilo dokončit.'));
        }
        if (!data || data.ok !== true) {
          throw new Error(window.cbAdminResponseError(result.response.status, data, 'Akce individuálních práv se nepodařila.'));
        }
        return data;
      });
  }

  function pageFor(element) {
    return element ? element.closest('[data-admin-individual="1"]') : null;
  }

  function userTableHtml(users, emailField, telefonField, withCounts) {
    var countHeaders = withCounts ? '<th>Povoleno</th><th>Zakazano</th>' : '';
    return ''
      + '<table class="admin_individual_results_table">'
      + '<thead><tr><th>ID</th><th>Jméno</th>' + countHeaders + '<th>Email</th><th>Telefon</th></tr></thead>'
      + '<tbody>'
      + users.map(function (user) {
        // Titul zahrnuje spolecny zdroj osoby; klient pouze vykresluje popisek.
        var fullName = String(user.cele_jmeno || (String(user.prijmeni || '') + ' ' + String(user.jmeno || '')));
        return ''
          + '<tr class="admin_individual_result" data-admin-individual-user="' + escapeHtml(user.id_user) + '">'
          + '<td>' + escapeHtml(user.id_user) + '</td>'
          + '<td><strong>' + escapeHtml(fullName.trim()) + '</strong></td>'
          + (withCounts ? '<td>' + escapeHtml(user.pocet_povoleno || 0) + '</td><td>' + escapeHtml(user.pocet_zakazano || 0) + '</td>' : '')
          + '<td>' + escapeHtml(user[emailField] || '') + '</td>'
          + '<td>' + escapeHtml(user[telefonField] || '') + '</td>'
          + '</tr>';
      }).join('')
      + '</tbody>'
      + '</table>';
  }

  function renderResults(page, users) {
    var results = page.querySelector('[data-admin-individual-results]');
    if (!results) {
      return;
    }

    // Kontakty zustavaji viditelne i pri hledani jmen, aby se daly rozlisit shodne osoby.
    results.innerHTML = users.length
      ? '<div class="admin_individual_hint">Výsledky hledání: ' + users.length + (users.length === 1 ? ' · Práva uživatele jsou otevřena níže.' : ' · Vyberte uživatele kliknutím na řádek.') + '</div>' + userTableHtml(users, 'email', 'telefon', false)
      : '<div class="admin_individual_hint">Nenalezen žádný uživatel.</div>';
  }

  function renderExceptionUsers(page, users) {
    var results = page.querySelector('[data-admin-individual-exception-users]');
    if (!results) {
      return;
    }

    results.innerHTML = users.length
      ? userTableHtml(users, 'email', 'telefon', true)
      : '<div class="admin_individual_hint">Zatim nema nikdo nastavenou vyjimku.</div>';
  }

  function loadExceptionUsers(page) {
    post('exception_users', {})
      .then(function (data) {
        renderExceptionUsers(page, Array.isArray(data.users) ? data.users : []);
      })
      .catch(function (error) {
        var results = page.querySelector('[data-admin-individual-exception-users]');
        if (results) {
          results.innerHTML = '<div class="admin_individual_hint">' + escapeHtml(window.cbAdminErrorMessage(error, 'Načtení seznamu se nepodařilo.')) + '</div>';
        }
      });
  }

  function setDetail(page, html) {
    var detail = page.querySelector('[data-admin-individual-detail]');
    if (detail) {
      detail.innerHTML = html || '';
    }
  }

  function search(input) {
    var page = pageFor(input);
    var query = input.value.trim();
    if (!page) {
      return;
    }
    var version = page.__adminIndividualSearchVersion;

    if (query.length < 2) {
      var results = page.querySelector('[data-admin-individual-results]');
      if (results) {
        results.innerHTML = '';
      }
      setDetail(page, '');
      return;
    }

    post('search', { q: query })
      .then(function (data) {
        // Starsi odpoved nesmi prepsat vysledek novejsiho hledani.
        if (version !== page.__adminIndividualSearchVersion) return;
        var users = Array.isArray(data.users) ? data.users : [];
        renderResults(page, users);
        setDetail(page, data.detail_html || '');
        if (users.length === 1 && data.detail_html) {
          var selected = page.querySelector('[data-admin-individual-results] [data-admin-individual-user]');
          if (selected) selected.classList.add('is-active');
        }
      })
      .catch(function (error) {
        if (version !== page.__adminIndividualSearchVersion) return;
        setDetail(page, '');
        var results = page.querySelector('[data-admin-individual-results]');
        if (results) {
          results.innerHTML = '<div class="admin_individual_hint">' + escapeHtml(window.cbAdminErrorMessage(error, 'Hledání se nepodařilo.')) + '</div>';
        }
      });
  }

  function loadUser(row) {
    var page = pageFor(row);
    var idUser = String(row.getAttribute('data-admin-individual-user') || '0');
    if (!page || idUser === '0') {
      return;
    }
    var version = page.__adminIndividualSearchVersion;
    // Pri rychlem vyberu dvou osob zobrazime jen posledni zvolenou osobu.
    page.__adminIndividualSelectedUser = idUser;

    post('detail', { id_user: idUser })
      .then(function (data) {
        if (version !== page.__adminIndividualSearchVersion || page.__adminIndividualSelectedUser !== idUser) return;
        setDetail(page, data.detail_html || '');
        Array.prototype.slice.call(page.querySelectorAll('[data-admin-individual-user]')).forEach(function (userRow) {
          userRow.classList.toggle('is-active', userRow === row);
        });
      })
      .catch(function (error) {
        if (version !== page.__adminIndividualSearchVersion || page.__adminIndividualSelectedUser !== idUser) return;
        window.alert(window.cbAdminErrorMessage(error, 'Načtení uživatele se nepodařilo.'));
      });
  }

  window.CB_ADMIN_INDIVIDUAL = {
    post: post
  };

  function init(root) {
    Array.prototype.slice.call(root.querySelectorAll('[data-admin-individual="1"]')).forEach(function (page) {
      loadExceptionUsers(page);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      init(document);
    }, { once: true });
  } else {
    init(document);
  }

  document.addEventListener('cb:main-swapped', function () {
    init(document);
  });

  document.addEventListener('cb:admin-individual-exception-saved', function () {
    init(document);
  });

  document.addEventListener('input', function (event) {
    var input = event.target && event.target.closest ? event.target.closest('[data-admin-individual-search]') : null;
    if (!input) {
      return;
    }

    window.clearTimeout(input.__adminIndividualTimer || 0);
    var page = pageFor(input);
    if (!page) return;
    page.__adminIndividualSearchVersion = (page.__adminIndividualSearchVersion || 0) + 1;
    var searching = input.value.trim().length >= 2;
    var exceptions = page.querySelector('[data-admin-individual-exception-users-wrap]');
    if (exceptions) exceptions.hidden = searching;
    setDetail(page, '');
    var results = page.querySelector('[data-admin-individual-results]');
    if (results) results.innerHTML = searching ? '<div class="admin_individual_hint">Hledám…</div>' : '';
    input.__adminIndividualTimer = window.setTimeout(function () {
      search(input);
    }, 350);
  });

  document.addEventListener('click', function (event) {
    var row = event.target && event.target.closest ? event.target.closest('[data-admin-individual-user]') : null;
    if (row) {
      loadUser(row);
    }
  });
})();
