<?php
declare(strict_types=1);

/*
 * Ucel souboru: Zobrazi dokumenty zamestnancu dostupne prihlasenemu uzivateli.
 * Samotne soubory odesila chraneny handler v modulu HR.
 */
$hrDocuments = hr_fetch_employee_documents($db, $cbHrIdUser);
$hrDocumentOpenUrl = static function (array $document): string {
    return cb_root_url('hr/hr_download/hr_dokument.php?' . http_build_query([
        'id_dokument' => (int)$document['id_dokument'],
        'verze' => (int)$document['verze'],
    ]));
};
?>
<section class="hr_panel">
    <div class="hr_panel_header">
        <h2 class="hr_panel_title">Dokumenty zaměstnanců</h2>
        <span class="hr_muted"><?= h((string)count($hrDocuments)) ?> souborů</span>
    </div>
    <?php if ($hrDocuments === []): ?>
        <p class="hr_empty_state">Nejsou evidované žádné dokumenty, které můžete zobrazit.</p>
    <?php else: ?>
        <div class="hr_table_wrap">
            <table class="hr_table">
                <thead>
                    <tr>
                        <th class="hr_table_cell hr_table_head">Zaměstnanec</th>
                        <th class="hr_table_cell hr_table_head">Typ</th>
                        <th class="hr_table_cell hr_table_head">Soubor</th>
                        <th class="hr_table_cell hr_table_head">Platnost do</th>
                        <th class="hr_table_cell hr_table_head">Uloženo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hrDocuments as $document): ?>
                        <tr>
                            <td class="hr_table_cell"><a class="hr_table_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$document['id_person']))) ?>"><?= h($document['osoba']) ?></a></td>
                            <td class="hr_table_cell"><?= h($document['typ']) ?></td>
                            <td class="hr_table_cell"><a class="hr_table_link" href="<?= h($hrDocumentOpenUrl($document)) ?>" target="_blank" rel="noopener"><?= h($document['ulozeny_nazev'] !== '' ? $document['ulozeny_nazev'] : $document['nazev']) ?></a></td>
                            <td class="hr_table_cell"><?= h(hr_format_date((string)($document['platnost_do'] ?? ''))) ?></td>
                            <td class="hr_table_cell"><?= h(hr_format_date($document['vytvoreno'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
