<?php
declare(strict_types=1);

$employees = hr_fetch_employees($db);
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Seznam zaměstnanců</h2>
            <p class="muted">Reálná data z HR evidence</p>
        </div>
        <a class="primary-button" href="?page=novy_zamestnanec">+ Nový zaměstnanec</a>
    </div>

    <?php if ($employees === []): ?>
        <p class="empty-state">Zatím není vložený žádný zaměstnanec.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Zaměstnanec</th>
                        <th>Zařazení</th>
                        <th>Pracoviště</th>
                        <th>Typ vztahu</th>
                        <th>Datum nástupu</th>
                        <th>Stav</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><a href="?page=zamestnanec&id=<?= h($employee['id_person']) ?>"><?= h($employee['cele_jmeno']) ?></a></td>
                            <td><?= h((string)($employee['zarazeni'] ?? '-')) ?></td>
                            <td><?= h((string)($employee['pracoviste'] ?? '-')) ?></td>
                            <td><?= h((string)($employee['vztah_kod'] ?? '-')) ?></td>
                            <td><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></td>
                            <td><span class="badge <?= h($employee['stav_badge']) ?>"><?= h($employee['stav_label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
