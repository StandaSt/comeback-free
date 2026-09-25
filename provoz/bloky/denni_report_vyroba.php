<?php
// bloky/denni_report_vyroba.php * Samostatný formulář denního reportu výroby.
declare(strict_types=1);

require_once __DIR__ . '/../lib/format_datum_cas.php';
require_once __DIR__ . '/../lib/denni_report_vyroba.php';

$vyrobaConn = db();
$vyrobaSessionUser = $_SESSION['cb_user'] ?? [];
$vyrobaActorId = is_array($vyrobaSessionUser) ? (int)($vyrobaSessionUser['id_user'] ?? 0) : 0;
$vyrobaAllowedBranches = cb_vyroba_allowed_branches($vyrobaConn, $vyrobaActorId);
$vyrobaAllowed = isset($vyrobaAllowedBranches[CB_VYROBA_ID_POB]);
$vyrobaCurrentWorkday = cb_denni_report_current_workday_date();
$vyrobaCurrentDate = $vyrobaCurrentWorkday->format('Y-m-d');
$vyrobaDate = trim((string)($_POST['datum_reportu'] ?? $_GET['datum_reportu'] ?? $vyrobaCurrentDate));
if (!cb_vyroba_valid_date($vyrobaDate) || $vyrobaDate > $vyrobaCurrentDate) {
    $vyrobaDate = $vyrobaCurrentDate;
}
$vyrobaDayOptions = cb_denni_report_workday_options($vyrobaCurrentWorkday);
if (!in_array($vyrobaDate, array_column($vyrobaDayOptions, 'value'), true)) {
    $vyrobaArchiveDate = DateTimeImmutable::createFromFormat('!Y-m-d', $vyrobaDate);
    if ($vyrobaArchiveDate instanceof DateTimeImmutable) {
        $vyrobaDayOptions[] = [
            'value' => $vyrobaDate,
            'label' => cb_dt_weekday_date_label_cs($vyrobaArchiveDate, true),
        ];
    }
}
if ($vyrobaAllowed) {
    foreach ($vyrobaDayOptions as $index => $option) {
        $optionDate = (string)($option['value'] ?? '');
        if ($optionDate !== $vyrobaCurrentDate && !cb_denni_report_has_active_branch_report($vyrobaConn, CB_VYROBA_ID_POB, $optionDate)) {
            $vyrobaDayOptions[$index]['missing'] = true;
            $vyrobaDayOptions[$index]['label'] = (string)$option['label'] . ' (nezadán)';
        }
    }
}
$vyrobaHistory = $vyrobaAllowed ? cb_denni_report_history_load($vyrobaConn, CB_VYROBA_ID_POB, $vyrobaDate) : null;
$vyrobaReport = (array)($vyrobaHistory['report'] ?? []);
$vyrobaExistingId = (int)($vyrobaReport['id_reportu'] ?? 0);
$vyrobaIsGoogle = $vyrobaExistingId > 0 && (int)($vyrobaReport['zdroj'] ?? 0) === 1;
$vyrobaCanCreate = cb_denni_report_ma_pravo(CB_DENNI_REPORT_UZAVRIT_PRAVO) && $vyrobaDate <= date('Y-m-d');
$vyrobaCanEdit = !$vyrobaIsGoogle && $vyrobaExistingId > 0
    && cb_denni_report_ma_pravo(CB_DENNI_REPORT_EDITOVAT_PRAVO)
    && cb_vyroba_user_main_branch($vyrobaConn, $vyrobaActorId);
