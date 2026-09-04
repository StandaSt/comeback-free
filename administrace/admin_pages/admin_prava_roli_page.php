<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin_db/admin_prava_roli_db.php';

$adminPravaData = cb_admin_prava_roli_data();
$adminRoles = $adminPravaData['roles'];
$adminModules = $adminPravaData['modules'];
$adminRights = $adminPravaData['rights'];
$adminAllowed = $adminPravaData['allowed'];
$adminShowBlockChecks = function_exists('cb_user_ma_roli') && cb_user_ma_roli(1);
$adminCanToggleApplied = function_exists('cb_pravo_ma') && cb_pravo_ma(106);
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
                        <col style="width:200px;">
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
                            <tr<?= $rightActive ? '' : ' class="is-inactive"' ?>>
                                <td class="admin_matrix_active">
                                    <?php if ($adminCanToggleApplied): ?>
                                        <button
                                            class="admin_matrix_right_id<?= !empty($right['aplikovano']) ? ' is-applied' : '' ?>"
                                            type="button"
                                            data-admin-pravo-aplikovano="1"
                                            data-id-pravo="<?= h((string)$right['id_pravo']) ?>"
                                            aria-pressed="<?= !empty($right['aplikovano']) ? 'true' : 'false' ?>"
                                            title="Změnit označení aplikace práva"
                                        ><?= h((string)$right['id_pravo']) ?></button>
                                    <?php else: ?>
                                        <span class="admin_matrix_right_id<?= !empty($right['aplikovano']) ? ' is-applied' : '' ?>"><?= h((string)$right['id_pravo']) ?></span>
                                    <?php endif; ?>
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
                                    <?php if ($right['popis'] !== ''): ?>
                                        <span><?= h($right['popis']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($adminRoles as $role): ?>
                                    <?php
                                    $idRole = (int)$role['id_role'];
                                    $idPravo = (int)$right['id_pravo'];
                                    $checked = !empty($adminAllowed[$idRole][$idPravo]);
                                    ?>
                                    <td class="admin_matrix_check">
                                        <input
                                            type="checkbox"
                                            data-admin-pravo="1"
                                            data-id-role="<?= h((string)$idRole) ?>"
                                            data-id-pravo="<?= h((string)$idPravo) ?>"
                                            data-id-modul="<?= h((string)$module['id_modul']) ?>"
                                            <?= $checked ? 'checked' : '' ?>
                                            <?= $rightActive ? '' : 'disabled' ?>
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
