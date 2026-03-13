// js/admin_karty.js * Verze: V1 * Aktualizace: 11.03.2026
'use strict';

(function (w) {
  function initAdminKarty() {
    // Zalozky byly odstraneny, soubor zustava jen kvuli kompatibilite nacitani v index.php.
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminKarty);
  } else {
    initAdminKarty();
  }
}(window));
