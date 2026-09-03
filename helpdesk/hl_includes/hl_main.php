<?php
// helpdesk/hl_includes/hl_main.php * Verze: V1 * Aktualizace: 23.06.2026
declare(strict_types=1);

require_once __DIR__ . '/../hl_lib/hl_prava.php';
require_once __DIR__ . '/../hl_lib/hl_pages.php';

if (!function_exists('cb_helpdesk_ticket_h')) {
    function cb_helpdesk_ticket_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('cb_helpdesk_ticket_author')) {
    function cb_helpdesk_ticket_author(array $row): string
    {
        $author = trim((string)($row['jmeno'] ?? '') . ' ' . (string)($row['prijmeni'] ?? ''));
        if ($author !== '') {
            return $author;
        }

        return 'ID ' . (string)(int)($row['id_user_zalozil'] ?? 0);
    }
}

if (!function_exists('cb_helpdesk_ticket_author_class')) {
    function cb_helpdesk_ticket_author_class(array $row): string
    {
        $idRole = (int)($row['id_role'] ?? 0);
        if ($idRole > 0 && $idRole <= 1) {
            return 'helpdesk_state_role_admin';
        }
        if ($idRole === 2 || $idRole === 3) {
            return 'helpdesk_state_role_manager';
        }
        if ($idRole >= 4 && $idRole <= 7) {
            return 'helpdesk_state_role_branch';
        }

        return 'helpdesk_state_role_default';
    }
}

if (!function_exists('cb_helpdesk_ticket_visibility_label')) {
    function cb_helpdesk_ticket_visibility_label(int $value): string
    {
        if ($value === 1) {
            return 'Všichni mohou reagovat';
        }
        if ($value === 2) {
            return 'Všichni mohou číst';
        }

        return 'Pouze pro admina';
    }
}

if (!function_exists('cb_helpdesk_ticket_type_label')) {
    function cb_helpdesk_ticket_type_label(string $value): string
    {
        if ($value === 'chyba') {
            return 'Chyba systému';
        }
        if ($value === 'dotaz') {
            return 'Dotaz';
        }
        if ($value === 'navrh') {
            return 'Námět na vylepšení';
        }

        return $value;
    }
}

if (empty($_SESSION['login_ok'])) {
    echo '<p>Nutné přihlášení.</p>';
    return;
}

$idUser = cb_helpdesk_current_user_id();
$isAdmin = cb_helpdesk_is_admin();
$conn = db();
$helpdeskAreaCondition = cb_helpdesk_allowed_area_condition();
$helpdeskCompanyCondition = 'COALESCE(h.id_firma, 1) = ' . (string)cb_helpdesk_current_company_id();

$items = [];
$scope = cb_helpdesk_visible_scope($idUser);

