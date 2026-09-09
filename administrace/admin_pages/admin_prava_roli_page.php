<?php
declare(strict_types=1);

/* Ucel souboru: Zobrazi globalni prava roli a stav jejich skutecne funkcnosti. */

require_once __DIR__ . '/../admin_db/admin_prava_roli_db.php';

$adminPravaData = cb_admin_prava_roli_data();
$adminRoles = $adminPravaData['roles'];
$adminModules = $adminPravaData['modules'];
$adminRights = $adminPravaData['rights'];
$adminAllowed = $adminPravaData['allowed'];
$adminShowBlockChecks = function_exists('cb_pravo_ma') && cb_pravo_ma(101);
?>
<?php if ($adminRights === []): ?>
    <div class="admin_empty blok">
        <h2 class="blok_title">Globální práva</h2>
        <p>V tabulce cis_prava zatím nejsou žádná práva.</p>
    </div>
<?php else: ?>
    <div class="admin_matrix_blocks">
        <?php foreach ($adminModules as $module): ?>
            <?php if ($module['rights'] === []): ?>
                <?php continue; ?>
            <?php endif; ?>
            <div class="admin_matrix_wrap">
                <table class="admin_matrix" style="width:auto; min-width:0;">
                    <colgroup>
                        <col style="width:72px;">
                        <col style="width:225px;">
                        <?php foreach ($adminRoles as $role): ?>
                            <col style="width:80px;">
                        <?php endforeach; ?>
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="admin_matrix_active_head">Aktivní</th>
                            <th style="white-space:nowrap;">Právo</th>
                            <?php foreach ($adminRoles as $role): ?>
                                <th class="admin_matrix_role_head"><?= h($role['role']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="admin_matrix_group">
                            <th></th>
                            <th style="white-space:nowrap;"><?= h($module['modul']) ?></th>
                            <?php foreach ($adminRoles as $role): ?>
                                <?php $idRole = (int)$role['id_role']; ?>
                                <th class="admin_matrix_check">
                                    <?php if ($adminShowBlockChecks): ?>
                                        <input
                                            type="checkbox"
                                            data-admin-blok="1"
                                            data-id-role="<?= h((string)$idRole) ?>"
                                            data-id-modul="<?= h((string)$module['id_modul']) ?>"
                                            title="Vybrat celý blok pro roli <?= h($role['role']) ?>"
                                        >
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        <?php foreach ($module['rights'] as $right): ?>
                            <?php $rightActive = !empty($right['aktivni']); ?>
                            <?php
                            $rightDescription = (string)$right['popis'];
                            $rightDescriptionShort = mb_strlen($rightDescription, 'UTF-8') > 33
                                ? mb_substr($rightDescription, 0, 30, 'UTF-8') . '...'
                                : $rightDescription;
                            ?>
                            <tr<?= $rightActive ? '' : ' class="is-inactive"' ?>>
                                <td class="admin_matrix_active">
                                    <span class="admin_matrix_right_id<?= !empty($right['aplikovano']) ? ' is-applied' : '' ?>"><?= h((string)$right['id_pravo']) ?></span>
                                    <input
                                        type="checkbox"
                                        data-admin-pravo-aktivni="1"
                                        data-id-pravo="<?= h((string)$right['id_pravo']) ?>"
                                        data-pravo-nazev="<?= h((string)$right['nazev']) ?>"
                                        <?= $rightActive ? 'checked' : '' ?>
                                        aria-label="Hlídání práva <?= h((string)$right['nazev']) ?>"
                                    >
                                </td>
                                <td style="white-space:nowrap;">
                                    <strong><?= h($right['nazev']) ?></strong>
                                    <?php if ($rightDescription !== ''): ?>
                                        <span<?= $rightDescription !== $rightDescriptionShort ? ' title="' . h($rightDescription) . '"' : '' ?>><?= h($rightDescriptionShort) ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($adminRoles as $role): ?>
                                    <?php
                                    $idRole = (int)$role['id_role'];
                                    $idPravo = (int)$right['id_pravo'];
                                    $checked = !empty($adminAllowed[$idRole][$idPravo]);
                                    $idVstupnihoPrava = (int)($right['vstupni_pravo'] ?? 0);
                                    $jeVstupniPravo = $idVstupnihoPrava === $idPravo;
                                    $jePodrizenePravo = $idVstupnihoPrava > 0 && !$jeVstupniPravo;
                                    $maVstupniPravo = !empty($adminAllowed[$idRole][$idVstupnihoPrava]);
                                    $disabled = !$rightActive || ($jePodrizenePravo && !$maVstupniPravo);
                                    $title = $jePodrizenePravo && !$maVstupniPravo
                                        ? 'Nejprve povolte vstupní právo modulu (' . $idVstupnihoPrava . ').'
                                        : '';
                                    ?>
                                    <td class="admin_matrix_check">
                                        <input
                                            type="checkbox"
                                            data-admin-pravo="1"
                                            data-id-role="<?= h((string)$idRole) ?>"
                                            data-id-pravo="<?= h((string)$idPravo) ?>"
                                            data-id-modul="<?= h((string)$module['id_modul']) ?>"
                                            data-vstupni-pravo="<?= $jeVstupniPravo ? '1' : '0' ?>"
                                            data-parent-pravo="<?= h((string)$idVstupnihoPrava) ?>"
                                            data-right-active="<?= $rightActive ? '1' : '0' ?>"
                                            <?= $checked ? 'checked' : '' ?>
                                            <?= $disabled ? 'disabled' : '' ?>
                                            <?= $title !== '' ? 'title="' . h($title) . '"' : '' ?>
                                        >
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
    <?php require __DIR__ . '/../admin_modaly/modal_pravo_aktivni.php'; ?>
<?php endif; ?>
