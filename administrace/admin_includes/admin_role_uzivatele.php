<?php
declare(strict_types=1);
/* Účel: Uchovává jednu kompaktní tabulku aktivních členů pro každou roli; hlavičky sdílejí její šablonu. */
?>
<?php foreach ($adminRoles as $role): ?>
    <?php $roleId = (int)$role['id_role']; if (!in_array($roleId, [1,2,3,4,5,7], true)) continue; $rows = $adminRoleMembers[$roleId] ?? []; ?>
    <template id="admin-role-members-<?= $roleId ?>">
        <header class="admin_role_popup_header">
            <strong><?= h($role['role']) ?></strong>
            <button type="button" data-role-close aria-label="Zavřít přehled">×</button>
        </header>
        <div class="admin_role_popup_scroll">
            <?php if (!$rows): ?><p>Tuto roli nemá přiřazenou žádný aktivní uživatel.</p><?php else: ?>
            <table><thead><tr><th>Jméno</th><th>Funkce / sloty</th><th>Pobočky</th></tr></thead><tbody>
                <?php foreach ($rows as $person): ?>
                <tr>
                    <td><strong title="ID osoby <?= (int)$person['id_person'] ?>"><?= h(trim((string)$person['prijmeni'].' '.(string)$person['jmeno']) ?: 'Osoba #'.$person['id_person']) ?></strong>
                        <?php if (!(int)$person['aktivni']): ?><small>Neaktivní</small><?php endif; ?></td>
                    <td><?= h(implode(', ',array_keys($person['funkce'])) ?: '—') ?>
                        <?php if ($person['sloty']): ?><small>Sloty: <?= h(implode(', ',array_keys($person['sloty']))) ?></small><?php endif; ?></td>
                    <td><?php if ((int)$person['pristup_vsechny_pobocky']): ?><strong>Všechny pobočky firmy</strong><?php endif; ?>
                        <?php if ($person['main']): ?><small><strong>Hlavní: <?= h(implode(', ',array_keys($person['main']))) ?></strong></small><?php endif; ?>
                        <?php $other = array_diff_key($person['pobocky'],$person['main']); ?>
                        <?php if ($other): ?><span><?= h(implode(', ',array_keys($other))) ?></span><?php elseif (!$person['main'] && !(int)$person['pristup_vsechny_pobocky']): ?>—<?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
        </div>
        <footer>Kliknutím na název role přehled připnete. Zařazení platné dnes.</footer>
    </template>
<?php endforeach; ?>
