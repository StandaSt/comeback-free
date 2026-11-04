<?php
/* Účel: Zobrazí společný přehled nasazených verzí z datového souboru bez přístupu do DB. */
declare(strict_types=1);

// Přehled patří jen do přihlášené kostry; přímé otevření souboru nic nezveřejní.
if (empty($_SESSION['login_ok'])) {
    return;
}

$cbVerzeVydani = [];
$cbVerzeChyba = false;
try {
    $cbVerzeSoubor = dirname(__DIR__, 3) . '/data/verze/vydani.json';
    if (!is_readable($cbVerzeSoubor)) {
        throw new RuntimeException('Chybí čitelný přehled verzí.');
    }
    $cbVerzeData = json_decode((string)file_get_contents($cbVerzeSoubor), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($cbVerzeData) || !isset($cbVerzeData['vydani']) || !is_array($cbVerzeData['vydani'])) {
        throw new RuntimeException('Neplatný přehled verzí.');
    }
    // Chybný záznam nesmí rozbít celou stránku ani vypsat neověřený obsah.
    foreach ($cbVerzeData['vydani'] as $cbVerzeRadek) {
        if (!is_array($cbVerzeRadek) || !is_string($cbVerzeRadek['verze'] ?? null)
            || !is_string($cbVerzeRadek['datum'] ?? null)) {
            throw new RuntimeException('Neplatné vydání.');
        }
        $cbVerzeDatum = DateTimeImmutable::createFromFormat('!Y-m-d', $cbVerzeRadek['datum']);
        if (!$cbVerzeDatum || $cbVerzeDatum->format('Y-m-d') !== $cbVerzeRadek['datum'] || trim($cbVerzeRadek['verze']) === '') {
            throw new RuntimeException('Neplatné datum nebo verze.');
        }
        foreach (['doplneno', 'vylepseno', 'opraveno'] as $cbVerzeKategorie) {
            $cbVerzeBody = $cbVerzeRadek[$cbVerzeKategorie] ?? [];
            if (!is_array($cbVerzeBody) || count(array_filter($cbVerzeBody, 'is_string')) !== count($cbVerzeBody)) {
                throw new RuntimeException('Neplatné body vydání.');
            }
        }
        $cbVerzeVydani[] = $cbVerzeRadek;
    }
    // Při více vydáních téhož dne rozhoduje přirozené pořadí čísla verze.
    usort($cbVerzeVydani, static fn(array $a, array $b): int => strcmp($b['datum'], $a['datum']) ?: strnatcmp($b['verze'], $a['verze']));
} catch (Throwable $cbVerzeVyjimka) {
    $cbVerzeChyba = true;
    $cbVerzeVydani = [];
    error_log('Přehled verzí: ' . $cbVerzeVyjimka->getMessage());
}
?>
<dialog id="cb-verze-dialog" class="cb_verze_dialog" aria-labelledby="cb-verze-title">
    <div class="cb_verze_head">
        <h2 id="cb-verze-title">Verze a novinky</h2>
        <button type="button" class="cb_verze_close" data-cb-verze-close aria-label="Zavřít přehled verzí" autofocus>×</button>
    </div>
    <div class="cb_verze_content">
        <?php if ($cbVerzeChyba): ?>
            <p>Přehled verzí se nyní nepodařilo načíst.</p>
        <?php elseif ($cbVerzeVydani === []): ?>
            <p>Zde budeme uvádět, co se v IS doplnilo, vylepšilo a opravilo. Přehled vydání zatím není vyplněný.</p>
        <?php else: ?>
            <?php foreach ($cbVerzeVydani as $cbVerzeIndex => $cbVerzeRadek): ?>
                <details class="cb_verze_release"<?= $cbVerzeIndex === 0 ? ' open' : '' ?>>
                    <summary><strong>Verze <?= h($cbVerzeRadek['verze']) ?></strong><time datetime="<?= h($cbVerzeRadek['datum']) ?>"><?= h((new DateTimeImmutable($cbVerzeRadek['datum']))->format('j. n. Y')) ?></time></summary>
                    <?php foreach (['doplneno' => 'Doplněno', 'vylepseno' => 'Vylepšeno', 'opraveno' => 'Opraveno'] as $cbVerzeKategorie => $cbVerzeNazev): ?>
                        <?php if (!empty($cbVerzeRadek[$cbVerzeKategorie])): ?>
                            <h3><?= h($cbVerzeNazev) ?></h3>
                            <ul><?php foreach ($cbVerzeRadek[$cbVerzeKategorie] as $cbVerzeBod): ?><li><?= h($cbVerzeBod) ?></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</dialog>
