<?php
// karty/admin_testy.php * Verze: V2 * Aktualizace: 17.03.2026
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

$card_min_html = '<p class="card_text">Toto je panel pro spousteni testovacich scriptu.</p>';

ob_start();
?>
<div class="card_stack cb-admin-testy">
  <div>
    <label class="card_field" for="cbAdminTestSelect">Vyber soubor
      <select id="cbAdminTestSelect" class="card_select card_control admin_testy_select" data-admin-testy-select>
        <option value="">Vyber *.php z admin_testy</option>
        <?php foreach ($items as $it): ?>
          <option
            value="<?= h((string)$it['url']) ?>"
            data-abs="<?= h((string)$it['abs']) ?>"
          ><?= h((string)$it['file']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <div id="cbAdminTestPathWrap" class="card_stack is-hidden admin_testy_path_wrap" data-admin-testy-path-wrap>
    <div class="card_small_text admin_testy_path_text">
      Cesta: <span id="cbAdminTestPath" data-admin-testy-path></span>
    </div>
    <div>
      <button id="cbAdminTestRun" type="button" data-admin-testy-run>Spustit</button>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var root = document.querySelector('.cb-admin-testy');
  if (!root) {
    return;
  }

  var select = root.querySelector('[data-admin-testy-select]');
  var pathWrap = root.querySelector('[data-admin-testy-path-wrap]');
  var path = root.querySelector('[data-admin-testy-path]');
  var runBtn = root.querySelector('[data-admin-testy-run]');

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
      pathWrap.classList.add('is-hidden');
      return;
    }

    path.textContent = abs;
    pathWrap.classList.remove('is-hidden');
  });

  runBtn.addEventListener('click', function () {
    if (!selectedUrl) return;
    window.open(selectedUrl, '_blank', 'noopener');
  });
})();
</script>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/admin_testy.php * Verze: V2 * Aktualizace: 17.03.2026 */
?>
