<?php
declare(strict_types=1);
/* Kompaktni sprava ciselniku: pridani aktivni polozky a jedna davka zmen pro kazdy blok. */
if (!cb_hr_nastaveni_ma_pravo()) throw new CbUserVisibleException('Nemáte právo 319 pro správu slotů a funkcí.');
$roles = hr_ciselnik_role($db);
$isAdmin = hr_ciselnik_je_admin($db);
// Navrat zustava omezen na kartu zamestnance identifikovanou ciselne.
$returnPerson = max(0, (int)($_GET['navrat_person'] ?? 0));
$settingsUrl = cb_root_url('index.php?m=hr&page=nastaveni' . ($returnPerson > 0 ? '&navrat_person=' . $returnPerson : ''));
// Sdilena podoba editacniho radku slouzi i jako sablona pro prave pridane polozky.
$renderRow = static function (array $item, string $type) use ($roles, $isAdmin): void {
    $locked = $type === 'funkce' && (int)$item['id_role'] === 1 && !$isAdmin;
    ?>
    <div class="hr_catalog_row" data-hr-catalog-row data-id="<?= h((string)$item['id']) ?>">
        <input data-field="nazev" aria-label="Název" value="<?= h($item['nazev']) ?>" maxlength="<?= $type === 'slot' ? '100' : '80' ?>" required<?= $locked ? ' disabled' : '' ?>>
        <?php if ($type === 'funkce'): ?><select data-field="id_role" aria-label="Přístupová role"<?= $locked ? ' disabled' : '' ?>><?php if ($locked): ?><option value="1">Admin</option><?php else: ?><?php foreach ($roles as $role): ?><option value="<?= h((string)$role['id_role']) ?>"<?= (int)$role['id_role'] === (int)$item['id_role'] ? ' selected' : '' ?>><?= h($role['role']) ?></option><?php endforeach; ?><?php endif; ?></select><?php endif; ?>
        <input type="checkbox" data-field="aktivni" aria-label="Aktivní"<?= (int)$item['aktivni'] === 1 ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
    </div>
    <?php
};
?>
<?php if ($returnPerson > 0): ?><a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . $returnPerson . '&sekce=pracovni_pomer')) ?>">Zpět na pracovní poměr zaměstnance</a><?php endif; ?>
<div class="hr_settings_grid hr_settings_grid--catalog">
<?php foreach (['slot' => 'Pracovní sloty', 'funkce' => 'Funkce'] as $type => $title): ?>
<section class="hr_panel" id="hr-settings-<?= h($type) ?>" data-hr-catalog-block data-type="<?= h($type) ?>">
    <div class="hr_panel_header"><h2 class="hr_panel_title"><?= h($title) ?></h2></div>
    <form class="hr_catalog_new" data-hr-catalog-add method="post" action="<?= h($settingsUrl) ?>">
        <input type="hidden" name="cb_action" value="hr_ciselnik_ulozit"><input type="hidden" name="typ" value="<?= h($type) ?>"><input type="hidden" name="id" value="0"><input type="hidden" name="navrat_person" value="<?= h((string)$returnPerson) ?>">
        <input name="nazev" aria-label="Nový název" placeholder="Nový název" maxlength="<?= $type === 'slot' ? '100' : '80' ?>" required>
        <?php if ($type === 'funkce'): ?><select name="id_role" aria-label="Přístupová role nové funkce" required><?php foreach ($roles as $role): ?><option value="<?= h((string)$role['id_role']) ?>"<?= (int)$role['id_role'] === 9 ? ' selected' : '' ?>><?= h($role['role']) ?></option><?php endforeach; ?></select><?php endif; ?>
        <button class="hr_primary_button" type="submit">Přidat</button>
    </form>
    <form data-hr-catalog-edit method="post" action="<?= h($settingsUrl) ?>">
        <input type="hidden" name="cb_action" value="hr_ciselnik_ulozit"><input type="hidden" name="typ" value="<?= h($type) ?>"><input type="hidden" name="navrat_person" value="<?= h((string)$returnPerson) ?>">
        <div class="hr_catalog_head"><span>Název</span><?php if ($type === 'funkce'): ?><span>Přístupová role</span><?php endif; ?><span>Aktivní</span></div>
        <div data-hr-catalog-rows><?php foreach (hr_ciselnik_polozky($db, $type) as $item) $renderRow($item, $type); ?></div>
        <button type="submit" class="hr_primary_button hr_catalog_save" data-hr-catalog-save hidden><?= $type === 'slot' ? 'Uložit změny slotů' : 'Uložit změny funkcí' ?></button>
    </form>
    <p data-hr-catalog-status class="hr_catalog_status" role="status" hidden></p>
    <template data-hr-catalog-template><?php $renderRow(['id'=>0,'nazev'=>'','aktivni'=>1,'id_role'=>9], $type); ?></template>
</section>
<?php endforeach; ?>
</div>