if ($isAdmin) {
    $stmtItems = $conn->prepare('
        SELECT h.id_helpdesk, h.id_user_zalozil, h.modul, h.typ, h.stav, h.verejny, h.predmet, h.vytvoreno, h.upraveno,
               h.pocet_zobrazeni, h.pocet_unikatnich_zobrazeni, h.pocet_zprav, TIMESTAMPDIFF(MINUTE, h.vytvoreno, NOW()) AS stari_minut,
               u.jmeno, u.prijmeni, u.id_role,
               CASE
                   WHEN hr.id_helpdesk_read IS NULL THEN 1
                   WHEN h.posledni_zprava IS NOT NULL AND h.posledni_zprava > hr.precteno THEN 1
                   ELSE 0
               END AS has_new_reply,
               EXISTS (
                   SELECT 1
                   FROM helpdesk_sledujici sw
                   WHERE sw.id_helpdesk = h.id_helpdesk
                     AND sw.id_user = ?
               ) AS is_watched
        FROM helpdesk h
        LEFT JOIN `user` u ON u.id_user = h.id_user_zalozil
        LEFT JOIN helpdesk_read hr ON hr.id_helpdesk = h.id_helpdesk AND hr.id_user = ?
        WHERE ' . $helpdeskAreaCondition . ' AND ' . $helpdeskCompanyCondition . '
        ORDER BY h.vytvoreno DESC, h.id_helpdesk DESC
        LIMIT 120
    ');
} else {
    $stmtItems = $conn->prepare('
        SELECT h.id_helpdesk, h.id_user_zalozil, h.modul, h.typ, h.stav, h.verejny, h.predmet, h.vytvoreno, h.upraveno,
               h.pocet_zobrazeni, h.pocet_unikatnich_zobrazeni, h.pocet_zprav, TIMESTAMPDIFF(MINUTE, h.vytvoreno, NOW()) AS stari_minut,
               u.jmeno, u.prijmeni, u.id_role,
               CASE
                   WHEN hr.id_helpdesk_read IS NULL THEN 1
                   WHEN h.posledni_zprava IS NOT NULL AND h.posledni_zprava > hr.precteno THEN 1
                   ELSE 0
               END AS has_new_reply,
               EXISTS (
                   SELECT 1
                   FROM helpdesk_sledujici sw
                   WHERE sw.id_helpdesk = h.id_helpdesk
                     AND sw.id_user = ?
               ) AS is_watched
        FROM helpdesk h
        LEFT JOIN `user` u ON u.id_user = h.id_user_zalozil
        LEFT JOIN helpdesk_read hr ON hr.id_helpdesk = h.id_helpdesk AND hr.id_user = ?
        WHERE ' . $helpdeskAreaCondition . ' AND ' . $helpdeskCompanyCondition . ' AND ' . $scope['sql'] . '
        ORDER BY h.vytvoreno DESC, h.id_helpdesk DESC
        LIMIT 120
    ');
}

if ($stmtItems instanceof mysqli_stmt) {
    if ($isAdmin) {
        $stmtItems->bind_param('ii', $idUser, $idUser);
        $stmtItems->execute();
    } else {
        $types = 'ii' . (string)$scope['types'];
        $params = [$idUser, $idUser];
        foreach ((array)($scope['params'] ?? []) as $value) {
            $params[] = $value;
        }
        $bind = [$types];
        foreach ($params as $index => $value) {
            $bind[] = &$params[$index];
        }
        call_user_func_array([$stmtItems, 'bind_param'], $bind);
        $stmtItems->execute();
    }
    $resItems = $stmtItems->get_result();
    if ($resItems instanceof mysqli_result) {
        while ($row = $resItems->fetch_assoc()) {
            $items[] = $row;
        }
        $resItems->free();
    }
    $stmtItems->close();
}

$helpdeskApiUrl = cb_root_url('index.php');
$helpdeskSourceModule = (string)($_SESSION['cb_helpdesk_source_module'] ?? 'provoz');
$helpdeskAreas = cb_helpdesk_allowed_areas();
if (!isset($helpdeskAreas[$helpdeskSourceModule])) {
    $helpdeskSourceModule = (string)(array_key_first($helpdeskAreas) ?? '');
}
$helpdeskUser = $_SESSION['cb_user'] ?? [];
$helpdeskUserName = trim((string)($helpdeskUser['name'] ?? '') . ' ' . (string)($helpdeskUser['surname'] ?? ''));
if ($helpdeskUserName === '') {
    $helpdeskUserName = 'ID ' . (string)$idUser;
}
$helpdeskUserRole = trim((string)($helpdeskUser['role'] ?? $helpdeskUser['nazev_role'] ?? ''));
if ($helpdeskUserRole === '') {
    $helpdeskUserRole = '-';
}
$helpdeskCurrentView = cb_helpdesk_current_view($isAdmin);
$helpdeskView = $helpdeskCurrentView['key'];
$helpdeskPageTitle = $helpdeskCurrentView['title'];
$helpdeskShowClosedToggle = $helpdeskView !== 'new-ticket' && $helpdeskView !== 'closed';

$helpdeskMenuUrl = static function (string $view) use ($helpdeskSourceModule): string {
    return cb_root_url('index.php?m=helpdesk&src=' . rawurlencode($helpdeskSourceModule) . '&hd=' . rawurlencode($view));
};
$helpdeskCreateUrl = cb_root_url('index.php?helpdesk_action=vytvorit&cb_helpdesk_module=' . rawurlencode($helpdeskSourceModule));
$visibleItems = [];
foreach ($items as $item) {
    if ($helpdeskView === 'new-ticket') {
        continue;
    }
    if ($helpdeskView === 'mine' && (int)($item['id_user_zalozil'] ?? 0) !== $idUser) {
        continue;
    }
    if ($helpdeskView === 'watched' && (int)($item['is_watched'] ?? 0) !== 1) {
        continue;
    }
    if ($helpdeskView === 'closed' && trim((string)($item['stav'] ?? '')) !== 'vyřešeno') {
        continue;
    }
    $visibleItems[] = $item;
}

?>
<?php if (!defined('CB_PP_ONLY') || CB_PP_ONLY !== true): ?>
    <?php require __DIR__ . '/hl_menu.php'; ?>
<?php endif; ?>
<?php if ($helpdeskView === 'uprava_profilu'): ?>
    <?php require __DIR__ . '/../../common/pages/uprava_profilu.php'; ?>
    <?php return; ?>
<?php endif; ?>
<section class="pp helpdesk_module_content" data-module="helpdesk" data-page="<?= h($helpdeskView) ?>" data-cb-helpdesk-module="1" data-cb-hd-api-url="<?= h($helpdeskApiUrl) ?>" data-cb-hd-is-admin="<?= $isAdmin ? '1' : '0' ?>" data-cb-hd-author-id="<?= (int)$idUser ?>">
<header class="pp_header helpdesk_page_header">
<div class="helpdesk_list_header">
  <h1><?= h($helpdeskPageTitle) ?></h1>
  <?php if ($helpdeskShowClosedToggle): ?>
    <label class="helpdesk_include_closed">
      <input type="checkbox" data-cb-hd-include-closed="1">
      <span>i uzavřené</span>
    </label>
  <?php endif; ?>
</div>
<?php if ($helpdeskView !== 'new-ticket'): ?>
  <h2 class="helpdesk_detail_heading" data-cb-hd-detail-heading="1">Vyber tiket ze seznamu vlevo</h2>
<?php endif; ?>
</header>
<?php if ($helpdeskView === 'new-ticket'): ?>
    <form class="helpdesk_form ram_normal zaobleni_8" method="post" action="<?= h($helpdeskCreateUrl) ?>" enctype="multipart/form-data">
      <h3 class="helpdesk_form_title">Zadání nového tiketu</h3>

      <div class="helpdesk_form_grid">
        <div class="helpdesk_form_label">Zadává:</div>
        <div class="helpdesk_form_static"><?= h($helpdeskUserName) ?> (<?= h($helpdeskUserRole) ?>)</div>

        <label class="helpdesk_form_label" for="hl-ticket-typ">Typ</label>
        <select class="helpdesk_form_input" id="hl-ticket-typ" name="typ">
          <option value="chyba">Chyba systému</option>
          <option value="dotaz">Dotaz</option>
          <option value="navrh">Námět na vylepšení</option>
        </select>

        <div class="helpdesk_form_label">Oblast</div>
        <?php if (count($helpdeskAreas) === 1): ?>
          <div class="helpdesk_form_static"><?= h((string)(reset($helpdeskAreas)['label'] ?? '')) ?></div>
          <input type="hidden" name="modul" value="<?= h((string)(array_key_first($helpdeskAreas) ?? '')) ?>">
        <?php else: ?>
          <select class="helpdesk_form_input" id="hl-ticket-modul" name="modul" required>
            <?php foreach ($helpdeskAreas as $areaKey => $area): ?>
              <option value="<?= h((string)$areaKey) ?>"<?= $areaKey === $helpdeskSourceModule ? ' selected' : '' ?>><?= h((string)$area['label']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <label class="helpdesk_form_label" for="hl-ticket-predmet">Předmět</label>
        <input class="helpdesk_form_input" id="hl-ticket-predmet" type="text" name="predmet" maxlength="160" placeholder="Nutno vyplnit" required>

        <div class="helpdesk_form_label">Určení:</div>
        <div class="helpdesk_form_radio_group">
          <label class="helpdesk_form_radio_label"><input class="helpdesk_form_radio_input" type="radio" name="urceni" value="admin"> Pouze pro admina</label>
          <label class="helpdesk_form_radio_label"><input class="helpdesk_form_radio_input" type="radio" name="urceni" value="reagovat" checked> Všichni mohou reagovat</label>
          <label class="helpdesk_form_radio_label"><input class="helpdesk_form_radio_input" type="radio" name="urceni" value="cist"> Všichni mohou číst</label>
        </div>

        <label class="helpdesk_form_label" for="hl-ticket-popis">Popis</label>
        <textarea class="helpdesk_form_input helpdesk_form_textarea" id="hl-ticket-popis" name="popis" rows="8" minlength="25" placeholder="Minimální délka zprávy je 25 znaků" required></textarea>

        <label class="helpdesk_form_label" for="hl-ticket-prilohy">Přílohy</label>
        <div class="helpdesk_form_files">
          <input class="helpdesk_form_input" id="hl-ticket-prilohy" type="file" name="prilohy[]" multiple accept=".png,.jpg,.jpeg,.webp,.gif,.pdf">
          <div class="helpdesk_form_hint">Povolené typy: PNG, JPG, WEBP, GIF, PDF. Maximálně 5 MB na soubor.</div>
        </div>
      </div>

      <div class="helpdesk_form_actions">
        <a class="helpdesk_action_btn" href="<?= h($helpdeskMenuUrl('all')) ?>">Zpět</a>
        <button type="submit" class="helpdesk_action_btn helpdesk_action_btn_primary">Odeslat</button>
      </div>
    </form>
<?php else: ?>
<div class="helpdesk_board">
  <div class="helpdesk_list_col">
      <section class="helpdesk_list_section" data-cb-hd-filter-value="all" data-cb-hd-include-closed="<?= $helpdeskShowClosedToggle ? '0' : '1' ?>">
        <div class="helpdesk_scroll">
          <div class="helpdesk_ticket_list" data-cb-hd-list="1">
            <?php if ($visibleItems === []): ?>
              <div class="helpdesk_empty ram_normal zaobleni_10" data-cb-hd-empty="1">Zatím bez záznamu.</div>
            <?php else: ?>
              <?php foreach ($visibleItems as $item): ?>
                <?php
                $ticketAgeMinutes = max(0, (int)($item['stari_minut'] ?? 0));
                $ticketAgeLabel = $ticketAgeMinutes < 1440
                    ? intdiv($ticketAgeMinutes, 60) . ':' . str_pad((string)($ticketAgeMinutes % 60), 2, '0', STR_PAD_LEFT)
                    : intdiv($ticketAgeMinutes, 1440) . ' d';
                $ticketViewCount = max(0, (int)($item['pocet_zobrazeni'] ?? 0));
                $ticketUniqueViewCount = max(0, (int)($item['pocet_unikatnich_zobrazeni'] ?? 0));
                ?>
                <article class="helpdesk_ticket_item helpdesk_ticket_area_<?= cb_helpdesk_ticket_h((string)(int)$item['modul']) ?> ram_normal zaobleni_10" data-hd-item="<?= cb_helpdesk_ticket_h((string)(int)$item['id_helpdesk']) ?>" data-hd-owner-id="<?= (int)($item['id_user_zalozil'] ?? 0) ?>" data-hd-watched="<?= (int)($item['is_watched'] ?? 0) === 1 ? '1' : '0' ?>" data-hd-stav="<?= cb_helpdesk_ticket_h((string)$item['stav']) ?>" data-hd-filtr="<?= cb_helpdesk_ticket_h((string)$item['stav'] === 'vyřešeno' ? 'uzavřené' : (trim((string)$item['stav']) === 'řeší se' ? 'řeší se' : 'nový')) ?>" data-hd-has-new-reply="<?= (int)($item['has_new_reply'] ?? 0) === 1 ? '1' : '0' ?>">
                  <div class="helpdesk_ticket_row">
                    <div class="helpdesk_ticket_body">
                      <div class="helpdesk_ticket_head">
                        <div class="helpdesk_ticket_title_group">
                          <strong class="helpdesk_ticket_number"><?= cb_helpdesk_ticket_h('Tiket č.: ' . (string)(int)$item['id_helpdesk']) ?></strong>
                          <strong class="helpdesk_ticket_subject"><?= cb_helpdesk_ticket_h((string)$item['predmet']) ?></strong>
                        </div>
                        <div class="helpdesk_ticket_meta">
                          <strong class="helpdesk_ticket_author <?= cb_helpdesk_ticket_h(cb_helpdesk_ticket_author_class($item)) ?>"><?= cb_helpdesk_ticket_h(cb_helpdesk_ticket_author($item)) ?></strong>
                          <div class="helpdesk_ticket_metrics">
                            <span class="helpdesk_ticket_age" title="Stáří tiketu"><?= cb_helpdesk_ticket_h($ticketAgeLabel) ?></span>
                            <span class="helpdesk_ticket_unique_views" title="Počet unikátních uživatelů, kteří otevřeli detail" aria-label="Unikátní uživatelé: <?= $ticketUniqueViewCount ?>">👤 <span data-hd-unique-view-count="1"><?= $ticketUniqueViewCount ?></span></span>
                            <span class="helpdesk_ticket_views" title="Celkový počet otevření detailu" aria-label="Celkový počet otevření detailu: <?= $ticketViewCount ?>"><span data-hd-view-count="1"><?= $ticketViewCount ?></span></span>
                          </div>
                          <span class="helpdesk_ticket_counter">
                            <span class="helpdesk_ticket_bell_wrap" data-hd-bell-wrap="1"><?= (int)($item['has_new_reply'] ?? 0) === 1 ? '<span class="helpdesk_ticket_bell helpdesk_state_unread" data-hd-bell="1" title="Nová reakce" aria-label="Nová reakce"></span>' : '<span class="helpdesk_ticket_bell" data-hd-bell="0" title="Bez nové reakce" aria-label="Bez nové reakce"></span>' ?></span>
                            <span class="helpdesk_ticket_count"><?= cb_helpdesk_ticket_h((string)max(1, (int)($item['pocet_zprav'] ?? 0))) ?></span>
                          </span>
                        </div>
                      </div>
                      <div class="helpdesk_ticket_desc">
                        <div class="helpdesk_ticket_badges">
                          <span class="helpdesk_ticket_badge helpdesk_ticket_badge_status"><span data-hd-state-text="1"><?= cb_helpdesk_ticket_h((string)$item['stav']) ?></span></span>
                          <span class="helpdesk_ticket_badge"><?= cb_helpdesk_ticket_h(cb_helpdesk_area_label((int)$item['modul'])) ?></span>
                          <span class="helpdesk_ticket_badge"><?= cb_helpdesk_ticket_h(cb_helpdesk_ticket_type_label((string)$item['typ'])) ?></span>
                          <span class="helpdesk_ticket_badge"><?= cb_helpdesk_ticket_h(cb_helpdesk_ticket_visibility_label((int)$item['verejny'])) ?></span>
                        </div>
                      </div>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>
  </div>
  <div class="helpdesk_detail_col">
      <div class="helpdesk_scroll helpdesk_detail_scroll">
        <div class="helpdesk_detail_panel" data-cb-hd-detail-panel="1"></div>
      </div>
  </div>
</section>
<?php endif; ?>
</section>
