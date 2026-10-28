<?php
// Zobrazí archivní součty a vedle nich nabídku náhledu e-mailu pouze pro kompletní období.
declare(strict_types=1);
$ktSumMoney = static fn(?int $value): string => $value === null ? '—' : cb_format('p2', cb_kt_decimal($value));
$ktSumNames = static fn(array $ids): string => implode(', ', array_map(static fn(int $id): string => $ktBranches[$id]['name'], $ids));
?>
<div class="kt_summary_row">
<section class="kt_page kt_saved_summary" aria-label="Součet tržby a stravenek">
  <h2>Součet tržby a stravenek</h2>
  <div class="kt_saved_amounts"><div>Spočítaná tržba<strong><?= h($ktSumMoney($ktSavedSummary['trzba'])) ?></strong></div><div>Spočítané stravenky<strong><?= h($ktSumMoney($ktSavedSummary['stravenky'])) ?></strong></div></div>
  <p>Zahrnuty pobočky: <?= $ktSavedSummary['branches'] === [] ? 'žádné' : h($ktSumNames($ktSavedSummary['branches'])) ?></p>
  <?php if ($ktSavedSummary['duplicates'] !== []): ?><p class="kt_warning">Součet nelze zobrazit. Více platných kontrol za toto období mají pobočky: <?= h($ktSumNames($ktSavedSummary['duplicates'])) ?>.</p><?php endif; ?>
  <?php if ($ktSavedSummary['incomplete']): ?><p class="kt_warning">Některé uložené spočítané částky nejsou vyplněné.</p><?php endif; ?>
</section>
<?php if (!empty($ktEmailReady)): ?>
<section class="kt_page kt_email" data-kt-email data-url="<?= h(cb_module_asset_url('lib/odeslat_kontrolu_trzeb.php','provoz')) ?>" data-crf="<?= h(cb_crf_token()) ?>" data-from="<?= h($ktFilters['from']) ?>" data-to="<?= h($ktFilters['to']) ?>" data-branch="<?= $ktFilters['branch'] ?>">
  <button type="button" class="kt_confirm_save" data-kt-email-preview>Odeslat výsledek kontroly na email Jan Hušner</button>
  <p role="status" data-kt-email-status hidden></p>
  <dialog class="kt_email_dialog" aria-label="Náhled e-mailu kontroly tržeb">
    <h2 tabindex="-1" autofocus>Náhled e-mailu</h2>
    <p data-kt-email-addresses></p><p><strong>Předmět: </strong><span data-kt-email-subject></span></p>
    <div class="kt_email_body" data-kt-email-body></div>
    <p role="status" data-kt-email-result hidden></p>
    <div class="kt_confirm_actions"><button type="button" class="kt_confirm_save" data-kt-email-send disabled>Odeslat</button><button type="button" data-kt-email-close>Zavřít</button></div>
  </dialog>
</section>
<?php endif; ?>
</div>
