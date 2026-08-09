(function () {
  'use strict';

  function najdiRoot(prvek) {
    return prvek && prvek.closest ? prvek.closest('[data-cb-period-root="1"]') : null;
  }

  function najdiPanel(prvek) {
    return prvek && prvek.closest ? prvek.closest('[data-cb-prodleva-root="1"]') : null;
  }

  function nastavPopisek(panel, sekundy) {
    var hodnota = panel ? panel.querySelector('[data-cb-prodleva-value="1"]') : null;
    if (hodnota) {
      hodnota.textContent = String(sekundy) + ' sec.';
    }
  }

  function obnovObdobi(root) {
    if (!root || !root.parentNode) {
      return;
    }
    var kopie = root.cloneNode(true);
    kopie.removeAttribute('data-cb-period-ready');
    root.parentNode.replaceChild(kopie, root);
    document.dispatchEvent(new CustomEvent('cb:main-swapped'));
  }

  function ulozProdlevu(input) {
    var panel = najdiPanel(input);
    var root = najdiRoot(input);
    if (!panel || !root) {
      return;
    }

    var sekundy = parseInt(String(input.value || '3'), 10);
    if (sekundy < 1) sekundy = 1;
    if (sekundy > 10) sekundy = 10;
    var milisekundy = sekundy * 1000;

    input.value = String(sekundy);
    root.setAttribute('data-manual-save-delay-ms', String(milisekundy));
    nastavPopisek(panel, sekundy);

    fetch(String(root.getAttribute('data-save-url') || 'index.php'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Comeback-Set-Prodleva': '1'
      },
      body: new URLSearchParams({ prodleva: String(sekundy) }).toString(),
      credentials: 'same-origin'
    })
      .then(function (response) {
        return response.json().catch(function () {
          return {};
        }).then(function (json) {
          if (!response.ok || !json || json.ok !== true) {
            throw new Error('Ulozeni prodlevy selhalo');
          }
        });
      })
      .then(function () {
        obnovObdobi(root);
      })
      .catch(function () {
        obnovObdobi(root);
      });
  }

  document.addEventListener('input', function (event) {
    var input = event.target && event.target.closest ? event.target.closest('[data-cb-prodleva-range="1"]') : null;
    if (!input) {
      return;
    }
    var panel = najdiPanel(input);
    var sekundy = parseInt(String(input.value || '3'), 10);
    if (sekundy < 1) sekundy = 1;
    if (sekundy > 10) sekundy = 10;
    nastavPopisek(panel, sekundy);
  });

  document.addEventListener('change', function (event) {
    var input = event.target && event.target.closest ? event.target.closest('[data-cb-prodleva-range="1"]') : null;
    if (input) {
      ulozProdlevu(input);
    }
  });
}());
