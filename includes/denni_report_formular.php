<?php
// includes/denni_report_formular.php * K10 formular denniho reportu
declare(strict_types=1);

$zrEditableDisabledAttr = !empty($isReadOnlyForm) ? ' disabled' : '';
$zrEditableReadonlyAttr = !empty($isReadOnlyForm) ? ' readonly' : '';
$zrReadonlyInfoText = trim((string)($readonlyInfoText ?? ''));
$zrUsesDraftMode = !empty($usesDraftPersistence) ? '1' : '0';
$zrFinalFullMode = !empty($isEditingFinalReport) ? '1' : '0';
$zrRozvozSazba = max(0, (int)($zrRozvozSazba ?? 0));
$zrCanUnlockFinalReport = !empty($canUnlockFinalReport);
$zrIsEditingFinalReport = !empty($isEditingFinalReport);
$zrSubmitReadyText = $zrIsEditingFinalReport ? 'Chci uložit opravený report' : 'Report je zkontrolovaný, uložit';
$zrSubmitLockedText = $zrIsEditingFinalReport ? 'Chci uložit opravený report' : 'Report bude možné uložit za';
$zrRemoveButtonHtml = !empty($isReadOnlyForm)
    ? ''
    : '<button type="button" class="zr_row_remove" data-zr-remove-row title="Odebrat" aria-label="Odebrat">×</button>';

$renderUserSelectOptions = static function (array $options, int $selectedId, string $placeholder, array $excludeIds = []): string {
    $html = '<option value="">' . h($placeholder) . '</option>';
    $exclude = array_fill_keys(array_map('intval', $excludeIds), true);
    foreach ($options as $option) {
        $idUser = (int)($option['id_user'] ?? 0);
        $name = trim((string)($option['name'] ?? ''));
        $restiaName = trim((string)($option['restia_name'] ?? ''));
        if ($idUser <= 0 || $name === '' || isset($exclude[$idUser])) {
            continue;
        }
        $restiaAttr = $restiaName !== '' ? ' data-zr-restia-name="' . h($restiaName) . '"' : '';
        $html .= '<option value="' . h((string)$idUser) . '"' . $restiaAttr . ($idUser === $selectedId ? ' selected' : '') . '>' . h($name) . '</option>';
    }
    return $html;
};

$renderTimeInput = static function (string $name, string $selected, string $dataAttr, string $extraAttr = ''): string {
    $attrName = trim($name) !== '' ? ' name="' . h($name) . '"' : '';
    $attrExtra = trim($extraAttr) !== '' ? ' ' . trim($extraAttr) : '';

    return '<input class="zr_time_input" type="text" inputmode="numeric"' . $attrName . ' value="' . h($selected) . '" style="width:100%;text-align:center;" ' . $dataAttr . $attrExtra . '>';
};

$renderInstorSavedRow = static function (array $row, callable $renderTimeInput) use ($zrEditableDisabledAttr, $zrEditableReadonlyAttr, $zrRemoveButtonHtml): string {
    $idDrOsoby = (int)($row['id_dr_osoby'] ?? 0);
    $idUser = (int)($row['id_user'] ?? 0);
    $name = trim((string)($row['name'] ?? ''));
    $start = trim((string)($row['start'] ?? ''));
    $end = trim((string)($row['end'] ?? ''));
    $break = trim((string)($row['break'] ?? '0'));
    $hours = trim((string)($row['hours'] ?? '0'));

    return ''
        . '<tr data-zr-person-row="instor" data-zr-id-user="' . h((string)$idUser) . '" data-zr-id-dr-osoby="' . h((string)$idDrOsoby) . '">'
        . '<td style="width:220px;">' . $zrRemoveButtonHtml . '<strong class="zr_saved_value">' . h($name) . '</strong>'
        . '<input type="hidden" name="instor_jmeno[]" value="' . h($name) . '">'
        . '<input type="hidden" name="instor_id_user[]" value="' . h((string)$idUser) . '">'
        . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('instor_zacatek[]', $start, 'data-zr-start', trim($zrEditableDisabledAttr . ' ' . $zrEditableReadonlyAttr)) . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('instor_konec[]', $end, 'data-zr-end', trim($zrEditableDisabledAttr . ' ' . $zrEditableReadonlyAttr)) . '</td>'
        . '<td class="zr_person_cell_break" style="width:44px;"><input type="text" inputmode="decimal" name="instor_pauza_hod[]" value="' . h($break) . '" style="width:100%;text-align:center;" data-zr-break' . $zrEditableReadonlyAttr . $zrEditableDisabledAttr . '></td>'
        . '<td style="width:70px;"><strong class="zr_saved_value" data-zr-hours>' . h($hours) . ' hod.</strong><input type="hidden" name="instor_hodiny[]" value="' . h($hours) . '" data-zr-hours-hidden></td>'
        . '<td></td>'
        . '</tr>';
};

