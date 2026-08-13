<?php
declare(strict_types=1);

function cb_admin_individualni_prava_html(array $data): string
{
    $user = $data['user'] ?? [];
    $modules = $data['modules'] ?? [];
    $global = $data['global'] ?? [];
    $exceptions = $data['exceptions'] ?? [];

    ob_start();
    ?>
    <div class="admin_individual_user">
        <strong><?= h(trim((string)($user['prijmeni'] ?? '') . ' ' . (string)($user['jmeno'] ?? ''))) ?></strong>
        <span>Role: <?= h((string)($user['role'] ?? '')) ?></span>
        <span>Slot: <?= h((string)($user['slot'] ?? '')) ?></span>
    </div>
    <div class="admin_individual_blocks">
        <?php foreach ($modules as $module): ?>
            <?php if (($module['rights'] ?? []) === []): ?>
                <?php continue; ?>
            <?php endif; ?>
            <div class="admin_matrix_wrap">
                <table class="admin_matrix admin_individual_matrix" style="width:auto; min-width:0;">
                    <colgroup>
                        <col style="width:200px;">
                        <col style="width:72px;">
                        <col style="width:80px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th style="white-space:nowrap;">Právo</th>
                            <th class="admin_matrix_role_head">Globální</th>
                            <th class="admin_matrix_role_head">Výjimka</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="admin_matrix_group">
                            <th colspan="3" style="white-space:nowrap;"><?= h((string)$module['modul']) ?></th>
                        </tr>
                        <?php foreach ($module['rights'] as $right): ?>
                            <?php
                            $idPravo = (int)$right['id_pravo'];
                            $globalValue = !empty($global[$idPravo]) ? 1 : 0;
                            $hasException = array_key_exists($idPravo, $exceptions);
                            $exceptionValue = $hasException ? (int)$exceptions[$idPravo] : null;
                            $exceptionClass = $exceptionValue === 1 ? 'is-plus' : ($exceptionValue === 0 ? 'is-minus' : '');
                            $exceptionMark = $exceptionValue === 1 ? '+' : ($exceptionValue === 0 ? '-' : '');
                            ?>
                            <tr>
                                <td style="white-space:nowrap;">
                                    <strong><?= h((string)$right['nazev']) ?></strong>
                                    <?php if ((string)$right['popis'] !== ''): ?>
                                        <span><?= h((string)$right['popis']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="admin_matrix_check">
                                    <input
                                        class="admin_individual_global"
                                        type="checkbox"
                                        disabled
                                        <?= $globalValue === 1 ? 'checked' : '' ?>
                                    >
                                </td>
                                <td class="admin_matrix_check">
                                    <label class="admin_exception_toggle <?= h($exceptionClass) ?>">
                                        <input
                                            type="checkbox"
                                            data-admin-vyjimka="1"
                                            data-id-user="<?= h((string)($user['id_user'] ?? 0)) ?>"
                                            data-id-pravo="<?= h((string)$idPravo) ?>"
                                            data-global="<?= h((string)$globalValue) ?>"
                                            <?= $hasException ? 'checked' : '' ?>
                                        >
                                        <span><?= h($exceptionMark) ?></span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return trim((string)ob_get_clean());
}
