<?php
// includes/denni_report_formular.php * K10 formular denniho reportu
declare(strict_types=1);

$renderUserSelectOptions = static function (array $options, int $selectedId, string $placeholder, array $excludeIds = []): string {
    $html = '<option value="">' . h($placeholder) . '</option>';
    $exclude = array_fill_keys(array_map('intval', $excludeIds), true);
    foreach ($options as $option) {
        $idUser = (int)($option['id_user'] ?? 0);
        $name = trim((string)($option['name'] ?? ''));
        if ($idUser <= 0 || $name === '' || isset($exclude[$idUser])) {
            continue;
        }
        $html .= '<option value="' . h((string)$idUser) . '"' . ($idUser === $selectedId ? ' selected' : '') . '>' . h($name) . '</option>';
    }
    return $html;
};

$renderTimeInput = static function (string $name, string $selected, string $dataAttr, string $extraAttr = ''): string {
    $attrName = trim($name) !== '' ? ' name="' . h($name) . '"' : '';
    $attrExtra = trim($extraAttr) !== '' ? ' ' . trim($extraAttr) : '';

    return '<input class="zr_time_input" type="text" inputmode="numeric"' . $attrName . ' value="' . h($selected) . '" style="width:100%;text-align:center;" ' . $dataAttr . $attrExtra . '>';
};


$renderInstorSavedRow = static function (array $row, callable $renderTimeInput): string {
    $idDrOsoby = (int)($row['id_dr_osoby'] ?? 0);
    $idUser = (int)($row['id_user'] ?? 0);
    $name = trim((string)($row['name'] ?? ''));
    $start = trim((string)($row['start'] ?? ''));
    $end = trim((string)($row['end'] ?? ''));
    $break = trim((string)($row['break'] ?? '0'));
    $hours = trim((string)($row['hours'] ?? '0'));

    return ''
        . '<tr data-zr-person-row="instor" data-zr-id-user="' . h((string)$idUser) . '" data-zr-id-dr-osoby="' . h((string)$idDrOsoby) . '">'
        . '<td style="width:220px;"><button type="button" class="zr_row_remove" data-zr-remove-row title="Odebrat" aria-label="Odebrat">×</button><strong class="zr_saved_value">' . h($name) . '</strong>'
        . '<input type="hidden" name="instor_jmeno[]" value="' . h($name) . '">'
        . '<input type="hidden" name="instor_id_user[]" value="' . h((string)$idUser) . '">'
        . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('instor_zacatek[]', $start, 'data-zr-start') . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('instor_konec[]', $end, 'data-zr-end') . '</td>'
        . '<td class="zr_person_cell_break" style="width:44px;"><input type="text" inputmode="decimal" name="instor_pauza_hod[]" value="' . h($break) . '" style="width:100%;text-align:center;" data-zr-break></td>'
        . '<td style="width:70px;"><strong class="zr_saved_value" data-zr-hours>' . h($hours) . ' hod.</strong><input type="hidden" name="instor_hodiny[]" value="' . h($hours) . '" data-zr-hours-hidden></td>'
        . '<td></td>'
        . '</tr>';
};

