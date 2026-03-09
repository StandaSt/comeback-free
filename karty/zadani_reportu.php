<?php
// karty/zadani_reportu.php * Verze: V1 * Aktualizace: 08.03.2026
declare(strict_types=1);
?>

<article class="zr_card cb-zadani-reportu">
  <div class="card_top">
    <div>
      <h3 class="card_title">Zadávání denního reportu</h3>
      <p class="card_subtitle">Formulář podle Report Chodov.xlsx</p>
    </div>
    <div class="card_tools">
      <button
        type="button"
        class="card_tool_btn"
        data-zr-toggle="1"
        aria-expanded="false"
        title="Rozbalit/sbalit"
      >&#8599;&#8601;</button>
    </div>
  </div>

  <div class="zr_compact" data-zr-compact>
    <p class="card_text">Zde se vkládá denní report.</p>
  </div>

  <div class="zr_expanded is-hidden" data-zr-expanded>
    <form class="zr_form" autocomplete="off">
      <div class="zr_grid zr_grid_top">
        <label>Datum
          <input type="date" name="datum_reportu" value="">
        </label>
        <label>Den
          <select name="nazev_dne">
            <option value="">Vyber den</option>
            <option>Pondělí</option>
            <option>Úterý</option>
            <option>Středa</option>
            <option>Čtvrtek</option>
            <option>Pátek</option>
            <option>Sobota</option>
            <option>Neděle</option>
          </select>
        </label>
        <label>Otevíral
          <input type="text" name="oteviral" value="">
        </label>
        <label>Zavíral
          <input type="text" name="zaviral" value="">
        </label>
      </div>

      <section class="zr_block">
        <h4>Tržby a online platby</h4>
        <div class="zr_grid">
          <label>Tržba celkem
            <input type="number" step="0.01" name="trzba_celkem" value="">
          </label>
          <label>Wolt
            <input type="number" step="0.01" name="online_wolt" value="">
          </label>
          <label>Bolt
            <input type="number" step="0.01" name="online_bolt" value="">
          </label>
          <label>Dáme jídlo
            <input type="number" step="0.01" name="online_dame_jidlo" value="">
          </label>
          <label>Web
            <input type="number" step="0.01" name="online_web" value="">
          </label>
          <label>Wolt drive cash
            <input type="number" step="0.01" name="online_wolt_drive_cash" value="">
          </label>
          <label>DJ cash
            <input type="number" step="0.01" name="online_dj_cash" value="">
          </label>
        </div>
      </section>

      <section class="zr_block">
        <h4>Pokladna a výdaje</h4>
        <div class="zr_grid">
          <label>Hotovost
            <input type="number" step="0.01" name="pokladna_hotovost" value="">
          </label>
          <label>Terminál
            <input type="number" step="0.01" name="pokladna_terminal" value="">
          </label>
          <label>Stravenky
            <input type="number" step="0.01" name="pokladna_stravenky" value="">
          </label>
          <label>Benzín
            <input type="number" step="0.01" name="vydaje_benzin" value="">
          </label>
          <label>Auta
            <input type="number" step="0.01" name="vydaje_auta" value="">
          </label>
          <label>Suroviny
            <input type="number" step="0.01" name="vydaje_suroviny" value="">
          </label>
          <label>Ostatní
            <input type="number" step="0.01" name="vydaje_ostatni" value="">
          </label>
        </div>
      </section>

      <section class="zr_block">
        <h4>Instor</h4>
        <div class="zr_grid">
          <label>Jméno Instor
            <select name="instor_jmeno">
              <option value="">Vyber zaměstnance</option>
              <option>Šebesta Tomáš</option>
              <option>Pešová Hana</option>
              <option>Chlubnová Adéla</option>
              <option>Martin Sova</option>
            </select>
          </label>
          <label>Začátek směny
            <input type="time" name="instor_zacatek" value="">
          </label>
          <label>Konec směny
            <input type="time" name="instor_konec" value="">
          </label>
          <label>Pauza (hod)
            <input type="number" step="0.25" name="instor_pauza_hod" value="">
          </label>
          <label>Počet hodin
            <input type="number" step="0.25" name="instor_hodiny" value="">
          </label>
        </div>
      </section>

      <section class="zr_block">
        <h4>Kurýr</h4>
        <div class="zr_grid">
          <label>Jméno Kurýr
            <select name="kuryr_jmeno">
              <option value="">Vyber kurýra</option>
              <option>Ondřej Navrátil</option>
              <option>Hubr Samuel</option>
              <option>Jan Přibyl</option>
              <option>Hammer Jan</option>
            </select>
          </label>
          <label>Začátek směny
            <input type="time" name="kuryr_zacatek" value="">
          </label>
          <label>Konec směny
            <input type="time" name="kuryr_konec" value="">
          </label>
          <label>Pauza (hod)
            <input type="number" step="0.25" name="kuryr_pauza_hod" value="">
          </label>
          <label>Počet hodin
            <input type="number" step="0.25" name="kuryr_hodiny" value="">
          </label>
          <label>Počet rozvozů
            <input type="number" step="1" name="kuryr_pocet_rozvozu" value="">
          </label>
          <label class="zr_chk">Vlastní vůz
            <input type="checkbox" name="kuryr_vlastni_vuz" value="1">
          </label>
          <label>Vyplatit PHM
            <input type="number" step="0.01" name="kuryr_vyplatit_phm" value="">
          </label>
        </div>
      </section>

      <section class="zr_block zr_block_api">
        <h4>Restia (mock API, needitovatelné)</h4>
        <div class="zr_grid">
          <label>Počet objednávek (API)
            <input type="number" name="api_pocet_obj" value="78" readonly>
          </label>
          <label>Průměrný make time (API)
            <input type="text" name="api_make_time" value="13 min 24 s" readonly>
          </label>
          <label>Zrušené obj. ks (API)
            <input type="number" name="api_zrusene_ks" value="0" readonly>
          </label>
          <label>Zrušené obj. Kč (API)
            <input type="number" name="api_zrusene_castka" value="0" readonly>
          </label>
        </div>
      </section>

      <div class="zr_actions">
        <button type="button">Uložit report</button>
        <button type="button">Uložit a nový den</button>
      </div>
    </form>
  </div>
</article>

<?php
/* karty/zadani_reportu.php * Verze: V1 * Aktualizace: 08.03.2026 */
?>
