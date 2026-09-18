<?php
declare(strict_types=1);

/*
 * Vstupni stranka Prehledu chyb.
 * Vybere jednu kategorii a nacte pouze jeji datovou vrstvu a pohled.
 */

if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(106)) {
    http_response_code(403);
    echo '<section class="blok"><h2 class="blok_title">Přístup zamítnut</h2><p>Nemáte právo zobrazit přehled chyb.</p></section>';
    return;
}

$kategorie = [
    'aplikace' => [
        'label' => 'Aplikační chyby',
        'description' => 'Neočekávané chyby IS evidované v centrálním logu.',
        'file' => __DIR__ . '/chyby/admin_chyby_aplikace.php',
    ],
    'prihlaseni' => [
        'label' => 'Přihlášení a 2FA',
        'description' => 'Pokusy o přihlášení, následné úspěchy a průběh 2FA.',
        'file' => __DIR__ . '/chyby/admin_chyby_prihlaseni.php',
    ],
    'ai' => [
        'label' => 'AI analytik',
        'description' => 'Neúspěšné a odmítnuté AI audity bez promptů a SQL.',
        'file' => __DIR__ . '/chyby/admin_chyby_ai.php',
    ],
    'system' => [
        'label' => 'PHP a Apache',
        'description' => 'Srozumitelný stav webu, PHP, databáze a technických logů.',
        'file' => __DIR__ . '/chyby/admin_chyby_system.php',
    ],
];

$aktivniKategorie = strtolower(trim((string)($_GET['cat'] ?? 'aplikace')));
if (!isset($kategorie[$aktivniKategorie])) {
    $aktivniKategorie = 'aplikace';
}
$baseUrl = cb_root_url('index.php?m=administrace&page=log_chyby');

/** Prevede databazovy cas do jednotneho formatu administrace. */
$formatKdy = static function (?string $kdy): string {
    if (!is_string($kdy) || $kdy === '') {
        return '—';
    }
    $timestamp = strtotime($kdy);
    return $timestamp === false ? $kdy : date('j. n. Y H:i:s', $timestamp);
};
?>
<section class="blok">
    <div class="admin_rights_editor admin_log_chyby">
        <nav class="admin_chyby_tabs" aria-label="Kategorie přehledu chyb">
            <?php foreach ($kategorie as $key => $config): ?>
                <a
                    href="<?= h($baseUrl . '&cat=' . rawurlencode($key)) ?>"
                    class="admin_chyby_tab<?= $aktivniKategorie === $key ? ' is-active' : '' ?>"
                    <?= $aktivniKategorie === $key ? 'aria-current="page"' : '' ?>
                ><?= h($config['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <p class="admin_chyby_description"><?= h($kategorie[$aktivniKategorie]['description']) ?></p>

        <?php require $kategorie[$aktivniKategorie]['file']; ?>
    </div>
</section>
