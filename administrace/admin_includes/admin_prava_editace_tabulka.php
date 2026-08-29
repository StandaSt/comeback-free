<?php
declare(strict_types=1);

/*
 * Účel souboru: Vykreslí editovatelnou tabulku práv jednoho modulu pro AJAX odpověď.
 * Každý řádek ukládá vlastní název a popis; šipky mění pouze pořadí.
 */

/** Převede připravená práva na HTML tabulku. */
function cb_admin_prava_editace_tabulka_html(array $rights): string
{
    ob_start();
    if ($rights === []) {
        ?>
        <p class="admin_rights_editor_empty">Vybraný modul zatím nemá žádná práva.</p>
        <?php
        return trim((string)ob_get_clean());
    }
    ?>
    <div class="admin_matrix_wrap">
        <table class="admin_matrix admin_rights_editor_matrix">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Název</th>
                    <th>Popis</th>
                    <th>Pořadí</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rights as $index => $right): ?>
                    <tr
                        data-admin-pravo-radek
                        data-id-pravo="<?= h((string)$right['id_pravo']) ?>"
                        data-puvodni-nazev="<?= h((string)$right['nazev']) ?>"
                        data-puvodni-popis="<?= h((string)$right['popis']) ?>"
                    >
                        <td><?= h((string)$right['id_pravo']) ?></td>
                        <td>
                            <input
                                type="text"
                                value="<?= h((string)$right['nazev']) ?>"
                                maxlength="100"
                                data-admin-pravo-nazev
                            >
                        </td>
                        <td>
                            <input
                                type="text"
                                value="<?= h((string)$right['popis']) ?>"
                                maxlength="255"
                                data-admin-pravo-popis
                            >
                        </td>
                        <td class="admin_rights_editor_order">
                            <button
                                type="button"
                                data-admin-pravo-posun="nahoru"
                                title="Posunout nahoru"
                                aria-label="Posunout právo <?= h((string)$right['nazev']) ?> nahoru"
                                <?= $index === 0 ? 'disabled' : '' ?>
                            >↑</button>
                            <button
                                type="button"
                                data-admin-pravo-posun="dolu"
                                title="Posunout dolů"
                                aria-label="Posunout právo <?= h((string)$right['nazev']) ?> dolů"
                                <?= $index === count($rights) - 1 ? 'disabled' : '' ?>
                            >↓</button>
                        </td>
                        <td>
                            <button type="button" data-admin-pravo-ulozit hidden>Uložit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return trim((string)ob_get_clean());
}
