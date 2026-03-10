<?php
// karty/admin_testy.php * Verze: V1 * Aktualizace: 10.03.2026
declare(strict_types=1);

$baseDir = realpath(__DIR__ . '/../admin_testy');
$items = [];

if (is_string($baseDir) && $baseDir !== '' && is_dir($baseDir)) {
    $all = scandir($baseDir);
    if (is_array($all)) {
        foreach ($all as $f) {
            if (!is_string($f) || $f === '.' || $f === '..') {
                continue;
            }
            if (!preg_match('~^[a-z0-9_][a-z0-9_.-]{0,120}\.php$~i', $f)) {
                continue;
            }

            $abs = $baseDir . DIRECTORY_SEPARATOR . $f;
            if (!is_file($abs)) {
                continue;
            }

            $items[] = [
                'file' => $f,
                'url' => cb_url('admin_testy/' . $f),
                'abs' => $abs,
            ];
        }
    }
}
?>

<article class="admin_testy_card">
  <div class="card_top">
    <div>
      <h3 class="card_title">Admin testy</h3>
      <p class="card_subtitle">Spouštění testovacích skriptů</p>
    </div>
  </div>

  <div class="card" style="border:0;padding:12px;">
    <p class="card_text">Toto je panel pro spouštění testovacích scriptů.</p>

    <div style="margin-top:10px;">
      <label for="cbAdminTestSelect">Vyber soubor</label>
      <select id="cbAdminTestSelect" style="display:block;width:100%;max-width:460px;margin-top:4px;">
        <option value="">Vyber *.php z admin_testy</option>
        <?php foreach ($items as $it): ?>
          <option
            value="<?= h((string)$it['url']) ?>"
            data-abs="<?= h((string)$it['abs']) ?>"
          ><?= h((string)$it['file']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div id="cbAdminTestPathWrap" style="display:none;margin-top:10px;">
      <div style="font-size:13px;">
        Cesta: <span id="cbAdminTestPath"></span>
      </div>
      <button id="cbAdminTestRun" type="button" style="margin-top:8px;">Spustit</button>
    </div>
  </div>
</article>

<script>
(function () {
  'use strict';

  var select = document.getElementById('cbAdminTestSelect');
  var pathWrap = document.getElementById('cbAdminTestPathWrap');
  var path = document.getElementById('cbAdminTestPath');
  var runBtn = document.getElementById('cbAdminTestRun');

  if (!select || !pathWrap || !path || !runBtn) {
    return;
  }

  var selectedUrl = '';

  select.addEventListener('change', function () {
    var opt = select.options[select.selectedIndex];
    var url = String(select.value || '');
    var abs = opt ? String(opt.getAttribute('data-abs') || '') : '';

    selectedUrl = url;
    if (url === '') {
      path.textContent = '';
      pathWrap.style.display = 'none';
      return;
    }

    path.textContent = abs;
    pathWrap.style.display = 'block';
  });

  runBtn.addEventListener('click', function () {
    if (!selectedUrl) return;
    window.open(selectedUrl, '_blank', 'noopener');
  });
})();
</script>

<?php
/* karty/admin_testy.php * Verze: V1 * Aktualizace: 10.03.2026 */
?>