$vyrobaEditRequested = (int)($_GET['zr_edit_final'] ?? 0) === 1;
$vyrobaEditable = $vyrobaAllowed && (($vyrobaExistingId === 0 && $vyrobaCanCreate) || ($vyrobaCanEdit && $vyrobaEditRequested));
$vyrobaFormPost = $vyrobaEditable && isset($_POST['vyroba_report_save']) ? $_POST : [];
$vyrobaOptions = $vyrobaAllowed ? cb_vyroba_people_options($vyrobaConn) : [];
$vyrobaOptionNames = array_column($vyrobaOptions, 'name', 'id_person');
$vyrobaRows = [];
foreach ((array)($vyrobaHistory['people_rows'] ?? []) as $row) {
    $vyrobaRows[] = [
        'id_person' => (int)($row['id_user'] ?? 0),
        'name' => trim((string)($row['prijmeni'] ?? '') . ' ' . (string)($row['jmeno'] ?? '')),
        'start' => substr((string)($row['smena_od'] ?? ''), 0, 5),
        'end' => substr((string)($row['smena_do'] ?? ''), 0, 5),
        'pause' => (string)($row['pauza'] ?? '0'),
        'hours' => (string)($row['odpracovano'] ?? '0'),
    ];
}
if ($vyrobaFormPost !== []) {
    $postedIds = is_array($vyrobaFormPost['vyroba_id_person'] ?? null) ? $vyrobaFormPost['vyroba_id_person'] : [];
    $vyrobaRows = [];
    foreach ($postedIds as $index => $idRaw) {
        $id = (int)$idRaw;
        $vyrobaRows[] = [
            'id_person' => $id,
            'name' => (string)($vyrobaOptionNames[$id] ?? ''),
            'start' => trim((string)($vyrobaFormPost['vyroba_zacatek'][$index] ?? '')),
            'end' => trim((string)($vyrobaFormPost['vyroba_konec'][$index] ?? '')),
            'pause' => trim((string)($vyrobaFormPost['vyroba_pauza'][$index] ?? '')),
            'hours' => '',
        ];
    }
}
$vyrobaFuel = (string)($vyrobaFormPost['vyroba_palivo'] ?? $vyrobaReport['vydaje_auta'] ?? '');
$vyrobaIngredients = (string)($vyrobaFormPost['vyroba_suroviny'] ?? $vyrobaReport['vydaje_suroviny'] ?? '');
$vyrobaOther = (string)($vyrobaFormPost['vyroba_ostatni'] ?? $vyrobaReport['vydaje_ostatni'] ?? '');
$vyrobaNote = (string)($vyrobaFormPost['vyroba_vzkaz'] ?? $vyrobaReport['poznamka'] ?? '');
$vyrobaUrl = cb_root_url('index.php') . '?m=provoz&page=denni_report&zr_id_pob=7&datum_reportu=' . rawurlencode($vyrobaDate);
$vyrobaUsedIds = [];
foreach ($vyrobaRows as $person) {
    $id = (int)$person['id_person'];
    if ($id > 0) {
        $vyrobaUsedIds[$id] = true;
    }
}
$vyrobaRenderOptions = static function () use ($vyrobaOptions, $vyrobaUsedIds): string {
    $html = '<option value="">Vyber pracovníka</option>';
    foreach ($vyrobaOptions as $option) {
        $id = (int)$option['id_person'];
        if (!isset($vyrobaUsedIds[$id])) {
            $html .= '<option value="' . h((string)$id) . '">' . h($option['name']) . '</option>';
        }
    }
    return $html;
};
?>
<div class="provoz_denni_report_page">
  <div class="vyroba_report">
    <?php if (!$vyrobaAllowed): ?>
      <p class="zr_readonly_info">Výroba není přiřazená k tvému profilu v HR. Přístup k jejím reportům se nastavuje v HR.</p>
    <?php else: ?>
      <?php if (trim((string)($GLOBALS['cbVyrobaFormError'] ?? '')) !== ''): ?>
        <p class="zr_readonly_info txt_cervena" role="alert"><?= h((string)$GLOBALS['cbVyrobaFormError']) ?></p>
      <?php elseif ((string)($_GET['vyroba_saved'] ?? '') === '1'): ?>
        <p class="zr_readonly_info" role="status">Report výroby byl uložen.</p>
      <?php endif; ?>

      <?php if ($vyrobaIsGoogle): ?>
        <p class="zr_readonly_info">Historický report z Google · pouze ke čtení.</p>
      <?php elseif ($vyrobaExistingId > 0 && !$vyrobaEditable): ?>
        <p class="zr_readonly_info">Report uložený v IS.</p>
        <?php if ($vyrobaCanEdit): ?><p><a class="head_task_btn" href="<?= h($vyrobaUrl . '&zr_edit_final=1') ?>">Editovat tento report</a></p><?php endif; ?>
      <?php endif; ?>

      <div class="vyroba_report_grid">
        <form class="vyroba_report_filters card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section" method="get" action="<?= h(cb_root_url('index.php')) ?>">
          <input type="hidden" name="m" value="provoz">
          <input type="hidden" name="page" value="denni_report">
          <label><span>Pobočka</span>
            <select name="zr_id_pob" data-vyroba-filter>
              <?php foreach ($vyrobaAllowedBranches as $branchId => $branchName): ?>
                <option value="<?= h((string)$branchId) ?>"<?= $branchId === CB_VYROBA_ID_POB ? ' selected' : '' ?>><?= h($branchName) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label><span>Datum</span><select name="datum_reportu" data-vyroba-filter>
            <?php foreach ($vyrobaDayOptions as $dayOption): ?>
              <option value="<?= h((string)$dayOption['value']) ?>"<?= (string)$dayOption['value'] === $vyrobaDate ? ' selected' : '' ?><?= !empty($dayOption['missing']) ? ' style="color:#c62828;"' : '' ?>><?= h((string)$dayOption['label']) ?></option>
            <?php endforeach; ?>
          </select></label>
        </form>

      <?php if (!$vyrobaEditable && $vyrobaExistingId <= 0): ?>
        <p class="zr_readonly_info vyroba_report_notice">Pro tento den není report výroby uložen.<?= $vyrobaOptions === [] ? ' Nejdřív přiřaď pracovníky k Výrobě v HR.' : '' ?></p>
      <?php elseif ($vyrobaEditable): ?>
        <form class="vyroba_report_form" method="post" action="<?= h($vyrobaUrl . ($vyrobaEditRequested ? '&zr_edit_final=1' : '')) ?>" data-vyroba-form>
          <input type="hidden" name="vyroba_report_save" value="1">
          <input type="hidden" name="id_pob" value="7">
          <input type="hidden" name="zr_id_pob" value="7">
          <input type="hidden" name="datum_reportu" value="<?= h($vyrobaDate) ?>">
          <input type="hidden" name="vyroba_expected_report_id" value="<?= h((string)$vyrobaExistingId) ?>">
          <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section vyroba_report_expense_section">
            <h4 class="card_section_title txt_seda">Výdaje</h4>
            <div class="vyroba_report_expenses">
              <label>Benzín / nafta (Kč) <input type="text" inputmode="decimal" name="vyroba_palivo" value="<?= h($vyrobaFuel) ?>"></label>
              <label>Suroviny (Kč) <input type="text" inputmode="decimal" name="vyroba_suroviny" value="<?= h($vyrobaIngredients) ?>"></label>
              <label>Ostatní (Kč) <input type="text" inputmode="decimal" name="vyroba_ostatni" value="<?= h($vyrobaOther) ?>"></label>
            </div>
          </section>
          <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section vyroba_report_people_section">
            <h4 class="card_section_title txt_seda">Směny výroby</h4>
            <?php if ($vyrobaOptions === []): ?><p class="card_text">V HR zatím není k Výrobě přiřazen žádný aktivní pracovník.</p><?php endif; ?>
            <div class="vyroba_report_person_picker"><select data-vyroba-add-person aria-label="Vyber pracovníka výroby"<?= $vyrobaOptions === [] ? ' disabled' : '' ?>><?= $vyrobaRenderOptions() ?></select></div>
            <div class="vyroba_report_table_wrap"><table class="zr_table zr_person_table vyroba_report_table"><thead><tr><th>Pracovník</th><th>Směna od</th><th>Směna do</th><th>Pauza</th><th>Odprac.</th><th></th></tr></thead><tbody data-vyroba-rows>
              <?php foreach ($vyrobaRows as $person): ?>
                <?php if ((int)$person['id_person'] <= 0) continue; ?>
                <tr data-vyroba-row>
                  <td><button class="zr_row_remove" type="button" data-vyroba-remove title="Odebrat pracovníka" aria-label="Odebrat pracovníka">×</button><strong class="zr_saved_value" data-vyroba-person-name><?= h((string)$person['name']) ?></strong><input type="hidden" name="vyroba_id_person[]" value="<?= h((string)$person['id_person']) ?>"></td>
                  <td><input class="zr_time_input" type="text" inputmode="numeric" name="vyroba_zacatek[]" value="<?= h((string)$person['start']) ?>" required data-vyroba-time data-vyroba-start></td>
                  <td><input class="zr_time_input" type="text" inputmode="numeric" name="vyroba_konec[]" value="<?= h((string)$person['end']) ?>" required data-vyroba-time data-vyroba-end></td>
                  <td class="zr_person_cell_break"><input type="text" inputmode="decimal" name="vyroba_pauza[]" value="<?= h((string)$person['pause']) ?>" data-vyroba-pause></td>
                  <td><strong class="zr_saved_value" data-vyroba-hours><?= h((string)$person['hours']) ?> hod.</strong></td><td></td>
                </tr>
              <?php endforeach; ?>
            </tbody></table></div>
          </section>
          <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section vyroba_report_note_section">
            <label for="vyroba_vzkaz">Vzkaz pro další směnu nebo manažera</label>
            <textarea id="vyroba_vzkaz" name="vyroba_vzkaz" rows="2"><?= h($vyrobaNote) ?></textarea>
          </section>
          <button class="zr_submit" type="submit"<?= $vyrobaOptions === [] && $vyrobaExistingId === 0 ? ' disabled' : '' ?>><?= $vyrobaExistingId > 0 ? 'Uložit opravený report' : 'Uložit report výroby' ?></button>
        </form>
        <template data-vyroba-template><tr data-vyroba-row><td><button class="zr_row_remove" type="button" data-vyroba-remove title="Odebrat pracovníka" aria-label="Odebrat pracovníka">×</button><strong class="zr_saved_value" data-vyroba-person-name></strong><input type="hidden" name="vyroba_id_person[]"></td><td><input class="zr_time_input" type="text" inputmode="numeric" name="vyroba_zacatek[]" required data-vyroba-time data-vyroba-start></td><td><input class="zr_time_input" type="text" inputmode="numeric" name="vyroba_konec[]" required data-vyroba-time data-vyroba-end></td><td class="zr_person_cell_break"><input type="text" inputmode="decimal" name="vyroba_pauza[]" data-vyroba-pause></td><td><strong class="zr_saved_value" data-vyroba-hours>—</strong></td><td></td></tr></template>
      <?php else: ?>
        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section vyroba_report_expense_section">
          <h4 class="card_section_title txt_seda">Výdaje</h4>
          <div class="vyroba_report_expenses vyroba_report_expenses--readonly">
            <span>Benzín / nafta <strong><?= h(cb_format('p', $vyrobaFuel)) ?></strong></span>
            <span>Suroviny <strong><?= h(cb_format('p', $vyrobaIngredients)) ?></strong></span>
            <span>Ostatní <strong><?= h(cb_format('p', $vyrobaOther)) ?></strong></span>
          </div>
        </section>
        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section vyroba_report_people_section">
          <h4 class="card_section_title txt_seda">Směny výroby</h4>
          <div class="vyroba_report_table_wrap"><table class="zr_table zr_person_table vyroba_report_table"><thead><tr><th>Pracovník</th><th>Směna od</th><th>Směna do</th><th>Pauza</th><th>Odprac.</th></tr></thead><tbody>
            <?php foreach ($vyrobaRows as $person): ?><tr><td><?= h((string)($person['name'] !== '' ? $person['name'] : 'Neznámá osoba')) ?></td><td><?= h((string)$person['start']) ?></td><td><?= h((string)$person['end']) ?></td><td><?= h((string)$person['pause']) ?> hod.</td><td><?= h((string)$person['hours']) ?> hod.</td></tr><?php endforeach; ?>
          </tbody></table></div>
        </section>
        <?php if (trim($vyrobaNote) !== ''): ?><section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section vyroba_report_note_section"><h4 class="card_section_title txt_seda">Vzkaz pro další směnu nebo manažera</h4><p><?= nl2br(h($vyrobaNote)) ?></p></section><?php endif; ?>
      <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
