<?php
declare(strict_types=1);

/*
 * Pohled kategorie PHP a Apache.
 * Prevede technicky stav sluzeb a logu do srozumitelnych barevnych karet.
 */

require_once __DIR__ . '/../../admin_lib/admin_system_health.php';

$health = cb_admin_system_health(db());
$statusLabels = [
    'ok' => 'V pořádku',
    'warning' => 'Upozornění',
    'error' => 'Závažná chyba',
    'unknown' => 'Nelze ověřit',
];
?>
<div class="admin_chyby_health_summary is-<?= h((string)$health['status']) ?>">
    Celkový stav: <strong><?= h($statusLabels[(string)$health['status']] ?? 'Nelze ověřit') ?></strong>
</div>

<section class="admin_chyby_health_grid" aria-label="Stav služeb">
    <?php foreach ($health['services'] as $service): ?>
        <article class="admin_chyby_health_card is-<?= h((string)$service['status']) ?>">
            <h3><?= h((string)$service['label']) ?></h3>
            <strong><?= h($statusLabels[(string)$service['status']] ?? 'Nelze ověřit') ?></strong>
            <p><?= h((string)$service['message']) ?></p>
            <?php if ((string)$service['detail'] !== ''): ?><small><?= h((string)$service['detail']) ?></small><?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<section class="admin_rights_editor_panel">
    <h3>Technické logy za posledních 24 hodin</h3>
    <div class="admin_chyby_health_grid">
        <?php foreach ($health['logs'] as $log): ?>
            <article class="admin_chyby_health_card is-<?= h((string)$log['status']) ?>">
                <h3><?= h((string)$log['label']) ?></h3>
                <strong><?= h($statusLabels[(string)$log['status']] ?? 'Nelze ověřit') ?></strong>
                <p><?= h((string)$log['message']) ?></p>
                <dl>
                    <div><dt>Závažné</dt><dd><?= h((string)(int)$log['errors']) ?></dd></div>
                    <div><dt>Upozornění</dt><dd><?= h((string)(int)$log['warnings']) ?></dd></div>
                    <div><dt>Poslední změna</dt><dd><?= h($formatKdy($log['modified_at'] ?? null)) ?></dd></div>
                </dl>
                <details><summary>Technický detail</summary><code><?= h((string)($log['path'] ?: 'Cesta není dostupná.')) ?></code></details>
            </article>
        <?php endforeach; ?>
    </div>
</section>

