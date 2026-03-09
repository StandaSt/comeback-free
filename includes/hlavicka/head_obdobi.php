<?php
// includes/hlavicka/head_obdobi.php * Verze: V2 * Aktualizace: 07.03.2026
?>
<div class="head_interval" aria-label="Období">
  <!-- Řádek "Od" + rychlé volby -->
  <div class="head_int_row">
    <label class="head_date">
      <span>Od</span>
      <input type="date" id="cbObdobiOd" value="<?= h($cbObdobiOd) ?>">
    </label>
    <div class="head_quick">
      <button type="button" class="head_pill<?= ($cbObdobiTyp === 'vcera' ? ' is-on' : '') ?>" data-range="vcera">Včera</button>
      <button type="button" class="head_pill<?= ($cbObdobiTyp === 'tyden' ? ' is-on' : '') ?>" data-range="tyden">Týden</button>
    </div>
  </div>

  <!-- Řádek "Do" + rychlé volby -->
  <div class="head_int_row">
    <label class="head_date">
      <span>Do</span>
      <input type="date" id="cbObdobiDo" value="<?= h($cbObdobiDo) ?>">
    </label>
    <div class="head_quick">
      <button type="button" class="head_pill<?= ($cbObdobiTyp === 'mesic' ? ' is-on' : '') ?>" data-range="mesic">Měsíc</button>
      <button type="button" class="head_pill<?= ($cbObdobiTyp === 'rok' ? ' is-on' : '') ?>" data-range="rok">Rok</button>
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

  // Převod Date -> YYYY-MM-DD.
  function fmtDate(dt){
    var y = dt.getFullYear();
    var m = String(dt.getMonth() + 1).padStart(2, '0');
    var d = String(dt.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  // Aktivace vizuálního stavu u rychlých voleb.
  function setActive(range){
    quickBtns.forEach(function(btn){
      btn.classList.toggle('is-on', btn.getAttribute('data-range') === range);
    });
  }

  // Uloží období do session přes index.php bez další akce.
  function savePeriod(od, ddo, typ){
    fetch('<?= h(cb_url('index.php')) ?>', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Comeback-Set-Period': '1'
      },
      body: JSON.stringify({ od: od, do: ddo, typ: typ })
    }).catch(function(){
      // Zatím bez notifikace; jen tichý fail.
    });
  }

  // Vypočte hranice rychlé volby období.
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
      var day = today.getDay(); // 0 = nedele, 1 = pondeli
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

    // "rok" = od 1.1. do dneška.
    from = new Date(today.getFullYear(), 0, 1);
    return { od: fmtDate(from), do: fmtDate(today) };
  }

  // Klik na rychlou volbu nastaví datumy + uloží do session.
  quickBtns.forEach(function(btn){
    btn.addEventListener('click', function(){
      var range = btn.getAttribute('data-range') || 'vcera';
      var val = computeRange(range);
      odInput.value = val.od;
      doInput.value = val.do;
      setActive(range);
      savePeriod(val.od, val.do, range);
    });
  });

  // Ruční změna datumů přepne typ na "vlastni".
  function onManualChange(){
    if (!odInput.value || !doInput.value) {
      return;
    }
    setActive('');
    savePeriod(odInput.value, doInput.value, 'vlastni');
  }

  odInput.addEventListener('change', onManualChange);
  doInput.addEventListener('change', onManualChange);
})();
</script>