$renderKuryrSavedRow = static function (array $row, callable $renderTimeInput): string {
    $idDrOsoby = (int)($row['id_dr_osoby'] ?? 0);
    $idUser = (int)($row['id_user'] ?? 0);
    $name = trim((string)($row['name'] ?? ''));
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
        . '<tr data-zr-person-row="kuryr" data-zr-id-user="' . h((string)$idUser) . '" data-zr-id-dr-osoby="' . h((string)$idDrOsoby) . '">'
        . '<td style="width:220px;"><button type="button" class="zr_row_remove" data-zr-remove-row title="Odebrat" aria-label="Odebrat">×</button><strong class="zr_saved_value">' . h($name) . '</strong>'
        . '<input type="hidden" name="kuryr_jmeno[]" value="' . h($name) . '">'
        . '<input type="hidden" name="kuryr_id_user[]" value="' . h((string)$idUser) . '">'
        . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('kuryr_zacatek[]', $start, 'data-zr-start') . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('kuryr_konec[]', $end, 'data-zr-end') . '</td>'
        . '<td class="zr_person_cell_break" style="width:44px;"><input type="text" inputmode="decimal" name="kuryr_pauza_hod[]" value="' . h($break) . '" style="width:100%;text-align:center;" data-zr-break></td>'
        . '<td style="width:70px;"><strong class="zr_saved_value" data-zr-hours>' . h($hours) . ' hod.</strong><input type="hidden" name="kuryr_hodiny[]" value="' . h($hours) . '" data-zr-hours-hidden></td>'
        . '<td class="txt_c" style="width:48px;"><strong class="zr_saved_value">' . h((string)$deliveryRestia) . '</strong><input type="hidden" name="kuryr_pocet_rozvozu_restia[]" value="' . h((string)$deliveryRestia) . '"></td>'
        . '<td class="txt_c" style="width:48px;"><strong class="zr_saved_value">' . h((string)$deliveryManual) . '</strong><input type="hidden" name="kuryr_pocet_rozvozu_manual[]" value="' . h((string)$deliveryManual) . '">'
        . '<input type="hidden" name="kuryr_pocet_rozvozu[]" value="' . h((string)$deliveryTotal) . '">'
        . '</td>'
        . '<td class="txt_c" style="width:34px;"><strong class="zr_saved_value">' . h($car === 1 ? 'Ano' : 'Ne') . '</strong><input type="hidden" name="kuryr_vlastni_vuz[]" value="' . h((string)$car) . '"></td>'
        . '<td><strong class="zr_saved_value">' . h(cb_denni_report_format_money($phm)) . '</strong><input type="hidden" name="kuryr_vyplatit_phm[]" value="' . h(number_format($phm, 2, '.', '')) . '"></td>'
        . '</tr>';
};

ob_start();
?>
<p class="card_text txt_seda odstup_vnejsi_0">
  Denni report za pobočku je možné zadat<br>po ukončení směny.
</p>
<?php
$card_min_html = (string)ob_get_clean();

?>
<?php if (!$canSaveReport): ?>
  <div class="zr_readonly_info">
    Denní report mohou upravovat pouze oprávnění uživatelé. Zobrazená data jsou jen pro kontrolu.
  </div>
