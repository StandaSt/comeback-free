<?php
// includes/admin.php * Verze: V10 * Aktualizace: 07.03.2026
declare(strict_types=1);

/*
 * Admin - sprava karet + vyjimek (krok 3)
 *
 * Tato stranka:
 * - nevklada zadna testovaci data sama
 * - jen zobrazi spravu tabulky karty
 * - data nacita/uklada pres JSON API v lib/karty_admin_api.php
 */

$apiUrl = cb_url('lib/karty_admin_api.php');
?>
<div class="page-head"><h2>Admin</h2></div>

<section class="card cb-admin-karty" data-api="<?= h($apiUrl) ?>">
  <h3>Sprava karet</h3>
  <p>Sprava seznamu karet. Mazani se nepouziva, jen aktivni/neaktivni. Poradi lze menit i tlacitky nahoru/dolu.</p>

  <!-- Formular pro pridani nove karty -->
  <form class="cb-admin-karty-add" autocomplete="off">
    <table class="table">
      <tbody>
        <tr>
          <th><label for="cb-kod">Kod</label></th>
          <td><input id="cb-kod" name="kod" type="text" required maxlength="80" placeholder="napr. trzby" /></td>
        </tr>
        <tr>
          <th><label for="cb-nazev">Nazev</label></th>
          <td><input id="cb-nazev" name="nazev" type="text" required maxlength="120" placeholder="napr. Trzby" /></td>
        </tr>
        <tr>
          <th><label for="cb-soubor">Soubor</label></th>
          <td><input id="cb-soubor" name="soubor" type="text" required maxlength="190" placeholder="napr. blocks/trzby.php" /></td>
        </tr>
        <tr>
          <th><label for="cb-min-role">Min role</label></th>
          <td><input id="cb-min-role" name="min_role" type="number" min="1" max="99" value="2" required /></td>
        </tr>
        <tr>
          <th><label for="cb-poradi">Poradi</label></th>
          <td><input id="cb-poradi" name="poradi" type="number" min="1" max="9999" value="100" required /></td>
        </tr>
      </tbody>
    </table>
    <p>
      <button type="submit">Pridat kartu</button>
      <button type="button" data-cb-karty-refresh="1">Obnovit seznam</button>
    </p>
  </form>

  <!-- Jedno misto pro hlasky uspech/chyba -->
  <div class="cb-admin-karty-msg" aria-live="polite"></div>

  <!-- Prehled existujicich karet -->
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Kod</th>
          <th>Nazev</th>
          <th>Soubor</th>
          <th>Min role</th>
          <th>Poradi</th>
          <th>Aktivni</th>
          <th>Akce</th>
        </tr>
      </thead>
      <tbody data-cb-karty-list>
        <tr><td colspan="8">Nacitam...</td></tr>
      </tbody>
    </table>
  </div>
</section>

<section class="card cb-admin-vyjimky" data-api="<?= h($apiUrl) ?>">
  <h3>Vyjimky opravneni</h3>
  <p>Individualni prava pro uzivatele na konkretni karty (priorita nad roli).</p>

  <p>
    <label for="cb-vyj-user">Uzivatel:</label>
    <select id="cb-vyj-user" data-cb-vyj-user>
      <option value="">Vyber uzivatele...</option>
    </select>
    <button type="button" data-cb-vyj-refresh-users="1">Obnovit uzivatele</button>
    <button type="button" data-cb-vyj-refresh-cards="1">Obnovit vyjimky</button>
  </p>

  <div class="cb-admin-vyjimky-msg" aria-live="polite"></div>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Karta</th>
          <th>Soubor</th>
          <th>Role</th>
          <th>Vyjimka</th>
          <th>Efektivne</th>
          <th>Akce</th>
        </tr>
      </thead>
      <tbody data-cb-vyjimky-list>
        <tr><td colspan="6">Vyber uzivatele.</td></tr>
      </tbody>
    </table>
  </div>
</section>

<section class="card cb-admin-vyjimky-log" data-api="<?= h($apiUrl) ?>">
  <h3>Historie vyjimek</h3>
  <p>Posledni zmeny opravneni (kdo, kdy, co zmenil).</p>

  <p>
    <label for="cb-vyj-log-karta">Karta:</label>
    <select id="cb-vyj-log-karta" data-cb-vyj-log-karta>
      <option value="">Vsechny karty</option>
    </select>
    <label for="cb-vyj-log-search">Hledat:</label>
    <input id="cb-vyj-log-search" type="text" data-cb-vyj-log-search placeholder="user, karta, email, poznamka..." />
    <label for="cb-vyj-log-per-page">Na stranku:</label>
    <select id="cb-vyj-log-per-page" data-cb-vyj-log-per-page>
      <option value="20">20</option>
      <option value="50" selected>50</option>
      <option value="100">100</option>
    </select>
    <button type="button" data-cb-vyj-log-refresh="1">Obnovit historii</button>
    <button type="button" data-cb-vyj-log-export="1">Export CSV</button>
    <button type="button" data-cb-vyj-log-prev="1">Predchozi</button>
    <button type="button" data-cb-vyj-log-next="1">Dalsi</button>
    <span data-cb-vyj-log-pageinfo>Stranka 1/1</span>
  </p>

  <div class="cb-admin-vyjimky-log-msg" aria-live="polite"></div>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Kdy</th>
          <th>Cilovy user</th>
          <th>Karta</th>
          <th>Zmena</th>
          <th>Provedl</th>
          <th>Poznamka</th>
        </tr>
      </thead>
      <tbody data-cb-vyjimky-log-list>
        <tr><td colspan="6">Nacitam...</td></tr>
      </tbody>
    </table>
  </div>
</section>
<?php
/* includes/admin.php * Verze: V10 * Aktualizace: 07.03.2026 */
// Konec souboru