$renderKuryrSavedRow = static function (array $row, callable $renderTimeInput) use ($zrEditableDisabledAttr, $zrEditableReadonlyAttr, $zrRemoveButtonHtml): string {
    $idDrOsoby = (int)($row['id_dr_osoby'] ?? 0);
    $idUser = (int)($row['id_user'] ?? 0);
    $name = trim((string)($row['name'] ?? ''));
    $restiaName = trim((string)($row['restia_name'] ?? ''));
    $start = trim((string)($row['start'] ?? ''));
    $end = trim((string)($row['end'] ?? ''));
    $break = trim((string)($row['break'] ?? '0'));
    $hours = trim((string)($row['hours'] ?? '0'));
    $deliveryRestia = (int)($row['delivery_restia'] ?? 0);
    $deliveryManual = (int)($row['delivery_manual'] ?? 0);
    $deliveryTotal = (int)($row['delivery_total'] ?? ($deliveryRestia + $deliveryManual));
    $car = (int)($row['car'] ?? 0);
    $phm = (float)($row['phm'] ?? 0);

    return ''
        . '<tr data-zr-person-row="kuryr" data-zr-id-user="' . h((string)$idUser) . '" data-zr-id-dr-osoby="' . h((string)$idDrOsoby) . '"' . ($restiaName !== '' ? ' data-zr-restia-name="' . h($restiaName) . '"' : '') . '>'
        . '<td style="width:220px;">' . $zrRemoveButtonHtml . '<strong class="zr_saved_value">' . h($name) . '</strong>'
        . '<input type="hidden" name="kuryr_jmeno[]" value="' . h($name) . '">'
        . '<input type="hidden" name="kuryr_id_user[]" value="' . h((string)$idUser) . '">'
        . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('kuryr_zacatek[]', $start, 'data-zr-start', trim($zrEditableDisabledAttr . ' ' . $zrEditableReadonlyAttr)) . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('kuryr_konec[]', $end, 'data-zr-end', trim($zrEditableDisabledAttr . ' ' . $zrEditableReadonlyAttr)) . '</td>'
        . '<td class="zr_person_cell_break" style="width:44px;"><input type="text" inputmode="decimal" name="kuryr_pauza_hod[]" value="' . h($break) . '" style="width:100%;text-align:center;" data-zr-break' . $zrEditableReadonlyAttr . $zrEditableDisabledAttr . '></td>'
        . '<td style="width:70px;"><strong class="zr_saved_value" data-zr-hours>' . h($hours) . ' hod.</strong><input type="hidden" name="kuryr_hodiny[]" value="' . h($hours) . '" data-zr-hours-hidden></td>'
        . '<td class="txt_c" style="width:48px;"><strong class="zr_saved_value" data-zr-delivery-restia-value>' . h((string)$deliveryRestia) . '</strong><input type="hidden" name="kuryr_pocet_rozvozu_restia[]" value="' . h((string)$deliveryRestia) . '" data-zr-editor-field="delivery_restia"></td>'
        . '<td class="txt_c" style="width:48px;"><input class="zr_delivery_input txt_c" type="text" inputmode="numeric" name="kuryr_pocet_rozvozu_manual[]" value="' . h((string)$deliveryManual) . '" style="width:100%;" data-zr-editor-field="delivery_manual" data-zr-int-short' . $zrEditableReadonlyAttr . $zrEditableDisabledAttr . '>'
        . '<input type="hidden" name="kuryr_pocet_rozvozu[]" value="' . h((string)$deliveryTotal) . '" data-zr-delivery-total>'
        . '</td>'
        . '<td class="txt_c" style="width:34px;"><span class="zr_chk txt_c zr_person_cell_car zr_person_cell_car_inline"><input type="checkbox" value="1"' . ($car === 1 ? ' checked' : '') . ' data-zr-editor-field="car" data-zr-car-check' . $zrEditableDisabledAttr . '></span><input type="hidden" name="kuryr_vlastni_vuz[]" value="' . h((string)$car) . '" data-zr-car-hidden></td>'
        . '<td><strong class="zr_saved_value" data-zr-phm-value>' . h(cb_denni_report_format_money($phm)) . '</strong><input type="hidden" name="kuryr_vyplatit_phm[]" value="' . h(number_format($phm, 2, '.', '')) . '" data-zr-phm-hidden></td>'
        . '</tr>';
};

