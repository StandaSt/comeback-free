<?php
// karty/zadani_reportu.php * Verze: V2 * Aktualizace: 09.03.2026
declare(strict_types=1);

$cbYesterday = new DateTimeImmutable('yesterday');
$cbDateValue = $cbYesterday->format('Y-m-d');
$cbWeekdays = [
    'Monday' => 'Pondělí',
    'Tuesday' => 'Úterý',
    'Wednesday' => 'Středa',
    'Thursday' => 'Čtvrtek',
    'Friday' => 'Pátek',
    'Saturday' => 'Sobota',
    'Sunday' => 'Neděle',
];
$cbWeekdayValue = $cbWeekdays[$cbYesterday->format('l')] ?? '';
$cbDateDisplay = mb_strtolower($cbWeekdayValue) . ' ' . $cbYesterday->format('j.n.Y');
$cbInstorOptions = [
    'Šebesta Tomáš',
    'Pešová Hana',
    'Chlubnová Adéla',
    'Martin Sova',
];
$cbKuryrOptions = [
    'Ondřej Navrátil',
    'Hubr Samuel',
    'Jan Přibyl',
    'Hammer Jan',
];
$cbTimeOptions = [];
for ($hour = 0; $hour < 24; $hour++) {
    foreach ([0, 15, 30, 45] as $minute) {
        $cbTimeOptions[] = sprintf('%02d:%02d', $hour, $minute);
    }
}
?>