<?php endif; ?>
<form class="zr_form gap_14" autocomplete="off" method="post" action="<?= h(cb_url('/')) ?>" data-zr-form data-cb-max-form="1" data-cb-loader-text="Načítám report pobočky" style="position:relative;">
  <input type="hidden" name="dr_id" value="<?= h((string)$idDr) ?>" data-zr-dr-id>
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
                  <input class="zr_date_display" type="text" value="<?= h($reportDateDisplay) ?>" readonly data-zr-date-display>
                  <input type="hidden" name="datum_reportu" value="<?= h($reportDate) ?>" data-zr-date data-zr-required="datum">
                </td>
              </tr>
              <tr>
                <th class="zr_intro_label zr_req_label txt_l" data-zr-required-label="oteviral">Otevíral</th>
                <td>
                  <select class="zr_intro_select" name="oteviral" data-zr-field="oteviral" data-zr-required="oteviral">
                    <?= $renderUserSelectOptions($instorOptions, $openingId, 'Vyber jméno') ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="zr_intro_label zr_req_label txt_l" data-zr-required-label="zaviral">Zavíral</th>
                <td>
                  <select class="zr_intro_select" name="zaviral" data-zr-field="zaviral" data-zr-required="zaviral">
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
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="pokladna_hotovost" value="<?= h($cashData['hotovost']) ?>" data-zr-field="pokladna_hotovost" data-zr-money="int" data-zr-required="pokladna_hotovost"></td>
                <td><input class="zr_money_input" type="text" inputmode="decimal" name="pokladna_terminal" value="<?= h($cashData['terminal']) ?>" data-zr-field="pokladna_terminal" data-zr-money="decimal" data-zr-required="pokladna_terminal"></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="pokladna_stravenky" value="<?= h($cashData['stravenky']) ?>" data-zr-field="pokladna_stravenky" data-zr-money="int" data-zr-required="pokladna_stravenky"></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_benzin" value="<?= h($cashData['vydaje_benzin']) ?>" data-zr-field="vydaje_benzin" data-zr-money="int" data-zr-required="vydaje_benzin"></td>
              </tr>
              <tr>
                <th class="txt_l">Auta</th>
                <th class="txt_l">Suroviny</th>
                <th class="txt_l">Ostatni</th>
                <th class="txt_l">PHM-soukr.</th>
              </tr>
              <tr>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_auta" value="<?= h($cashData['vydaje_auta']) ?>" data-zr-field="vydaje_auta" data-zr-money="int" data-zr-required="vydaje_auta"></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_suroviny" value="<?= h($cashData['vydaje_suroviny']) ?>" data-zr-field="vydaje_suroviny" data-zr-money="int" data-zr-required="vydaje_suroviny"></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_ostatni" value="<?= h($cashData['vydaje_ostatni']) ?>" data-zr-field="vydaje_ostatni" data-zr-money="int" data-zr-required="vydaje_ostatni"></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_phm_soukrome" value="<?= h($cashData['vydaje_phm_soukrome']) ?>" data-zr-field="vydaje_phm_soukrome" data-zr-money="int" data-zr-required="vydaje_phm_soukrome"></td>
              </tr>
            </tbody>
          </table>
        </section>
      </section>

      <div class="zr_left gap_14">
        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_instor_section">
          <h4 class="card_section_title txt_seda">Instor</h4>
          <div style="width:220px;margin-bottom:6px;">
            <select data-zr-add-person="instor">
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
            <strong class="zr_control_label zr_control_label_col">COL</strong>
            <strong class="zr_control_value zr_control_value_col" data-zr-report-col><?= h($reportColLabel) ?></strong>
            <input type="hidden" name="col_pomer" value="<?= h($reportColValue) ?>" data-zr-report-col-value>
          </div>
        </section>

        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_kuryr_section">
          <h4 class="card_section_title txt_seda">Kurýr</h4>
          <div style="width:220px;margin-bottom:6px;">
            <select data-zr-add-person="kuryr">
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
                <th class="txt_l" style="width:34px;white-space:nowrap;">Vůz</th>
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
            style="width:100%;margin-top:4px;"
          >
        </section>
        <?php if ($canSaveReport && $reportBranchId > 0): ?>
          <button
            type="button"
            class="zr_submit"
            disabled
            data-zr-submit
            data-zr-submit-locked-text="Report bude možné uložit za"
            data-zr-submit-ready-text="Report je zkontrolovaný, uložit"
            data-zr-submit-missing-text="Vyplň všechna povinná data reportu"
            data-zr-submit-at="<?= h((string)$reportSaveAtTs) ?>"
            style="background:#d9dee8;border-color:#c1c9d6;color:#5f6b7a;cursor:not-allowed;opacity:1;"
          >Report bude možné uložit za 0:00:00</button>
        <?php endif; ?>
      </div>
    </div>

    <aside class="zr_side gap_14">
      <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_restia_section">
        <h4 class="card_section_title txt_seda">Aktuální data z Restie</h4>
        <div class="zr_restia_update">
          <span>Aktualizace v <?= h($lastRestiaUpdateLabel) ?></span>
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
          <tbody>
            <tr><td class="zr_restia_key">Wolt</td><td class="zr_restia_value txt_r"><strong data-zr-restia-wolt data-zr-value="<?= h(number_format((float)$restiaSummary['wolt'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['wolt'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Bolt</td><td class="zr_restia_value txt_r"><strong data-zr-restia-bolt data-zr-value="<?= h(number_format((float)$restiaSummary['bolt'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['bolt'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Foodora</td><td class="zr_restia_value txt_r"><strong data-zr-restia-dj data-zr-value="<?= h(number_format((float)$restiaSummary['dj'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['dj'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Web</td><td class="zr_restia_value txt_r"><strong data-zr-restia-web data-zr-value="<?= h(number_format((float)$restiaSummary['web'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['web'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Wolt drive cash</td><td class="zr_restia_value txt_r"><strong data-zr-restia-wolt-cash data-zr-value="<?= h(number_format((float)$restiaSummary['wolt_cash'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['wolt_cash'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">DJ cash</td><td class="zr_restia_value txt_r"><strong data-zr-restia-dj-cash data-zr-value="<?= h(number_format((float)$restiaSummary['dj_cash'], 2, '.', '')) ?>"><?= h(cb_denni_report_format_money_whole((float)$restiaSummary['dj_cash'])) ?></strong></td></tr>
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
  <?php if (!$canSaveReport): ?>
    <div class="zr_readonly_overlay" aria-hidden="true"></div>
  <?php endif; ?>
</form>
