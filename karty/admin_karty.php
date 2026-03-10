<?php
// karty/admin_karty.php * Verze: V6 * Aktualizace: 09.03.2026
declare(strict_types=1);

$apiUrl = cb_url('lib/karty_admin_api.php');
$cbUser = $_SESSION['cb_user'] ?? [];
$idRole = (int)($cbUser['id_role'] ?? 0);
$isAdmin = ($idRole === 1);

$karetCount = 0;
$lastAddedName = '-';
$souborOptions = [];
$usedSouborMap = [];

try {
    $res = db()->query('SELECT COUNT(*) AS cnt FROM karty');
    if ($res) {
        $row = $res->fetch_assoc();
        $karetCount = (int)($row['cnt'] ?? 0);
        $res->free();
    }

    $resLast = db()->query('SELECT nazev FROM karty ORDER BY id_karta DESC LIMIT 1');
    if ($resLast) {
        $rowLast = $resLast->fetch_assoc();
        $lastAddedName = trim((string)($rowLast['nazev'] ?? ''));
        if ($lastAddedName === '') {
            $lastAddedName = '-';
        }
        $resLast->free();
    }

    $resUsed = db()->query('SELECT soubor FROM karty');
    if ($resUsed) {
        while ($rowUsed = $resUsed->fetch_assoc()) {
            $used = trim((string)($rowUsed['soubor'] ?? ''));
            if ($used !== '') {
                $usedSouborMap[strtolower($used)] = true;
            }
        }
        $resUsed->free();
    }
} catch (Throwable $e) {
    $karetCount = 0;
    $lastAddedName = '-';
    $usedSouborMap = [];
}

try {
    $dir = __DIR__;
    $all = scandir($dir);
    if (is_array($all)) {
        foreach ($all as $f) {
            if (!is_string($f) || $f === '.' || $f === '..') {
                continue;
            }
            if (!preg_match('~^[a-z0-9_]{2,80}\.php$~i', $f)) {
                continue;
            }
            $name = preg_replace('~\.php$~i', '', $f);
            if (!is_string($name) || $name === '') {
                continue;
            }
            if (isset($usedSouborMap[strtolower($name)])) {
                continue;
            }
            $souborOptions[] = $name;
        }
    }
    sort($souborOptions, SORT_NATURAL | SORT_FLAG_CASE);
} catch (Throwable $e) {
    $souborOptions = [];
}
?>

<article class="admin_karty_card cb-admin-karty" data-api="<?= h($apiUrl) ?>">
  <div class="card_top">
    <div>
      <h3 class="card_title">Správa karet</h3>
      <p class="card_subtitle">Centrální správa dashboard karet v IS Comeback</p>
    </div>
    <div class="card_tools">
      <button
        type="button"
        class="card_tool_btn"
        data-admink-toggle="1"
        aria-expanded="false"
        title="Rozbalit/sbalit"
      >⤢</button>
    </div>
  </div>

  <div class="admin_karty_compact" data-admink-compact>
    <table class="admin_karty_meta" aria-label="Přehled karet">
      <tr>
        <th>Počet karet v IS:</th>
        <td><strong data-admink-count><?= h((string)$karetCount) ?></strong></td>
      </tr>
      <tr>
        <th>Poslední přidaná:</th>
        <td><strong data-admink-last><?= h($lastAddedName) ?></strong></td>
      </tr>
    </table>
  </div>

  <div class="admin_karty_expanded is-hidden" data-admink-expanded>
    <?php if (!$isAdmin): ?>
      <p class="card_text">Nemáš oprávnění pro správu karet.</p>
    <?php else: ?>
      <div class="admin_karty_tabs" role="tablist" aria-label="Sekce správy karet">
        <button type="button" class="admin_karty_tab is-active" data-admink-tab="nova" role="tab" aria-selected="true">1) Nová karta</button>
        <button type="button" class="admin_karty_tab" data-admink-tab="poradi" role="tab" aria-selected="false">2) Pořadí</button>
        <button type="button" class="admin_karty_tab" data-admink-tab="titulky" role="tab" aria-selected="false">3) Titulky</button>
        <button type="button" class="admin_karty_tab" data-admink-tab="deaktivace" role="tab" aria-selected="false">4) Deaktivace</button>
      </div>

      <div class="cb-admin-karty-msg admin_karty_msg" aria-live="polite"></div>

      <section class="admin_karty_panel" data-admink-panel="nova">
        <p class="card_text card_text_muted">Přidání nové karty včetně názvu souboru, pořadí a minimální role.</p>
        <form class="cb-admin-karty-add admin_karty_form" autocomplete="off">
          <div class="admin_karty_form_grid">
            <label>Nadpis
              <input name="nazev" type="text" required maxlength="120" placeholder="např. Správa karet" />
            </label>
            <label>Soubor
              <select name="soubor" required>
                <option value="">Vyber soubor...</option>
                <?php foreach ($souborOptions as $opt): ?>
                  <option value="<?= h($opt) ?>"><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Karta povolena pro:
              <select name="min_role" required>
                <option value="1">admin</option>
                <option value="2">manager</option>
                <option value="3" selected>všichni</option>
              </select>
            </label>
            <label>Pořadí
              <input name="poradi" type="number" min="1" max="9999" value="100" required />
            </label>
            <label class="admin_karty_submit_wrap" aria-label="Akce">
              <span>&nbsp;</span>
              <button type="submit">Přidat kartu</button>
            </label>
          </div>
        </form>

        <div class="admin_karty_sep"></div>
        <h4 class="admin_karty_list_title">Seznam existujících karet</h4>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Název</th>
                <th>Soubor</th>
                <th>Min role</th>
                <th>Pořadí</th>
                <th>Aktivní</th>
                <th>Akce</th>
              </tr>
            </thead>
            <tbody data-cb-karty-list>
              <tr><td colspan="7">Načítám...</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="admin_karty_panel is-hidden" data-admink-panel="poradi">
        <p class="card_text card_text_muted">Pořadí měníš šipkami nahoru/dolů nebo úpravou hodnoty ve sloupci pořadí.</p>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Název</th>
                <th>Soubor</th>
                <th>Min role</th>
                <th>Pořadí</th>
                <th>Aktivní</th>
                <th>Akce</th>
              </tr>
            </thead>
            <tbody data-cb-karty-list>
              <tr><td colspan="7">Načítám...</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="admin_karty_panel is-hidden" data-admink-panel="titulky">
        <p class="card_text card_text_muted">Uprav název karty ve sloupci název a potvrď tlačítkem Uložit.</p>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Název</th>
                <th>Soubor</th>
                <th>Min role</th>
                <th>Pořadí</th>
                <th>Aktivní</th>
                <th>Akce</th>
              </tr>
            </thead>
            <tbody data-cb-karty-list>
              <tr><td colspan="7">Načítám...</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="admin_karty_panel is-hidden" data-admink-panel="deaktivace">
        <p class="card_text card_text_muted">Kartu deaktivuj tlačítkem Deaktivovat. Mazání se nepoužívá.</p>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Název</th>
                <th>Soubor</th>
                <th>Min role</th>
                <th>Pořadí</th>
                <th>Aktivní</th>
                <th>Akce</th>
              </tr>
            </thead>
            <tbody data-cb-karty-list>
              <tr><td colspan="7">Načítám...</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  </div>
</article>

<?php
/* karty/admin_karty.php * Verze: V6 * Aktualizace: 09.03.2026 */
?>