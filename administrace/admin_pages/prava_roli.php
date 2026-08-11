<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin_lib/prava_data.php';

$adminPravaData = cb_admin_prava_roli_data();
$adminRoles = $adminPravaData['roles'];
$adminModules = $adminPravaData['modules'];
$adminRights = $adminPravaData['rights'];
$adminAllowed = $adminPravaData['allowed'];
?>
<?php if ($adminRights === []): ?>
    <div class="admin_empty blok">
        <h2 class="blok_title">Práva rolí</h2>
        <p>V tabulce cis_prava zatím nejsou žádná práva.</p>
    </div>
<?php else: ?>
    <div class="admin_matrix_wrap">
        <table class="admin_matrix">
            <thead>
                <tr>
                    <th>Právo</th>
                    <?php foreach ($adminRoles as $role): ?>
                        <th><?= h($role['role']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adminModules as $module): ?>
                    <?php if ($module['rights'] === []): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <tr class="admin_matrix_group">
                        <th colspan="<?= h((string)(count($adminRoles) + 1)) ?>"><?= h($module['modul']) ?></th>
                    </tr>
                    <?php foreach ($module['rights'] as $right): ?>
                        <tr>
                            <td>
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
                                        <?= $checked ? 'checked' : '' ?>
                                    >
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
