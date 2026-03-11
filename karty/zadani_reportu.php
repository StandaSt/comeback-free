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
    <form class="zr_layout" autocomplete="off" data-zr-form>
      <div class="zr_main card_stack">
        <div class="zr_intro_layout">
          <section class="card_section zr_intro_box zr_intro_box_left">
            <div class="zr_intro_left">
              <label class="card_field zr_req_label" data-zr-required-label="datum">Datum
                <input class="card_input zr_date_display" type="text" value="<?= h($cbDateDisplay) ?>" readonly data-zr-date-display>
                <input type="hidden" name="datum_reportu" value="<?= h($cbDateValue) ?>" data-zr-date data-zr-required="datum">
              </label>
              <label class="card_field zr_req_label" data-zr-required-label="oteviral">Otevíral
                <select class="card_select" name="oteviral" data-zr-required="oteviral">
                  <option value="">Vyber Instor</option>
                  <?php foreach ($cbInstorOptions as $instorName): ?>
                    <option value="<?= h($instorName) ?>"><?= h($instorName) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="card_field zr_req_label" data-zr-required-label="zaviral">Zavíral
                <select class="card_select" name="zaviral" data-zr-required="zaviral">
                  <option value="">Vyber Instor</option>
                  <?php foreach ($cbInstorOptions as $instorName): ?>
                    <option value="<?= h($instorName) ?>"><?= h($instorName) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
          </section>

          <section class="card_section zr_intro_box zr_cash_box">
            <h4 class="card_section_title">Pokladna a výdaje</h4>
            <div class="zr_grid zr_grid_cash">
              <label class="card_field zr_req_label" data-zr-required-label="pokladna_hotovost">Hotovost
                <input class="card_input zr_money_input" type="text" inputmode="numeric" name="pokladna_hotovost" value="" data-zr-money="int" data-zr-required="pokladna_hotovost">
              </label>
              <label class="card_field">Terminál
                <input class="card_input zr_money_input" type="text" inputmode="decimal" name="pokladna_terminal" value="0" data-zr-money="decimal" data-zr-required="pokladna_terminal">
              </label>
              <label class="card_field">Stravenky
                <input class="card_input zr_money_input" type="text" inputmode="numeric" name="pokladna_stravenky" value="0" data-zr-money="int" data-zr-required="pokladna_stravenky">
              </label>
              <label class="card_field">Benzín
                <input class="card_input zr_money_input" type="text" inputmode="numeric" name="vydaje_benzin" value="0" data-zr-money="int" data-zr-required="vydaje_benzin">
              </label>
              <label class="card_field">Auta
                <input class="card_input zr_money_input" type="text" inputmode="numeric" name="vydaje_auta" value="0" data-zr-money="int" data-zr-required="vydaje_auta">
              </label>
              <label class="card_field">Suroviny
                <input class="card_input zr_money_input" type="text" inputmode="numeric" name="vydaje_suroviny" value="0" data-zr-money="int" data-zr-required="vydaje_suroviny">
              </label>
              <label class="card_field">Ostatní
                <input class="card_input zr_money_input" type="text" inputmode="numeric" name="vydaje_ostatni" value="0" data-zr-money="int" data-zr-required="vydaje_ostatni">
              </label>
              <label class="card_field">PHM - soukromé
                <input class="card_input zr_money_input" type="text" inputmode="numeric" name="vydaje_phm_soukrome" value="0" data-zr-money="int" data-zr-required="vydaje_phm_soukrome">
              </label>
            </div>
          </section>
        </div>

        <section class="card_section">
          <h4 class="card_section_title">Instor</h4>
          <div class="zr_people_stack" data-zr-people-list="instor">
            <div class="zr_person_row zr_person_row_staff zr_person_editor" data-zr-person-row="instor" data-zr-editor="instor">
              <label class="card_field zr_person_name zr_req_label" data-zr-required-label="instor_jmeno">Instor:
                <select id="zr_instor_jmeno" class="card_select" data-zr-editor-field="jmeno" data-zr-required="instor_jmeno" onchange="document.getElementById('zr_instor_ulozit').disabled = !this.value;">
                  <option value="">Vyber zaměstnance</option>
                  <?php foreach ($cbInstorOptions as $instorName): ?>
                    <option value="<?= h($instorName) ?>"><?= h($instorName) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="card_field zr_req_label" data-zr-required-label="instor_zacatek">Směna od:
                <select class="card_select" data-zr-editor-field="start" data-zr-start data-zr-required="instor_zacatek">
                  <option value="">--:--</option>
                  <?php foreach ($cbTimeOptions as $timeOpt): ?>
                    <option value="<?= h($timeOpt) ?>"<?= $timeOpt === '10:00' ? ' selected' : '' ?>><?= h($timeOpt) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="card_field zr_req_label" data-zr-required-label="instor_konec">Směna do:
                <select class="card_select" data-zr-editor-field="end" data-zr-end data-zr-required="instor_konec">
                  <option value="">--:--</option>
                  <?php foreach ($cbTimeOptions as $timeOpt): ?>
                    <option value="<?= h($timeOpt) ?>"<?= $timeOpt === '16:00' ? ' selected' : '' ?>><?= h($timeOpt) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="card_field">Pauza
                <input class="card_input" type="text" inputmode="decimal" value="0" data-zr-editor-field="break" data-zr-break data-zr-required="instor_pauza">
              </label>
              <div class="card_field zr_hours_field">
                <span class="zr_hours_label">Odpracováno:</span>
                <strong class="zr_hours_value" data-zr-hours>10 hod.</strong>
                <input type="hidden" value="10" data-zr-hours-hidden>
              </div>
              <div class="card_field zr_row_save_wrap">
                <button type="button" id="zr_instor_ulozit" class="zr_row_save" data-zr-save-row="instor" disabled>Uložit</button>
              </div>
            </div>
            <div class="zr_saved_rows" data-zr-saved-list="instor"></div>
          </div>
        </section>

        <section class="card_section">
          <h4 class="card_section_title">Kurýr</h4>
          <div class="zr_people_stack" data-zr-people-list="kuryr">
            <div class="zr_person_row zr_person_row_staff zr_person_row_kuryr zr_person_editor" data-zr-person-row="kuryr" data-zr-editor="kuryr">
              <label class="card_field zr_person_name">Kurýr:
                <select id="zr_kuryr_jmeno" class="card_select" data-zr-editor-field="jmeno" onchange="document.getElementById('zr_kuryr_ulozit').disabled = !this.value;">
                  <option value="">Vyber kurýra</option>
                  <?php foreach ($cbKuryrOptions as $kuryrName): ?>
                    <option value="<?= h($kuryrName) ?>"><?= h($kuryrName) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="card_field">Směna od:
                <select class="card_select" data-zr-editor-field="start" data-zr-start>
                  <option value="">--:--</option>
                  <?php foreach ($cbTimeOptions as $timeOpt): ?>
                    <option value="<?= h($timeOpt) ?>"<?= $timeOpt === '10:00' ? ' selected' : '' ?>><?= h($timeOpt) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="card_field">Směna do:
                <select class="card_select" data-zr-editor-field="end" data-zr-end>
                  <option value="">--:--</option>
                  <?php foreach ($cbTimeOptions as $timeOpt): ?>
                    <option value="<?= h($timeOpt) ?>"<?= $timeOpt === '16:00' ? ' selected' : '' ?>><?= h($timeOpt) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="card_field">Pauza
                <input class="card_input" type="text" inputmode="decimal" value="0" data-zr-editor-field="break" data-zr-break>
              </label>
              <div class="card_field zr_hours_field">
                <span class="zr_hours_label">Odpracováno:</span>
                <div class="zr_field_value_row"><strong class="zr_hours_value" data-zr-hours>10 hod.</strong></div>
                <input type="hidden" value="10" data-zr-hours-hidden>
              </div>
              <div class="card_field zr_delivery_field">
                <span class="zr_delivery_label">Rozvozů:</span>
                <div class="zr_delivery_inputs">
                  <input class="card_input zr_delivery_input" type="text" inputmode="numeric" value="0" data-zr-editor-field="delivery_restia" data-zr-int-short>
                  <span class="zr_delivery_plus">+</span>
                  <input class="card_input zr_delivery_input" type="text" inputmode="numeric" value="" data-zr-editor-field="delivery_manual" data-zr-int-short>
                </div>
                <input type="hidden" value="0" data-zr-delivery-total>
              </div>
              <label class="card_field zr_chk">Vlastní vůz
                <input type="checkbox" value="1" data-zr-editor-field="car" data-zr-car-check>
              </label>
              <div class="card_field zr_phm_field is-hidden" data-zr-phm-field>
                <span class="zr_hours_label">Vyplatit PHM</span>
                <div class="zr_field_value_row"><strong class="zr_hours_value" data-zr-phm-value>0 Kč</strong></div>
                <input type="hidden" value="0" data-zr-phm-hidden>
              </div>
              <div class="card_field zr_row_save_wrap">
                <button type="button" id="zr_kuryr_ulozit" class="zr_row_save" data-zr-save-row="kuryr" disabled>Uložit</button>
              </div>
            </div>
            <div class="zr_saved_rows" data-zr-saved-list="kuryr"></div>
          </div>
        </section>

        <div class="card_actions">
          <button type="button" class="zr_submit is-hidden" data-zr-submit>Uložit report</button>
        </div>
      </div>

      <aside class="zr_side card_stack">
        <section class="card_section zr_block_api">
          <h4 class="card_section_title">Automaticky z Restie</h4>
          <div class="zr_metric_total">
            <span class="zr_metric_label">Tržba</span>
            <strong class="zr_metric_value" data-zr-restia-trzba>36 618 Kč</strong>
          </div>

          <div class="zr_auto_list">
            <div class="zr_auto_row"><span>Wolt</span><strong data-zr-restia-wolt>9 034 Kč</strong></div>
            <div class="zr_auto_row"><span>Bolt</span><strong data-zr-restia-bolt>1 056 Kč</strong></div>
            <div class="zr_auto_row"><span>Dáme jídlo</span><strong data-zr-restia-dj>8 491 Kč</strong></div>
            <div class="zr_auto_row"><span>Web</span><strong data-zr-restia-web>12 699 Kč</strong></div>
            <div class="zr_auto_row"><span>Wolt drive cash</span><strong data-zr-restia-wolt-cash>0 Kč</strong></div>
            <div class="zr_auto_row"><span>DJ cash</span><strong data-zr-restia-dj-cash>2 093 Kč</strong></div>
          </div>
        </section>

        <section class="card_section zr_block_api zr_block_api_minor">
          <h4 class="card_section_title">Operativa a kontrola</h4>
          <div class="zr_auto_grid">
            <div class="zr_auto_row"><span>Zrušené obj. ks</span><strong data-zr-cancel-count>0</strong></div>
            <div class="zr_auto_row"><span>Zrušené obj. Kč</span><strong data-zr-cancel-value>0 Kč</strong></div>
            <div class="zr_auto_row"><span>Zpožděné rozvozy +5 min</span><strong data-zr-delay-count>5</strong></div>
            <div class="zr_auto_row"><span>Průměrný make time</span><strong data-zr-make-time>13 min 24 s</strong></div>
            <div class="zr_auto_row"><span>Výdajové doklady</span><strong data-zr-docs-count>0</strong></div>
            <div class="zr_auto_row"><span>Nezrušené celkem</span><strong data-zr-orders-total>78</strong></div>
            <div class="zr_auto_row"><span>Naše rozvozy</span><strong data-zr-own-deliveries>12</strong></div>
            <div class="zr_auto_row"><span>Pozdě WoltDrive 5+</span><strong data-zr-woltdrive-late>0</strong></div>
          </div>
        </section>
      </aside>
    </form>
  </div>
</article>

<?php
/* karty/zadani_reportu.php * Verze: V2 * Aktualizace: 09.03.2026 */
?>
