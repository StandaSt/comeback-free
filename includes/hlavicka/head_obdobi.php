<?php
// includes/hlavicka/head_obdobi.php * Verze: V5 * Aktualizace: 27.04.2026
$cbObdobiOdInput = '';
$cbObdobiDoInput = '';
try {
    $cbObdobiOdInput = (new DateTimeImmutable((string)$cbObdobiOd))->format('Y-m-d\TH:i');
} catch (Throwable $e) {
    $cbObdobiOdInput = '';
}
try {
    $cbObdobiDoInput = (new DateTimeImmutable((string)$cbObdobiDo))->format('Y-m-d\TH:i');
} catch (Throwable $e) {
    $cbObdobiDoInput = '';
}
?>
<div class="head_interval ram_hlavicka zaobleni_10 gap_4 displ_flex flex_sloupec jc_stred" aria-label="Období">
  <div class="head_int_row displ_grid">
    <label class="head_date text_11 gap_6 displ_flex">
      <span>Od</span>
      <input class="text_11 zaobleni_8 ram_ovladace" type="datetime-local" id="cbObdobiOd" value="<?= h($cbObdobiOdInput) ?>">
    </label>
    <div class="head_quick gap_4 displ_flex jc_konec">
      <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="vcera">Včera</button>
      <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="tyden">Týden</button>
    </div>
  </div>

  <div class="head_int_row displ_grid">
    <label class="head_date text_11 gap_6 displ_flex">
      <span>Do</span>
      <input class="text_11 zaobleni_8 ram_ovladace" type="datetime-local" id="cbObdobiDo" value="<?= h($cbObdobiDoInput) ?>">
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
  var allowedModes = ['vcera', 'tyden', 'mesic', 'rok', 'manual'];
  if (activeMode === 'dnes') {
    activeMode = 'vcera';
  }

  if (allowedModes.indexOf(activeMode) === -1) {
    activeMode = 'manual';
  }

  function fmtDateTime(dt){
    var y = dt.getFullYear();
    var m = String(dt.getMonth() + 1).padStart(2, '0');
    var d = String(dt.getDate()).padStart(2, '0');
    var h = String(dt.getHours()).padStart(2, '0');
    var mi = String(dt.getMinutes()).padStart(2, '0');
    return y + '-' + m + '-' + d + 'T' + h + ':' + mi;
  }

  function parseDateTime(v){
    var s = String(v || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(s)) return null;
    var parts = s.split('T');
    var dateParts = parts[0].split('-');
    var timeParts = parts[1].split(':');
    var y = Number(dateParts[0]);
    var m = Number(dateParts[1]);
    var d = Number(dateParts[2]);
    var h = Number(timeParts[0]);
    var mi = Number(timeParts[1]);
    var dt = new Date(y, m - 1, d, h, mi, 0, 0);
    if (
      dt.getFullYear() !== y
      || (dt.getMonth() + 1) !== m
      || dt.getDate() !== d
      || dt.getHours() !== h
      || dt.getMinutes() !== mi
    ) {
      return null;
    }
    return dt;
  }

  function getCurrentWorkingDayStart(){
    var now = new Date();
    var today = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 6, 0, 0, 0);
    if (now.getHours() < 6) {
      today.setDate(today.getDate() - 1);
    }
    return today;
  }

  function getFinishedWorkingDayStart(){
    var start = new Date(getCurrentWorkingDayStart());
    start.setDate(start.getDate() - 1);
    return start;
  }

  function getFinishedWorkingDayEnd(){
    var end = new Date(getCurrentWorkingDayStart());
    return end;
  }

  function getNowMax(){
    var now = new Date();
    now.setSeconds(0, 0);
    return now;
  }

  function clampToMax(v, maxDate){
    var dt = parseDateTime(v);
    if (!dt) return '';
    if (dt.getTime() > maxDate.getTime()) {
      dt = new Date(maxDate);
    }
    return fmtDateTime(dt);
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
    var finishedDayStart = getFinishedWorkingDayStart();
    var finishedDayEnd = getFinishedWorkingDayEnd();
    var from = new Date(finishedDayStart);
    var to = new Date(finishedDayEnd);

    if (range === 'vcera') {
      return { od: fmtDateTime(from), do: fmtDateTime(to) };
    }

    if (range === 'tyden') {
      var day = finishedDayStart.getDay();
      var mondayShift = (day === 0 ? -6 : 1 - day);
      from.setDate(finishedDayStart.getDate() + mondayShift);
      from.setHours(6, 0, 0, 0);
      return { od: fmtDateTime(from), do: fmtDateTime(to) };
    }

    if (range === 'mesic') {
      from = new Date(finishedDayStart.getFullYear(), finishedDayStart.getMonth(), 1, 6, 0, 0, 0);
      return { od: fmtDateTime(from), do: fmtDateTime(to) };
    }

    from = new Date(finishedDayStart.getFullYear(), 0, 1, 6, 0, 0, 0);
    return { od: fmtDateTime(from), do: fmtDateTime(to) };
  }

  quickBtns.forEach(function(btn){
    btn.addEventListener('click', function(){
      var range = btn.getAttribute('data-range') || 'vcera';
      var val = computeRange(range);

      odInput.value = val.od;
      doInput.value = val.do;
      setActive(range);
      setManualHighlight(false);
      savePeriod({ od: val.od, do: val.do, mode: range });
    });
  });

  odInput.max = fmtDateTime(getNowMax());
  doInput.max = fmtDateTime(getNowMax());

  odInput.addEventListener('change', function(){
    var maxDate = getNowMax();
    var od = clampToMax(odInput.value, maxDate);
    var ddo = clampToMax(doInput.value, maxDate);
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
    var maxDate = getNowMax();
    var od = clampToMax(odInput.value, maxDate);
    var ddo = clampToMax(doInput.value, maxDate);
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
