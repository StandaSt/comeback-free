<?php
// includes/hlavicka/head_pobocka.php * Verze: V1 * Aktualizace: 07.03.2026
declare(strict_types=1);
?>
<div class="head_branch" aria-label="Pobočka">
  <select id="cbPobockaSelect" class="head_branch_select" data-cb-branch-select="1" <?= $cbPobocky ? '' : 'disabled' ?> >
    <?php if ($cbPobocky): ?>
      <?php if (!empty($cbPobockaMultiFromCard)): ?>
        <option value="" selected>Vybráno z karty</option>
      <?php endif; ?>
      <?php foreach ($cbPobocky as $p): ?>
        <option value="<?= (int)$p['id_pob'] ?>"<?= (empty($cbPobockaMultiFromCard) && ((int)$p['id_pob'] === (int)$cbPobockaId) ? ' selected' : '') ?>><?= h($p['nazev']) ?></option>
      <?php endforeach; ?>
    <?php else: ?>
      <option value="">Pobočka</option>
    <?php endif; ?>
  </select>
</div>

<script>
(function(){
  var sel = document.querySelector('[data-cb-branch-select="1"]');
  if (!sel) return;

  sel.addEventListener('change', function(){
    var id = parseInt(sel.value || '0', 10);
    if (!id || !Number.isFinite(id)) return;

    fetch('<?= h(cb_url('index.php')) ?>', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Comeback-Set-Branch': '1'
      },
      body: JSON.stringify({ id_pob: id })
    }).catch(function(){
      // Tichy fail.
    });
  });
})();
</script>
