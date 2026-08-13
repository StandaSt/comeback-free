<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin_db/admin_prava_roli_db.php';

$adminPravaData = cb_admin_prava_roli_data();
$adminRoles = $adminPravaData['roles'];
$adminModules = $adminPravaData['modules'];
$adminRights = $adminPravaData['rights'];
$adminAllowed = $adminPravaData['allowed'];
$adminCurrentUser = $_SESSION['cb_user'] ?? [];
$adminShowBlockChecks = is_array($adminCurrentUser) && (int)($adminCurrentUser['id_role'] ?? 0) === 1;
?>
<?php if ($adminRights === []): ?>
    <div class="admin_empty blok">
        <h2 class="blok_title">Práva rolí</h2>
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
                        <col style="width:200px;">
                        <?php foreach ($adminRoles as $role): ?>
                            <col style="width:80px;">
                        <?php endforeach; ?>
                    </colgroup>
                    <thead>
                        <tr>
                            <th style="white-space:nowrap;">Právo</th>
                            <?php foreach ($adminRoles as $role): ?>
                                <th class="admin_matrix_role_head"><?= h($role['role']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="admin_matrix_group">
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
                            <tr>
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
<?php endif; ?>
