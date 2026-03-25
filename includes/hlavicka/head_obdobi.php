<?php
// includes/hlavicka/head_obdobi.php * Verze: V3 * Aktualizace: 25.03.2026
?>
<div class="head_interval" aria-label="Období">
  <div class="head_int_row">
    <label class="head_date">
      <span>Od</span>
      <input type="date" id="cbObdobiOd" value="<?= h($cbObdobiOd) ?>">
    </label>
    <div class="head_quick">
      <button type="button" class="head_pill" data-range="vcera">Včera</button>
      <button type="button" class="head_pill" data-range="tyden">Týden</button>
    </div>
  </div>

  <div class="head_int_row">
    <label class="head_date">
      <span>Do</span>
      <input type="date" id="cbObdobiDo" value="<?= h($cbObdobiDo) ?>">
    </label>
    <div class="head_quick">
      <button type="button" class="head_pill" data-range="mesic">Měsíc</button>
      <button type="button" class="head_pill" data-range="rok">Rok</button>
    </div>
  </div>
</div>

<script>
(function(){
  var odInput = document.getElementById('cbObdobiOd');
  var doInput = document.getElementById('cbObdobiDo');
  var quickBtns = document.querySelectorAll('.head_interval .head_pill[data-range]');

  if (!odInput || !doInput || !quickBtns.length) {
    return;
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

  function clampToToday(v){
    var dt = parseDate(v);
    if (!dt) return '';
    var now = new Date();
    var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    if (dt.getTime() > today.getTime()) {
      dt = today;
    }
    return fmtDate(dt);
  }

  function setActive(range){
    quickBtns.forEach(function(btn){
      btn.classList.toggle('is-on', btn.getAttribute('data-range') === range);
    });
  }

  function savePeriod(payload){
    fetch('<?= h(cb_url('index.php')) ?>', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Comeback-Set-Period': '1'
      },
      body: JSON.stringify(payload)
    }).catch(function(){});
  }

  function computeRange(range){
    var now = new Date();
    var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var from = new Date(today);
    var to = new Date(today);

    if (range === 'vcera') {
      from.setDate(today.getDate() - 1);
      return { od: fmtDate(from), do: fmtDate(to) };
    }

    if (range === 'tyden') {
      var day = today.getDay();
      var mondayShift = (day === 0 ? -6 : 1 - day);
      from.setDate(today.getDate() + mondayShift);
      to = new Date(from);
      to.setDate(from.getDate() + 6);
      return { od: fmtDate(from), do: fmtDate(to) };
    }

    if (range === 'mesic') {
      from = new Date(today.getFullYear(), today.getMonth(), 1);
      to = new Date(today.getFullYear(), today.getMonth() + 1, 0);
      return { od: fmtDate(from), do: fmtDate(to) };
    }

    from = new Date(today.getFullYear(), 0, 1);
    return { od: fmtDate(from), do: fmtDate(today) };
  }

  quickBtns.forEach(function(btn){
    btn.addEventListener('click', function(){
      var range = btn.getAttribute('data-range') || 'vcera';
      var val = computeRange(range);
      odInput.value = val.od;
      doInput.value = val.do;
      setActive(range);
      savePeriod({ od: val.od, do: val.do });
    });
  });

  var todayStr = fmtDate(new Date());
  odInput.max = todayStr;
  doInput.max = todayStr;

  odInput.addEventListener('change', function(){
    setActive('');
    var od = clampToToday(odInput.value);
    var ddo = clampToToday(doInput.value);
    if (!od || !ddo) return;
    if (od > ddo) {
      od = ddo;
    }
    odInput.value = od;
    savePeriod({ od: od });
  });

  doInput.addEventListener('change', function(){
    setActive('');
    var od = clampToToday(odInput.value);
    var ddo = clampToToday(doInput.value);
    if (!od || !ddo) return;
    if (ddo < od) {
      ddo = od;
    }
    doInput.value = ddo;
    savePeriod({ do: ddo });
  });
})();
</script>