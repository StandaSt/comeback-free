<?php
// pages/dashboard.php * Verze: V1 * Aktualizace: 1.3.2026
declare(strict_types=1);

/*
 * DASHBOARD (dashboard-first)
 * - bloky (náhledy) + maximalizace (A = full karta v dashboardu)
 * - bez funkční logiky dat
 */

$view = (string)($_GET['view'] ?? '');
$view = preg_replace('~[^a-z0-9_]+~i', '', $view);

function cb_dash_block_path(string $key): string {
    return __DIR__ . '/../blocks/blok_' . $key . '.php';
}
?>

<style>
/* ===== DASHBOARD V1 (lokální CSS, zatím inline) ===== */
.uk-grid{
  display:grid;
  grid-template-columns: repeat(12, 1fr);
  gap:12px;
}
.uk-col-12{ grid-column: span 12; }
.uk-col-8{ grid-column: span 8; }
.uk-col-6{ grid-column: span 6; }
.uk-col-4{ grid-column: span 4; }
.uk-col-3{ grid-column: span 3; }

.uk-cardhead{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  padding-bottom:8px;
  border-bottom:1px solid rgba(0,0,0,.08);
  margin-bottom:10px;
}
.uk-title{
  font-size:13px;
  font-weight:800;
  display:flex;
  align-items:center;
  gap:8px;
}
.uk-no{
  font-weight:700;
  color:rgba(0,0,0,.45);
}
.uk-actions{ display:flex; gap:6px; }
.uk-mini{ width:34px; height:34px; border-radius:10px; font-size:18px; }
.uk-body{ font-size:13px; color:#222; }

.uk-kpi{
  display:grid;
  grid-template-columns: repeat(3, minmax(0,1fr));
  gap:10px;
  margin-bottom:10px;
}
.uk-kpi small{ display:block; color:rgba(0,0,0,.55); font-size:12px; margin-bottom:2px; }
.uk-kpi b{ font-size:16px; }

.uk-note{ margin:8px 0 0 0; color:rgba(0,0,0,.60); font-size:12px; }

.uk-spark{
  display:flex;
  gap:6px;
  align-items:flex-end;
  height:46px;
}
.uk-spark span{
  width:10px;
  height: calc(var(--h, 30) * 1%);
  background: rgba(63,48,146,.35);
  border-radius:6px 6px 2px 2px;
}

.uk-gauge{
  height:66px;
  border-radius:14px;
  border:1px solid rgba(0,0,0,.10);
  background: linear-gradient(90deg, rgba(63,48,146,.12) 0%, rgba(63,48,146,.12) calc(var(--p,50) * 1%), rgba(0,0,0,.03) calc(var(--p,50) * 1%), rgba(0,0,0,.03) 100%);
  display:flex;
  align-items:center;
  justify-content:center;
  font-weight:900;
}

.uk-bars{ display:grid; gap:8px; }
.uk-bars div{ display:flex; align-items:center; gap:10px; }
.uk-bars i{
  height:10px;
  border-radius:999px;
  background: rgba(63,48,146,.35);
  display:block;
  flex:0 0 auto;
}
.uk-bars span{ color:rgba(0,0,0,.65); font-size:12px; }

.uk-heat{
  display:grid;
  grid-template-columns: repeat(7, 1fr);
  gap:4px;
}
.uk-heat i{
  height:12px;
  border-radius:4px;
  background: rgba(0,0,0,.06);
}
.uk-rank{ margin:0; padding-left:18px; display:grid; gap:6px; }
.uk-rank li{ display:flex; justify-content:space-between; gap:10px; }
.uk-rank span{ color:rgba(0,0,0,.55); }

.uk-tags{ display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px; }
.uk-tags .t{
  font-size:12px;
  padding:4px 8px;
  border-radius:999px;
  border:1px solid rgba(0,0,0,.10);
  background:#fff;
}
.uk-tags .ok{ background: rgba(22,163,74,.10); border-color: rgba(22,163,74,.30); }
.uk-tags .warn{ background: rgba(245,158,11,.14); border-color: rgba(245,158,11,.40); }
.uk-tags .bad{ background: rgba(220,38,38,.12); border-color: rgba(220,38,38,.35); }
.uk-tags .info{ background: rgba(37,99,235,.10); border-color: rgba(37,99,235,.35); }

.uk-list{ margin:0; padding-left:18px; display:grid; gap:6px; }
.uk-list b{ font-weight:800; }

.uk-back{
  display:inline-flex;
  align-items:center;
  gap:8px;
  margin-bottom:10px;
  font-size:13px;
  text-decoration:none;
}
</style>

<?php
echo '<div class="page-head"><h2>Dashboard</h2></div>';

if ($view !== '') {
    $file = cb_dash_block_path($view);

    echo '<a href="#" class="uk-back" id="cbBackDash">← Zpět na přehled</a>';

    if (is_file($file)) {
        require $file;
    } else {
        echo '<section class="card"><p>Blok nenalezen.</p></section>';
    }

    ?>
    <script>
    (function(){
      var b = document.getElementById('cbBackDash');
      if (!b) return;
      b.addEventListener('click', function(ev){
        ev.preventDefault();
        // přepneme zpět na Dashboard přes menu AJAX (bez URL)
        try{
          var el = document.querySelector('.cb-nav[data-page="dashboard"]');
          if (el) el.click();
        }catch(e){}
      });
    })();
    </script>
    <?php

    return;
}
?>

<div class="uk-grid" id="cbDashGrid">
  <div class="uk-col-6"><?php require __DIR__ . '/../blocks/blok_trzby.php'; ?></div>
  <div class="uk-col-6"><?php require __DIR__ . '/../blocks/blok_zisk.php'; ?></div>

  <div class="uk-col-4"><?php require __DIR__ . '/../blocks/blok_obj.php'; ?></div>
  <div class="uk-col-4"><?php require __DIR__ . '/../blocks/blok_hodiny.php'; ?></div>
  <div class="uk-col-4"><?php require __DIR__ . '/../blocks/blok_top.php'; ?></div>

  <div class="uk-col-12"><?php require __DIR__ . '/../blocks/blok_alerty.php'; ?></div>
</div>

<script>
(function(){
  function qs(sel){ return document.querySelector(sel); }
  function qsa(sel){ return Array.prototype.slice.call(document.querySelectorAll(sel)); }

  // hide card
  qsa('[data-uk-hide]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var card = btn.closest('section.card');
      if (card) card.style.display = 'none';
    });
  });

  // max/detail (A = full karta v dashboardu) => zavoláme dashboard s ?view=
  function openView(key){
    var url = (function(){
      try{
        var u = new URL(window.location.href);
        u.search = '?view=' + encodeURIComponent(key);
        u.hash = '';
        return u.toString();
      }catch(e){
        return window.location.href.split('?')[0] + '?view=' + encodeURIComponent(key);
      }
    })();

    var headers = {
      'X-Comeback-Partial':'1',
      'X-Comeback-Page':'dashboard'
    };

    var main = document.querySelector('.central-content main') || document.querySelector('main');
    if (main) main.innerHTML = '<section class="card"><p>Načítám…</p></section>';

    if (!window.CB_AJAX || typeof window.CB_AJAX.fetchText !== 'function') return;

    window.CB_AJAX.fetchText(url, headers, null).then(function(html){
      if (main) main.innerHTML = html;
      try { history.replaceState(null, '', window.location.href.split('?')[0]); } catch(e){}
    }).catch(function(){});
  }

  qsa('[data-uk-max],[data-uk-detail]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var k = btn.getAttribute('data-uk-max') || btn.getAttribute('data-uk-detail') || '';
      if (!k) return;
      openView(k);
    });
  });
})();
</script>

<?php
/* pages/dashboard.php * Verze: V1 * Aktualizace: 1.3.2026 * Počet řádků: 209 */
// Konec souboru
