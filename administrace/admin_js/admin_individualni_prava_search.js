// Nacita seznam uzivatelu s vyjimkami, vyhledava uzivatele a otevre jejich detail prav.
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
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        return response.json();
      })
      .then(function (data) {
        if (!data || data.ok !== true) {
          throw new Error(String((data && data.err) || 'Akce selhala.'));
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
      + '<thead><tr><th>Jméno</th>' + countHeaders + '<th>Email</th><th>Telefon</th></tr></thead>'
      + '<tbody>'
      + users.map(function (user) {
        var fullName = String(user.prijmeni || '') + ' ' + String(user.jmeno || '');
        return ''
          + '<tr class="admin_individual_result" data-admin-individual-user="' + escapeHtml(user.id_user) + '">'
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

    results.innerHTML = users.length
      ? userTableHtml(users, 'email_match', 'telefon_match', false)
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
          results.innerHTML = '<div class="admin_individual_hint">' + escapeHtml(error.message || 'Nacteni seznamu selhalo.') + '</div>';
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
        var users = Array.isArray(data.users) ? data.users : [];
        if (users.length === 1 && data.detail_html) {
          var results = page.querySelector('[data-admin-individual-results]');
          if (results) {
            results.innerHTML = '';
          }
        } else {
          renderResults(page, users);
        }
        setDetail(page, data.detail_html || '');
      })
      .catch(function (error) {
        setDetail(page, '');
        var results = page.querySelector('[data-admin-individual-results]');
        if (results) {
          results.innerHTML = '<div class="admin_individual_hint">' + escapeHtml(error.message || 'Hledání selhalo.') + '</div>';
        }
      });
  }

  function loadUser(row) {
    var page = pageFor(row);
    var idUser = String(row.getAttribute('data-admin-individual-user') || '0');
    if (!page || idUser === '0') {
      return;
    }

    post('detail', { id_user: idUser })
      .then(function (data) {
        setDetail(page, data.detail_html || '');
        Array.prototype.slice.call(page.querySelectorAll('[data-admin-individual-user]')).forEach(function (userRow) {
          userRow.classList.toggle('is-active', userRow === row);
        });
      })
      .catch(function (error) {
        window.alert(error.message || 'Načtení uživatele selhalo.');
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