?>
<?php if (!empty($missingHistoryReport)): ?>
  <div class="zr_missing_report_bar"><?= h((string)($missingHistoryReportText ?? 'Tento report není zadán')) ?></div>
<?php endif; ?>
<?php if ($zrReadonlyInfoText !== ''): ?>
  <div class="zr_readonly_info">
    <?= h($zrReadonlyInfoText) ?>
  </div>
<?php endif; ?>
<form class="zr_form gap_14" autocomplete="off" method="post" action="<?= h(cb_url('/')) ?>" data-zr-form data-zr-draft-mode="<?= h($zrUsesDraftMode) ?>" data-zr-final-full="<?= h($zrFinalFullMode) ?>" data-zr-form-mode="<?= h((string)($formMode ?? 'workday')) ?>" data-zr-readonly="<?= !empty($isReadOnlyForm) ? '1' : '0' ?>" data-zr-rozvoz-sazba="<?= h((string)$zrRozvozSazba) ?>" data-cb-max-form="1" data-cb-loader-text="Načítám report pobočky" style="position:relative;">
  <input type="hidden" name="dr_id" value="<?= h((string)$idDr) ?>" data-zr-dr-id>
  <input type="hidden" name="zr_edit_final" value="<?= $zrIsEditingFinalReport ? '1' : '0' ?>" data-zr-edit-final>
  <div class="zr_layout gap_14">
    <div class="zr_main gap_14">
      <section class="zr_top gap_14">
        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_intro_section">
          <table class="zr_table">
            <tbody>
              <tr>
                <th class="zr_intro_label txt_l">Pobočka</th>
                <td>
                  <?php if ($singleAllowedBranchName !== ''): ?>
                    <span class="text_tucny text_14"><?= h($singleAllowedBranchName) ?></span>
                    <input type="hidden" name="zr_id_pob" value="<?= h((string)$reportBranchId) ?>">
                  <?php else: ?>
                    <select class="zr_intro_select" name="zr_id_pob" onchange="if(this.form){this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit();}">
                      <option value=""><?= h('Vyber pobočku') ?></option>
                      <?php foreach ($allowedBranches as $branchId => $allowedBranchName): ?>
                        <option value="<?= h((string)$branchId) ?>"<?= (int)$branchId === $reportBranchId ? ' selected' : '' ?>><?= h((string)$allowedBranchName) ?></option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                  <input type="hidden" name="id_pob" value="<?= h((string)$reportBranchId) ?>">
                </td>
              </tr>
              <tr>
                <th class="zr_intro_label zr_req_label txt_l" data-zr-required-label="datum">Datum</th>
                <td>
                  <select class="zr_intro_select" name="datum_reportu" data-zr-date data-zr-required="datum" onchange="if(this.form){this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit();}">
                    <?php foreach (($workdayOptions ?? []) as $dayOption): ?>
                      <option value="<?= h((string)$dayOption['value']) ?>"<?= ((string)$dayOption['value'] === (string)$reportDate) ? ' selected' : '' ?>><?= h((string)$dayOption['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="zr_intro_label zr_req_label txt_l" data-zr-required-label="oteviral">Otevíral</th>
                <td>
                  <select class="zr_intro_select" name="oteviral" data-zr-field="oteviral" data-zr-required="oteviral"<?= $zrEditableDisabledAttr ?>>
                    <?= $renderUserSelectOptions($instorOptions, $openingId, 'Vyber jméno') ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="zr_intro_label zr_req_label txt_l" data-zr-required-label="zaviral">Zavíral</th>
                <td>
                  <select class="zr_intro_select" name="zaviral" data-zr-field="zaviral" data-zr-required="zaviral"<?= $zrEditableDisabledAttr ?>>
                    <?= $renderUserSelectOptions($instorOptions, $closingId, 'Vyber jméno') ?>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
        </section>

        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_cash_section">
          <h4 class="card_section_title txt_seda">Pokladna a výdaje</h4>
          <table class="zr_table zr_cash_table">
            <tbody>
              <tr>
                <th class="zr_req_label txt_l" data-zr-required-label="pokladna_hotovost">Hotovost</th>
                <th class="zr_req_label txt_l" data-zr-required-label="pokladna_terminal">Terminal</th>
                <th class="zr_req_label txt_l" data-zr-required-label="pokladna_stravenky">Stravenky</th>
                <th class="txt_l">Benzín</th>
              </tr>
              <tr>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="pokladna_hotovost" value="<?= h($cashData['hotovost']) ?>" data-zr-field="pokladna_hotovost" data-zr-money="int" data-zr-required="pokladna_hotovost"<?= $zrEditableReadonlyAttr . $zrEditableDisabledAttr ?>></td>
                <td><input class="zr_money_input" type="text" inputmode="decimal" name="pokladna_terminal" value="<?= h($cashData['terminal']) ?>" data-zr-field="pokladna_terminal" data-zr-money="decimal" data-zr-required="pokladna_terminal"<?= $zrEditableReadonlyAttr . $zrEditableDisabledAttr ?>></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="pokladna_stravenky" value="<?= h($cashData['stravenky']) ?>" data-zr-field="pokladna_stravenky" data-zr-money="int" data-zr-required="pokladna_stravenky"<?= $zrEditableReadonlyAttr . $zrEditableDisabledAttr ?>></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_benzin" value="<?= h($cashData['vydaje_benzin']) ?>" data-zr-field="vydaje_benzin" data-zr-money="int" data-zr-required="vydaje_benzin"<?= $zrEditableReadonlyAttr . $zrEditableDisabledAttr ?>></td>
              </tr>
              <tr>
                <th class="txt_l">Auta</th>
                <th class="txt_l">Suroviny</th>
                <th class="txt_l">Ostatní</th>
                <th class="txt_l">PHM-soukr.</th>
              </tr>
              <tr>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_auta" value="<?= h($cashData['vydaje_auta']) ?>" data-zr-field="vydaje_auta" data-zr-money="int" data-zr-required="vydaje_auta"<?= $zrEditableReadonlyAttr . $zrEditableDisabledAttr ?>></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_suroviny" value="<?= h($cashData['vydaje_suroviny']) ?>" data-zr-field="vydaje_suroviny" data-zr-money="int" data-zr-required="vydaje_suroviny"<?= $zrEditableReadonlyAttr . $zrEditableDisabledAttr ?>></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_ostatni" value="<?= h($cashData['vydaje_ostatni']) ?>" data-zr-field="vydaje_ostatni" data-zr-money="int" data-zr-required="vydaje_ostatni"<?= $zrEditableReadonlyAttr . $zrEditableDisabledAttr ?>></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_phm_soukrome" value="<?= h($cashData['vydaje_phm_soukrome']) ?>" data-zr-field="vydaje_phm_soukrome" data-zr-money="int" data-zr-required="vydaje_phm_soukrome" readonly<?= $zrEditableDisabledAttr ?>></td>
              </tr>
            </tbody>
          </table>
        </section>
      </section>

      <div class="zr_left gap_14">
        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_instor_section">
          <h4 class="card_section_title txt_seda">Instor</h4>
          <div style="width:220px;margin-bottom:6px;">
            <select data-zr-add-person="instor"<?= $zrEditableDisabledAttr ?>>
              <?= $renderUserSelectOptions($instorOptions, 0, 'Vyber zaměstnance', $usedInstorIds) ?>
            </select>
          </div>
          <table class="zr_table zr_person_table" style="width:100%;" data-zr-people-list="instor">
            <thead>
              <tr>
                <th class="zr_req_label txt_l" style="width:220px;white-space:nowrap;" data-zr-required-label="instor_jmeno">Instor</th>
                <th class="zr_req_label txt_l" style="width:58px;white-space:nowrap;" data-zr-required-label="instor_zacatek">Směna od</th>
                <th class="zr_req_label txt_l" style="width:58px;white-space:nowrap;" data-zr-required-label="instor_konec">Směna do</th>
                <th class="txt_l" style="width:44px;white-space:nowrap;">Pauza</th>
                <th class="txt_l" style="width:70px;white-space:nowrap;">Odprac.</th>
                <th class="txt_l" style="white-space:nowrap;"></th>
              </tr>
            </thead>
            <tbody data-zr-saved-list="instor">
              <?php foreach ($instorRows as $row): ?>
                <?= $renderInstorSavedRow($row, $renderTimeInput) ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>

        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_control_section">
          <strong class="zr_control_label zr_control_label_rozdil">Rozdíl pokladna</strong>
          <div class="zr_control_metric">
            <strong class="zr_control_value" data-zr-report-rozdil><?= h($reportDifferenceLabel) ?></strong>
            <input type="hidden" name="rozdil" value="<?= h($reportDifferenceValue) ?>" data-zr-report-rozdil-value>
          </div>
          <div class="zr_control_metric zr_control_metric_col">
            <span class="cb_tooltip" tabindex="0" aria-label="Informace k COL" data-cb-tooltip-position="1">
              <strong class="zr_control_label zr_control_label_col" style="display:inline-block;margin-bottom:0;cursor:help;">COL</strong>
              <span class="cb_tooltip_panel cb_tooltip_card" data-cb-tooltip-panel="1">
                <span class="cb_tooltip_title">Informativní varianta</span>
                <div class="text_12" data-zr-report-col-bez-dph><?= h($reportColBezDphLabel) ?></div>
              </span>
            </span>
            <strong class="zr_control_value zr_control_value_col" data-zr-report-col><?= h($reportColLabel) ?></strong>
            <input type="hidden" name="col_pomer" value="<?= h($reportColValue) ?>" data-zr-report-col-value>
          </div>
        </section>

        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_kuryr_section">
          <h4 class="card_section_title txt_seda">Kurýr</h4>
          <div style="width:220px;margin-bottom:6px;">
            <select data-zr-add-person="kuryr"<?= $zrEditableDisabledAttr ?>>
              <?= $renderUserSelectOptions($kuryrOptions, 0, 'Vyber kurýra', $usedKuryrIds) ?>
            </select>
          </div>
          <table class="zr_table zr_person_table" style="width:100%;" data-zr-people-list="kuryr" data-zr-delivery-counts="<?= h($kuryrDeliveryCountsJson) ?>">
            <thead>
              <tr>
                <th class="txt_l" style="width:220px;white-space:nowrap;">Kurýr</th>
                <th class="txt_l" style="width:58px;white-space:nowrap;">Směna od</th>
                <th class="txt_l" style="width:58px;white-space:nowrap;">Směna do</th>
                <th class="txt_l" style="width:44px;white-space:nowrap;">Pauza</th>
                <th class="txt_l" style="width:70px;white-space:nowrap;">Odprac.</th>
                <th class="txt_l" style="width:48px;white-space:nowrap;">Rozvozů</th>
                <th class="txt_l" style="width:48px;white-space:nowrap;">Ručně</th>
                <th class="txt_l" style="width:34px;white-space:nowrap;">Vl. vůz</th>
                <th class="txt_l" style="white-space:nowrap;">PHM</th>
              </tr>
            </thead>
            <tbody data-zr-saved-list="kuryr">
              <?php foreach ($kuryrRows as $row): ?>
                <?= $renderKuryrSavedRow($row, $renderTimeInput) ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>
        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_note_section">
          <?php if ($previousNote !== ''): ?>
            <div style="margin-bottom:8px;">
              <div class="txt_seda text_12">Včerejší vzkaz</div>
              <div class="text_14"><?= h($previousNote) ?></div>
            </div>
          <?php endif; ?>
          <label class="txt_seda text_12" for="zr_note">Vzkaz pro další směnu nebo managera</label>
          <input
            id="zr_note"
            type="text"
            name="poznamka"
            value="<?= h($draftNote) ?>"
            data-zr-note
            <?= $zrEditableReadonlyAttr . $zrEditableDisabledAttr ?>
            style="width:100%;margin-top:4px;"
          >
        </section>
        <?php if ($zrCanUnlockFinalReport && !$zrIsEditingFinalReport && $reportBranchId > 0): ?>
          <button
            type="button"
            class="zr_submit"
            data-zr-edit-final-button
            style="background:#c62828;border-color:#b71c1c;color:#fff;cursor:pointer;opacity:1;"
          >Editovat tento report</button>
        <?php elseif (!empty($canEditReport) && $reportBranchId > 0): ?>
          <button
            type="button"
            class="zr_submit"
            disabled
            data-zr-submit
            data-zr-submit-locked-text="<?= h($zrSubmitLockedText) ?>"
            data-zr-submit-ready-text="<?= h($zrSubmitReadyText) ?>"
            data-zr-submit-missing-text="Vyplň všechna povinná data reportu"
            data-zr-submit-at="<?= h((string)($zrIsEditingFinalReport ? 0 : $reportSaveAtTs)) ?>"
            <?php if ($zrIsEditingFinalReport): ?>style="background:#2e7d32;border-color:#1b5e20;color:#fff;cursor:pointer;opacity:1;"<?php else: ?>style="background:#d9dee8;border-color:#c1c9d6;color:#5f6b7a;cursor:not-allowed;opacity:1;"<?php endif; ?>
          ><?= h($zrIsEditingFinalReport ? 'Chci uložit opravený report' : 'Report bude možné uložit za 0:00:00') ?></button>
        <?php endif; ?>
      </div>
    </div>

    <aside class="zr_side gap_14">
      <div class="is-hidden" data-zr-card-subtitle-side="<?= h('Aktualizace v ' . (string)$lastRestiaUpdateLabel) ?>"></div>
      <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_restia_section">
        <div class="zr_restia_head">
          <h4 class="card_section_title txt_seda">Aktuální data z Restie</h4>
          <button
            type="button"
            class="zr_restia_refresh_btn"
            disabled
            data-zr-restia-refresh
            data-zr-restia-refresh-at="<?= h((string)$reportRefreshAtTs) ?>"
            title="Aktualizovat Restii"
            aria-label="Aktualizovat Restii"
          >↻</button>
        </div>
        <div class="zr_restia_total gap_4">
          <span class="zr_metric_label">Tržba</span>
          <strong class="zr_metric_value" data-zr-restia-trzba data-zr-value="<?= h(number_format((float)$restiaSummary['trzba'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['trzba'])) ?></strong>
        </div>
        <table class="zr_table zr_restia_table">
          <thead>
            <tr>
              <th class="zr_restia_key">Položka</th>
              <th class="zr_restia_value txt_r">Obj.</th>
              <th class="zr_restia_value txt_r">Kč</th>
            </tr>
          </thead>
          <tbody>
            <tr><td class="zr_restia_key">Wolt</td><td class="zr_restia_value txt_r"><?= h((string)($restiaSummary['wolt_count'] ?? 0)) ?></td><td class="zr_restia_value txt_r"><strong data-zr-restia-wolt data-zr-value="<?= h(number_format((float)$restiaSummary['wolt'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['wolt'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Bolt</td><td class="zr_restia_value txt_r"><?= h((string)($restiaSummary['bolt_count'] ?? 0)) ?></td><td class="zr_restia_value txt_r"><strong data-zr-restia-bolt data-zr-value="<?= h(number_format((float)$restiaSummary['bolt'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['bolt'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Foodora</td><td class="zr_restia_value txt_r"><?= h((string)($restiaSummary['dj_count'] ?? 0)) ?></td><td class="zr_restia_value txt_r"><strong data-zr-restia-dj data-zr-value="<?= h(number_format((float)$restiaSummary['dj'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['dj'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Web</td><td class="zr_restia_value txt_r"><?= h((string)($restiaSummary['web_count'] ?? 0)) ?></td><td class="zr_restia_value txt_r"><strong data-zr-restia-web data-zr-value="<?= h(number_format((float)$restiaSummary['web'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['web'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Wolt drive cash</td><td class="zr_restia_value txt_r"><?= h((string)($restiaSummary['wolt_cash_count'] ?? 0)) ?></td><td class="zr_restia_value txt_r"><strong data-zr-restia-wolt-cash data-zr-value="<?= h(number_format((float)$restiaSummary['wolt_cash'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['wolt_cash'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">DJ cash</td><td class="zr_restia_value txt_r"><?= h((string)($restiaSummary['dj_cash_count'] ?? 0)) ?></td><td class="zr_restia_value txt_r"><strong data-zr-restia-dj-cash data-zr-value="<?= h(number_format((float)$restiaSummary['dj_cash'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['dj_cash'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Ostatní</td><td class="zr_restia_value txt_r"><?= h((string)($restiaSummary['other_count'] ?? 0)) ?></td><td class="zr_restia_value txt_r"><strong><?= h(cb_denni_report_format_money_whole((float)($restiaSummary['other'] ?? 0))) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Kontrola</td><td class="zr_restia_value txt_r"><?= h((string)($restiaSummary['control_count'] ?? 0)) ?></td><td class="zr_restia_value txt_r"><strong><?= h(cb_denni_report_format_money_whole((float)($restiaSummary['control_amount'] ?? 0))) ?></strong></td></tr>
          </tbody>
        </table>
      </section>

      <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_ops_section">
        <h4 class="card_section_title txt_seda">Operativa a kontrola</h4>
        <table class="zr_table zr_restia_table">
          <tbody>
            <tr><td class="zr_restia_key">Zrušené obj. ks</td><td class="zr_restia_value txt_r"><strong data-zr-cancel-count><?= h((string)$restiaSummary['cancel_count']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Zrušené obj. Kč</td><td class="zr_restia_value txt_r"><strong data-zr-cancel-value><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['cancel_value'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Zpožděné rozvozy +5 min</td><td class="zr_restia_value txt_r"><strong data-zr-delay-count><?= h((string)$restiaSummary['delay_count']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Průměrný make time</td><td class="zr_restia_value txt_r"><strong data-zr-make-time><?= h($makeTimeLabel) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Výdajové doklady</td><td class="zr_restia_value txt_r"><strong data-zr-docs-count><?= h((string)$restiaSummary['docs_count']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Nezrušené celkem</td><td class="zr_restia_value txt_r"><strong data-zr-orders-total><?= h((string)$restiaSummary['orders_total']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Naše rozvozy</td><td class="zr_restia_value txt_r"><strong data-zr-own-deliveries><?= h((string)$restiaSummary['own_deliveries']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Pozdě WoltDrive 5+</td><td class="zr_restia_value txt_r"><strong data-zr-woltdrive-late><?= h((string)$restiaSummary['woltdrive_late']) ?></strong></td></tr>
          </tbody>
        </table>
      </section>
    </aside>
  </div>
</form>
