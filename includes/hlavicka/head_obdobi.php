<?php
// includes/hlavicka/head_obdobi.php * Verze: V5 * Aktualizace: 27.04.2026
?>
<div class="head_interval ram_hlavicka zaobleni_10 gap_4 displ_flex flex_sloupec jc_stred" aria-label="Období">
  <div class="head_int_row displ_grid">
    <label class="head_date text_11 gap_6 displ_flex">
      <span>Od</span>
      <input class="text_11 zaobleni_8 ram_ovladace" type="date" id="cbObdobiOd" value="<?= h($cbObdobiOd) ?>">
    </label>
    <div class="head_quick gap_4 displ_flex jc_konec">
      <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="dnes">Dnes</button>
      <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="tyden">Týden</button>
    </div>
  </div>

  <div class="head_int_row displ_grid">
    <label class="head_date text_11 gap_6 displ_flex">
      <span>Do</span>
      <input class="text_11 zaobleni_8 ram_ovladace" type="date" id="cbObdobiDo" value="<?= h($cbObdobiDo) ?>">
    </label>
    <div class="head_quick gap_4 displ_flex jc_konec">
      <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="mesic">Měsíc</button>
      <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="rok">Rok</button>
    </div>
  </div>
</div>

<script>
(function(){
  var odInput = document.getElementById('cbObdobiOd');
  var doInput = document.getElementById('cbObdobiDo');
  var quickBtns = document.querySelectorAll('.head_interval .head_pill[data-range]');
  var odLabel = odInput ? odInput.closest('.head_date') : null;
  var doLabel = doInput ? doInput.closest('.head_date') : null;
  var activeMode = '<?= h($cbObdobiMode) ?>';

  if (!odInput || !doInput || !quickBtns.length) {
    return;
  }
  var isSaving = false;
  var allowedModes = ['dnes', 'tyden', 'mesic', 'rok', 'manual'];

  if (allowedModes.indexOf(activeMode) === -1) {
    activeMode = 'manual';
  }

  function fmtDate(dt){
    var y = dt.getFullYear();
    var m = String(dt.getMonth() + 1).padStart(2, '0');
    var d = String(dt.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  function parseDate(v){
    var s = String(v || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return null;
    var parts = s.split('-');
    var y = Number(parts[0]);
    var m = Number(parts[1]);
    var d = Number(parts[2]);
    var dt = new Date(y, m - 1, d);
    if (dt.getFullYear() !== y || (dt.getMonth() + 1) !== m || dt.getDate() !== d) {
      return null;
    }
    dt.setHours(0, 0, 0, 0);
    return dt;
  }

  function getWorkingToday(){
    var now = new Date();
    var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    if (now.getHours() < 8) {
      today.setDate(today.getDate() - 1);
    }
    return today;
  }

  function getWorkingEnd(){
    var end = new Date(getWorkingToday());
    end.setDate(end.getDate() + 1);
    return end;
  }

  function clampToMax(v, maxDate){
    var dt = parseDate(v);
    if (!dt) return '';
    if (dt.getTime() > maxDate.getTime()) {
      dt = new Date(maxDate);
    }
    return fmtDate(dt);
  }

  function setActive(mode){
    activeMode = mode;
    quickBtns.forEach(function(btn){
      btn.classList.toggle('is-on', mode !== 'manual' && btn.getAttribute('data-range') === mode);
    });
  }

  function setManualHighlight(isManual){
    if (odLabel) odLabel.classList.toggle('is-manual', !!isManual);
    if (doLabel) doLabel.classList.toggle('is-manual', !!isManual);
    if (odInput) odInput.classList.toggle('is-manual', !!isManual);
    if (doInput) doInput.classList.toggle('is-manual', !!isManual);
  }

  function savePeriod(payload){
    if (isSaving) {
      return Promise.resolve();
    }
    isSaving = true;
    return fetch('<?= h(cb_url('index.php')) ?>', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Comeback-Set-Period': '1'
      },
      body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json().catch(function(){ return {}; }); })
    .then(function(json){
      if (json && json.ok === true) {
        if (window.CB_AJAX && typeof window.CB_AJAX.refreshDashboardRefreshOpCards === 'function') {
          return window.CB_AJAX.refreshDashboardRefreshOpCards();
        }
      }
    })
    .catch(function(){})
    .finally(function(){ isSaving = false; });
  }

  function computeRange(range){
    var workingToday = getWorkingToday();
    var from = new Date(workingToday);
    var to = getWorkingEnd();

    if (range === 'dnes') {
      return { od: fmtDate(from), do: fmtDate(to) };
    }

    if (range === 'tyden') {
      var day = workingToday.getDay();
      var mondayShift = (day === 0 ? -6 : 1 - day);
      from.setDate(workingToday.getDate() + mondayShift);
      return { od: fmtDate(from), do: fmtDate(to) };
    }

    if (range === 'mesic') {
      from = new Date(workingToday.getFullYear(), workingToday.getMonth(), 1);
      return { od: fmtDate(from), do: fmtDate(to) };
    }

    from = new Date(workingToday.getFullYear(), 0, 1);
    return { od: fmtDate(from), do: fmtDate(to) };
  }

  quickBtns.forEach(function(btn){
    btn.addEventListener('click', function(){
      var range = btn.getAttribute('data-range') || 'dnes';
      var val = computeRange(range);

      odInput.value = val.od;
      doInput.value = val.do;
      setActive(range);
      setManualHighlight(false);
      savePeriod({ od: val.od, do: val.do, mode: range });
    });
  });

  odInput.max = fmtDate(getWorkingToday());
  doInput.max = fmtDate(getWorkingEnd());

  odInput.addEventListener('change', function(){
    var od = clampToMax(odInput.value, getWorkingToday());
    var ddo = clampToMax(doInput.value, getWorkingEnd());
    if (!od || !ddo) return;
    if (od > ddo) {
      od = ddo;
    }
    odInput.value = od;
    doInput.value = ddo;
    setActive('manual');
    setManualHighlight(true);
    savePeriod({ od: od, do: ddo, mode: 'manual' });
  });

  doInput.addEventListener('change', function(){
    var od = clampToMax(odInput.value, getWorkingToday());
    var ddo = clampToMax(doInput.value, getWorkingEnd());
    if (!od || !ddo) return;
    if (ddo < od) {
      ddo = od;
    }
    odInput.value = od;
    doInput.value = ddo;
    setActive('manual');
    setManualHighlight(true);
    savePeriod({ od: od, do: ddo, mode: 'manual' });
  });

  setActive(activeMode);
  setManualHighlight(activeMode === 'manual');
})();
</script>
