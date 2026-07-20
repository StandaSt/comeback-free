<?php
declare(strict_types=1);

$nabor = hr_nacti_nabor_prehled($db);

$bloky = [
    [
        'title' => 'Nové veřejné dotazníky',
        'rows' => $nabor['nove_dotazniky'],
        'date_key' => 'zadano',
        'date_label' => 'Zadáno',
    ],
    [
        'title' => 'Domluvené pohovory',
        'rows' => $nabor['domluvene_pohovory'],
        'date_key' => 'planovano_na',
        'date_label' => 'Pohovor',
    ],
    [
        'title' => 'Čekáme na vstupní dotazník',
        'rows' => $nabor['ceka_na_vstupni_dotaznik'],
        'date_key' => 'odeslano',
        'date_label' => 'Odesláno',
    ],
    [
        'title' => 'Čekáme na podepsanou smlouvu',
        'rows' => $nabor['ceka_na_smlouvu'],
        'date_key' => 'posledni_aktivita',
        'date_label' => 'Aktivita',
    ],
];
?>
<?php foreach ($bloky as $blok): ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2><?= h($blok['title']) ?></h2>
                <p class="muted"><?= h(hr_pocet_uchazecu_text(count($blok['rows']))) ?></p>
            </div>
        </div>

        <?php if ($blok['rows'] === []): ?>
            <p class="empty-state">Aktuálně žádný uchazeč.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Uchazeč</th>
                            <th>Telefon</th>
                            <th>E-mail</th>
                            <th>Pozice</th>
                            <th>Pracoviště</th>
                            <th><?= h($blok['date_label']) ?></th>
                            <th>Stav</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blok['rows'] as $uchazec): ?>
                            <tr>
                                <td><?= h($uchazec['cele_jmeno']) ?></td>
                                <td><?= h($uchazec['telefon']) ?></td>
                                <td><?= h($uchazec['email']) ?></td>
                                <td><?= h($uchazec['pozice']) ?></td>
                                <td><?= h($uchazec['pracoviste_preference']) ?></td>
                                <td><?= h(hr_format_date((string)($uchazec[$blok['date_key']] ?? ''))) ?></td>
                                <td><span class="badge neutral"><?= h($uchazec['stav_nazev']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
