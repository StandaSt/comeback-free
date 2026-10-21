<?php
// Jediny souhrnny radek s oznacenim kontroly, obdobim a uplnosti ziveho ci archivniho snimku.
declare(strict_types=1);
// Pocet dnu patri celemu vybranemu obdobi, nikoli jen poctu nalezenych reportu.
$ktPeriodFrom = $ktArchive ? $ktDetail['datum_od'] : $ktFilters['from'];
$ktPeriodTo = $ktArchive ? $ktDetail['datum_do'] : $ktFilters['to'];
$ktDayCount = (new DateTimeImmutable($ktPeriodFrom))->diff(new DateTimeImmutable($ktPeriodTo))->days + 1;
?>
<div class="kt_summary">
  <?php // Skutecne cislo vznika az pri ulozeni; rozepsane kontrole zadne nepredpovidame. ?>
  <strong><?= $ktArchive ? 'Kontrola #' . h((string)$ktId) : 'Nová kontrola' ?> · <?= h(cb_format('d', $ktPeriodFrom)) ?> – <?= h(cb_format('d', $ktPeriodTo)) ?></strong>
  <p class="kt_sources">Počet dnů: <?= h((string)$ktDayCount) ?> · <?= $ktSources['missing'] === [] ? 'reporty všechny' : 'chybí report ' . h(implode(', ', array_map(static fn(string $day): string => cb_format('d', $day), $ktSources['missing']))) ?></p>
</div>
<?php foreach (['incomplete' => 'Neúplné reporty'] as $field => $label): ?>
  <?php if ($ktSources[$field] !== []): ?><p class="kt_warning"><?= h($label) ?>: <?= h(implode(', ', array_map(static fn(string $day): string => cb_format('d', $day), $ktSources[$field]))) ?>. Odevzdané obsahuje pouze dostupné podklady.</p><?php endif; ?>
<?php endforeach; ?>
<?php if ($ktSources['missing_prices'] > 0): ?><p class="kt_warning">U <?= h((string)$ktSources['missing_prices']) ?> objednávek chybí cena. Kontrolní součty objednávek nejsou dostupné.</p><?php endif; ?>
