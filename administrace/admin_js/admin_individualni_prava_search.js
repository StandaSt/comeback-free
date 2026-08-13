// admin_js/admin_individualni_prava_search.js
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

  function renderResults(page, users) {
    var results = page.querySelector('[data-admin-individual-results]');
    if (!results) {
      return;
    }

    if (!users.length) {
      results.innerHTML = '<div class="admin_individual_hint">Nenalezen žádný uživatel.</div>';
      return;
    }

    results.innerHTML = ''
      + '<table class="admin_individual_results_table">'
      + '<thead><tr><th>Jméno</th><th>Email</th><th>Telefon</th></tr></thead>'
      + '<tbody>'
      + users.map(function (user) {
        var fullName = String(user.prijmeni || '') + ' ' + String(user.jmeno || '');
        return ''
          + '<tr class="admin_individual_result" data-admin-individual-user="' + escapeHtml(user.id_user) + '">'
          + '<td><strong>' + escapeHtml(fullName.trim()) + '</strong></td>'
          + '<td>' + escapeHtml(user.email_match || '') + '</td>'
          + '<td>' + escapeHtml(user.telefon_match || '') + '</td>'
          + '</tr>';
      }).join('')
      + '</tbody>'
      + '</table>';
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
        var results = page.querySelector('[data-admin-individual-results]');
        if (results) {
          results.innerHTML = '';
        }
      })
      .catch(function (error) {
        window.alert(error.message || 'Načtení uživatele selhalo.');
      });
  }

  window.CB_ADMIN_INDIVIDUAL = {
    post: post
  };

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