<article class="card_shell cb-zadani-reportu">
  <div class="card_top">
    <div>
      <h3
        class="card_title"
        data-zr-card-title
        data-zr-card-title-base="<?= h((string)($cb_card_title ?? 'Zadávání denního reportu')) ?>"
      ><?= h((string)($cb_card_title ?? 'Zadávání denního reportu')) ?></h3>
      <p class="card_subtitle"><span class="card_code"><?= h((string)($cb_card_code ?? '')) ?></span>Formulář podle Report Chodov.xlsx</p>
    </div>
    <div class="card_tools">
      <button
        type="button"
        class="card_tool_btn"
        data-card-toggle="1"
        aria-expanded="false"
        title="Rozbalit/sbalit"
      >⤢</button>
    </div>
  </div>

  <div class="card_compact" data-card-compact>
    <p class="card_text">Denní report s ručním zadáním a kontrolou dat z Restie.</p>
  </div>

  <div class="card_expanded is-hidden" data-card-expanded>
    <form class="zr_form" autocomplete="off" data-zr-form>
      <div class="zr_layout">
        <div class="zr_main">
          <section class="zr_top">
            <section class="card_section zr_section zr_intro_section">
              <table class="zr_table">
                <tbody>
                  <tr>
                    <th class="zr_intro_label zr_req_label" data-zr-required-label="datum">Datum</th>
                    <td>
                      <input class="zr_date_display" type="text" value="<?= h($cbDateDisplay) ?>" readonly data-zr-date-display>
                      <input type="hidden" name="datum_reportu" value="<?= h($cbDateValue) ?>" data-zr-date data-zr-required="datum">
                    </td>
                  </tr>
                  <tr>
                    <th class="zr_intro_label zr_req_label" data-zr-required-label="oteviral">Otevíral</th>
                    <td>
                      <select class="zr_intro_select" name="oteviral" data-zr-field="oteviral" data-zr-required="oteviral">
                        <option value="">Vyber Instor</option>
                        <?php foreach ($cbInstorOptions as $instorName): ?>
                          <option value="<?= h($instorName) ?>"><?= h($instorName) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <th class="zr_intro_label zr_req_label" data-zr-required-label="zaviral">Zavíral</th>
                    <td>
                      <select class="zr_intro_select" name="zaviral" data-zr-field="zaviral" data-zr-required="zaviral">
                        <option value="">Vyber Instor</option>
                        <?php foreach ($cbInstorOptions as $instorName): ?>
                          <option value="<?= h($instorName) ?>"><?= h($instorName) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                  </tr>
                </tbody>
              </table>
            </section>

            <section class="card_section zr_section zr_cash_section">
              <h4 class="card_section_title">Pokladna a výdaje</h4>
              <table class="zr_table zr_cash_table">
                <thead>
                  <tr>
                    <th class="zr_req_label" data-zr-required-label="pokladna_hotovost">Hotovost</th>
                    <th>Terminál</th>
                    <th>Stravenky</th>
                    <th>Benzín</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><input class="zr_money_input" type="text" inputmode="numeric" name="pokladna_hotovost" value="" data-zr-field="pokladna_hotovost" data-zr-money="int" data-zr-required="pokladna_hotovost"></td>
                    <td><input class="zr_money_input" type="text" inputmode="decimal" name="pokladna_terminal" value="0" data-zr-field="pokladna_terminal" data-zr-money="decimal" data-zr-required="pokladna_terminal"></td>
                    <td><input class="zr_money_input" type="text" inputmode="numeric" name="pokladna_stravenky" value="0" data-zr-field="pokladna_stravenky" data-zr-money="int" data-zr-required="pokladna_stravenky"></td>
                    <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_benzin" value="0" data-zr-field="vydaje_benzin" data-zr-money="int" data-zr-required="vydaje_benzin"></td>
                  </tr>
                  <tr>
                    <th>Auta</th>
                    <th>Suroviny</th>
                    <th>Ostatní</th>
                    <th>PHM-soukr.</th>
                  </tr>
                  <tr>
                    <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_auta" value="0" data-zr-field="vydaje_auta" data-zr-money="int" data-zr-required="vydaje_auta"></td>
                    <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_suroviny" value="0" data-zr-field="vydaje_suroviny" data-zr-money="int" data-zr-required="vydaje_suroviny"></td>
                    <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_ostatni" value="0" data-zr-field="vydaje_ostatni" data-zr-money="int" data-zr-required="vydaje_ostatni"></td>
                    <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_phm_soukrome" value="0" data-zr-field="vydaje_phm_soukrome" data-zr-money="int" data-zr-required="vydaje_phm_soukrome"></td>
                  </tr>
                </tbody>
              </table>
            </section>
          </section>

          <div class="zr_left">
          <section class="card_section zr_section zr_instor_section">
            <h4 class="card_section_title">Instor</h4>
            <table class="zr_table zr_person_table" data-zr-people-list="instor">
              <colgroup>
                <col class="zr_person_col_name">
                <col class="zr_person_col_time">
                <col class="zr_person_col_time">
                <col class="zr_person_col_break">
                <col class="zr_person_col_hours">
                <col class="zr_person_col_save">
              </colgroup>
              <thead>
                <tr>
                  <th class="zr_person_col_name zr_req_label" data-zr-required-label="instor_jmeno">Instor:</th>
                  <th class="zr_person_col_time zr_req_label" data-zr-required-label="instor_zacatek">Směna od:</th>
                  <th class="zr_person_col_time zr_req_label" data-zr-required-label="instor_konec">Směna do:</th>
                  <th class="zr_person_col_break">Pauza</th>
                  <th class="zr_person_col_hours">Odpracováno:</th>
                  <th class="zr_person_col_save"></th>
                </tr>
              </thead>
              <tbody>
                <tr data-zr-person-row="instor" data-zr-editor="instor">
                  <td class="zr_person_cell_name">
                    <select data-zr-editor-field="jmeno" data-zr-required="instor_jmeno">
                      <option value="">Vyber zaměstnance</option>
                      <?php foreach ($cbInstorOptions as $instorName): ?>
                        <option value="<?= h($instorName) ?>"><?= h($instorName) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td class="zr_person_cell_time">
                    <select data-zr-editor-field="start" data-zr-start data-zr-required="instor_zacatek">
                      <option value="">--:--</option>
                      <?php foreach ($cbTimeOptions as $timeOpt): ?>
                        <option value="<?= h($timeOpt) ?>"<?= $timeOpt === '10:00' ? ' selected' : '' ?>><?= h($timeOpt) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td class="zr_person_cell_time">
                    <select data-zr-editor-field="end" data-zr-end data-zr-required="instor_konec">
                      <option value="">--:--</option>
                      <?php foreach ($cbTimeOptions as $timeOpt): ?>
                        <option value="<?= h($timeOpt) ?>"<?= $timeOpt === '16:00' ? ' selected' : '' ?>><?= h($timeOpt) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td class="zr_person_cell_break"><input type="text" inputmode="decimal" value="0" data-zr-editor-field="break" data-zr-break data-zr-required="instor_pauza"></td>
                  <td class="zr_person_cell_hours">
                    <strong class="zr_hours_value" data-zr-hours>10 hod.</strong>
                    <input type="hidden" value="10" data-zr-hours-hidden>
                  </td>
                  <td class="zr_person_cell_save"><button type="button" class="zr_row_save" data-zr-save-row="instor" disabled>Uložit</button></td>
                </tr>
              </tbody>
              <tbody data-zr-saved-list="instor"></tbody>
            </table>
          </section>

          <section class="card_section zr_section zr_kuryr_section">
            <h4 class="card_section_title">Kurýr</h4>
            <table class="zr_table zr_person_table" data-zr-people-list="kuryr">
              <colgroup>
                <col class="zr_person_col_name">
                <col class="zr_person_col_time">
                <col class="zr_person_col_time">
                <col class="zr_person_col_break">
                <col class="zr_person_col_hours">
                <col class="zr_person_col_save">
              </colgroup>
              <thead>
                <tr>
                  <th class="zr_person_col_name">Kurýr:</th>
                  <th class="zr_person_col_time">Směna od:</th>
                  <th class="zr_person_col_time">Směna do:</th>
                  <th class="zr_person_col_break">Pauza</th>
                  <th class="zr_person_col_hours">Odprac.:</th>
                  <th class="zr_person_col_save">Rozvozů / Vlastní vůz / Vyplatit PHM / Uložit</th>
                </tr>
              </thead>
              <tbody>
                <tr data-zr-person-row="kuryr" data-zr-editor="kuryr">
                  <td class="zr_person_cell_name">
                    <select data-zr-editor-field="jmeno">
                      <option value="">Vyber kurýra</option>
                      <?php foreach ($cbKuryrOptions as $kuryrName): ?>
                        <option value="<?= h($kuryrName) ?>"><?= h($kuryrName) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td class="zr_person_cell_time">
                    <select data-zr-editor-field="start" data-zr-start>
                      <option value="">--:--</option>
                      <?php foreach ($cbTimeOptions as $timeOpt): ?>
                        <option value="<?= h($timeOpt) ?>"<?= $timeOpt === '10:00' ? ' selected' : '' ?>><?= h($timeOpt) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td class="zr_person_cell_time">
                    <select data-zr-editor-field="end" data-zr-end>
                      <option value="">--:--</option>
                      <?php foreach ($cbTimeOptions as $timeOpt): ?>
                        <option value="<?= h($timeOpt) ?>"<?= $timeOpt === '16:00' ? ' selected' : '' ?>><?= h($timeOpt) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td class="zr_person_cell_break"><input type="text" inputmode="decimal" value="0" data-zr-editor-field="break" data-zr-break></td>
                  <td class="zr_person_cell_hours">
                    <strong class="zr_hours_value" data-zr-hours>10 hod.</strong>
                    <input type="hidden" value="10" data-zr-hours-hidden>
                  </td>
                  <td class="zr_person_cell_save">
                    <div class="zr_kuryr_extra">
                      <div class="zr_kuryr_extra_item">
                        <span class="zr_kuryr_extra_label"></span>
                        <div class="zr_delivery_wrap zr_delivery_wrap_kuryr">
                          <input class="zr_delivery_input" type="text" inputmode="numeric" value="0" data-zr-editor-field="delivery_restia" data-zr-int-short>
                          <span class="zr_delivery_plus">+</span>
                          <input class="zr_delivery_input" type="text" inputmode="numeric" value="" data-zr-editor-field="delivery_manual" data-zr-int-short>
                        </div>
                        <input type="hidden" value="0" data-zr-delivery-total>
                      </div>
                      <div class="zr_kuryr_extra_item zr_kuryr_extra_item_car">
                        <span class="zr_kuryr_extra_label"></span>
                        <span class="zr_chk zr_person_cell_car zr_person_cell_car_inline"><input type="checkbox" value="1" data-zr-editor-field="car" data-zr-car-check></span>
                      </div>
                      <div class="zr_kuryr_extra_item zr_phm_field zr_person_cell_phm" data-zr-phm-field>
                        <span class="zr_kuryr_extra_label"></span>
                        <strong class="zr_hours_value" data-zr-phm-value>0 Kč</strong>
                        <input type="hidden" value="0" data-zr-phm-hidden>
                      </div>
                      <div class="zr_kuryr_extra_item zr_kuryr_extra_item_save">
                        <button type="button" class="zr_row_save" data-zr-save-row="kuryr" disabled>Uložit</button>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
              <tbody data-zr-saved-list="kuryr"></tbody>
            </table>
          </section>

          <div class="card_actions">
            <button type="button" class="zr_submit is-hidden" data-zr-submit>Uložit report</button>
          </div>
          </div>
        </div>

        <aside class="zr_side">
          <section class="card_section zr_section zr_restia_section">
            <h4 class="card_section_title">Automaticky z Restie</h4>
            <div class="zr_restia_total">
              <span class="zr_metric_label">Tržba</span>
              <strong class="zr_metric_value" data-zr-restia-trzba>36 618 Kč</strong>
            </div>
            <table class="zr_table zr_restia_table">
              <tbody>
                <tr><td class="zr_restia_key">Wolt</td><td class="zr_restia_value"><strong data-zr-restia-wolt>9 034 Kč</strong></td></tr>
                <tr><td class="zr_restia_key">Bolt</td><td class="zr_restia_value"><strong data-zr-restia-bolt>1 056 Kč</strong></td></tr>
                <tr><td class="zr_restia_key">Dáme jídlo</td><td class="zr_restia_value"><strong data-zr-restia-dj>8 491 Kč</strong></td></tr>
                <tr><td class="zr_restia_key">Web</td><td class="zr_restia_value"><strong data-zr-restia-web>12 699 Kč</strong></td></tr>
                <tr><td class="zr_restia_key">Wolt drive cash</td><td class="zr_restia_value"><strong data-zr-restia-wolt-cash>0 Kč</strong></td></tr>
                <tr><td class="zr_restia_key">DJ cash</td><td class="zr_restia_value"><strong data-zr-restia-dj-cash>2 093 Kč</strong></td></tr>
              </tbody>
            </table>
          </section>

          <section class="card_section zr_section zr_ops_section">
            <h4 class="card_section_title">Operativa a kontrola</h4>
            <table class="zr_table zr_restia_table">
              <tbody>
                <tr><td class="zr_restia_key">Zrušené obj. ks</td><td class="zr_restia_value"><strong data-zr-cancel-count>0</strong></td></tr>
                <tr><td class="zr_restia_key">Zrušené obj. Kč</td><td class="zr_restia_value"><strong data-zr-cancel-value>0 Kč</strong></td></tr>
                <tr><td class="zr_restia_key">Zpožděné rozvozy +5 min</td><td class="zr_restia_value"><strong data-zr-delay-count>5</strong></td></tr>
                <tr><td class="zr_restia_key">Průměrný make time</td><td class="zr_restia_value"><strong data-zr-make-time>13 min 24 s</strong></td></tr>
                <tr><td class="zr_restia_key">Výdajové doklady</td><td class="zr_restia_value"><strong data-zr-docs-count>0</strong></td></tr>
                <tr><td class="zr_restia_key">Nezrušené celkem</td><td class="zr_restia_value"><strong data-zr-orders-total>78</strong></td></tr>
                <tr><td class="zr_restia_key">Naše rozvozy</td><td class="zr_restia_value"><strong data-zr-own-deliveries>12</strong></td></tr>
                <tr><td class="zr_restia_key">Pozdě WoltDrive 5+</td><td class="zr_restia_value"><strong data-zr-woltdrive-late>0</strong></td></tr>
              </tbody>
            </table>
          </section>
        </aside>
      </div>
    </form>
  </div>
</article>

<?php
/* karty/zadani_reportu.php * Verze: V2 * Aktualizace: 09.03.2026 */
?>
