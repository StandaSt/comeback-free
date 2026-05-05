<?php
// includes/hlavicka/head_obdobi.php * Verze: V5 * Aktualizace: 27.04.2026
$cbObdobiOdInput = '';
$cbObdobiDoInput = '';
try {
    $cbObdobiOdInput = (new DateTimeImmutable((string)$cbObdobiOd))->format('Y-m-d');
} catch (Throwable $e) {
    $cbObdobiOdInput = '';
}
try {
    $cbObdobiDoInput = (new DateTimeImmutable((string)$cbObdobiDo))->format('Y-m-d');
} catch (Throwable $e) {
    $cbObdobiDoInput = '';
}
$cbObdobiCasOptions = [];
for ($i = 0; $i < 48; $i++) {
    $totalMinutes = (6 * 60) + ($i * 30);
    $h = intdiv($totalMinutes, 60) % 24;
    $m = $totalMinutes % 60;
    $value = sprintf('%02d:%02d', $h, $m);
    $label = (string)$h . ':' . sprintf('%02d', $m);
    $cbObdobiCasOptions[] = ['value' => $value, 'label' => $label];
}
?>
<div class="head_interval ram_hlavicka zaobleni_10 gap_4 displ_flex flex_sloupec jc_stred" aria-label="Období">
  <div class="head_int_row displ_grid">
    <label class="head_date text_11 gap_6 displ_flex">
      <span>Od</span>
      <input class="text_11 zaobleni_8 ram_ovladace" type="date" id="cbObdobiOd" value="<?= h($cbObdobiOdInput) ?>">
      <select class="text_11 zaobleni_8 ram_ovladace" id="cbObdobiOdCas" aria-label="Čas od">
        <?php foreach ($cbObdobiCasOptions as $opt): ?>
          <option value="<?= h($opt['value']) ?>"<?= ($opt['value'] === '06:00') ? ' selected' : '' ?>><?= h($opt['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="head_quick gap_4 displ_flex jc_konec">
      <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="vcera">Včera</button>
      <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="tyden">Týden</button>
    </div>
  </div>

  <div class="head_int_row displ_grid">
    <label class="head_date text_11 gap_6 displ_flex">
      <span>Do</span>
      <input class="text_11 zaobleni_8 ram_ovladace" type="date" id="cbObdobiDo" value="<?= h($cbObdobiDoInput) ?>">
      <select class="text_11 zaobleni_8 ram_ovladace" id="cbObdobiDoCas" aria-label="Čas do">
        <?php foreach ($cbObdobiCasOptions as $opt): ?>
          <option value="<?= h($opt['value']) ?>"<?= ($opt['value'] === '06:00') ? ' selected' : '' ?>><?= h($opt['label']) ?></option>
        <?php endforeach; ?>
      </select>
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
  var odCasInput = document.getElementById('cbObdobiOdCas');
  var doCasInput = document.getElementById('cbObdobiDoCas');
  var quickBtns = document.querySelectorAll('.head_interval .head_pill[data-range]');
  var odLabel = odInput ? odInput.closest('.head_date') : null;
  var doLabel = doInput ? doInput.closest('.head_date') : null;
  var activeMode = '<?= h($cbObdobiMode) ?>';

  if (!odInput || !doInput || !odCasInput || !doCasInput || !quickBtns.length) {
    return;
  }
  var isSaving = false;
  var defaultTime = '06:00';
  var odTimeKey = 'cb_obdobi_od_cas';
  var doTimeKey = 'cb_obdobi_do_cas';
  var allowedModes = ['vcera', 'tyden', 'mesic', 'rok', 'manual'];
  if (activeMode === 'dnes') {
    activeMode = 'vcera';
  }

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
    var dateParts = s.split('-');
    var y = Number(dateParts[0]);
    var m = Number(dateParts[1]);
    var d = Number(dateParts[2]);
    var dt = new Date(y, m - 1, d, 6, 0, 0, 0);
    if (
      dt.getFullYear() !== y
      || (dt.getMonth() + 1) !== m
      || dt.getDate() !== d
    ) {
      return null;
    }
    return dt;
  }

  function isTimeValue(v){
    return /^\d{2}:\d{2}$/.test(String(v || ''));
  }

  function loadTime(key){
    try {
      var v = window.sessionStorage ? window.sessionStorage.getItem(key) : '';
      return isTimeValue(v) ? v : defaultTime;
    } catch (e) {
      return defaultTime;
    }
  }

  function saveTime(key, value){
    try {
      if (window.sessionStorage && isTimeValue(value)) {
        window.sessionStorage.setItem(key, value);
      }
    } catch (e) {}
  }

  function periodValue(dateValue, timeValue){
    var date = String(dateValue || '').trim();
    var time = isTimeValue(timeValue) ? String(timeValue) : defaultTime;
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return '';
    return date + ' ' + time;
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
    if (odCasInput) odCasInput.classList.toggle('is-manual', !!isManual);
    if (doCasInput) doCasInput.classList.toggle('is-manual', !!isManual);
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
      return { od: fmtDate(from), do: fmtDate(to) };
    }

    if (range === 'tyden') {
      var day = finishedDayStart.getDay();
      var mondayShift = (day === 0 ? -6 : 1 - day);
      from.setDate(finishedDayStart.getDate() + mondayShift);
      from.setHours(6, 0, 0, 0);
      return { od: fmtDate(from), do: fmtDate(to) };
    }

    if (range === 'mesic') {
      from = new Date(finishedDayStart.getFullYear(), finishedDayStart.getMonth(), 1, 6, 0, 0, 0);
      return { od: fmtDate(from), do: fmtDate(to) };
    }

    from = new Date(finishedDayStart.getFullYear(), 0, 1, 6, 0, 0, 0);
    return { od: fmtDate(from), do: fmtDate(to) };
  }

  quickBtns.forEach(function(btn){
    btn.addEventListener('click', function(){
      var range = btn.getAttribute('data-range') || 'vcera';
      var val = computeRange(range);

      odInput.value = val.od;
      doInput.value = val.do;
      odCasInput.value = defaultTime;
      doCasInput.value = defaultTime;
      saveTime(odTimeKey, odCasInput.value);
      saveTime(doTimeKey, doCasInput.value);
      setActive(range);
      setManualHighlight(false);
      savePeriod({
        od: periodValue(val.od, odCasInput.value),
        do: periodValue(val.do, doCasInput.value),
        mode: range
      });
    });
  });

  odCasInput.value = loadTime(odTimeKey);
  doCasInput.value = loadTime(doTimeKey);
  odInput.max = fmtDate(getNowMax());
  doInput.max = fmtDate(getNowMax());

  function saveManualPeriod(){
    var maxDate = getNowMax();
    var od = clampToMax(odInput.value, maxDate);
    var ddo = clampToMax(doInput.value, maxDate);
    if (!od || !ddo) return;
    if (od > ddo) {
      od = ddo;
    }
    odInput.value = od;
    doInput.value = ddo;
    saveTime(odTimeKey, odCasInput.value);
    saveTime(doTimeKey, doCasInput.value);
    setActive('manual');
    setManualHighlight(true);
    savePeriod({
      od: periodValue(od, odCasInput.value),
      do: periodValue(ddo, doCasInput.value),
      mode: 'manual'
    });
  }

  odInput.addEventListener('change', saveManualPeriod);
  doInput.addEventListener('change', saveManualPeriod);
  odCasInput.addEventListener('change', saveManualPeriod);
  doCasInput.addEventListener('change', saveManualPeriod);

  setActive(activeMode);
  setManualHighlight(activeMode === 'manual');
})();
</script>
