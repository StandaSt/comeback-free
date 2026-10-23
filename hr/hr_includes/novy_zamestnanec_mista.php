<?php
declare(strict_types=1);
/* Ucel: Spolecny vyber oblasti i pobocek a vice MAIN pro novou osobu; obsluhu sdili s kartou zamestnance. */
// Ciselniky dodava hr_novy_zamestnanec_data; PP blok nema vlastni databazove spojeni.
?>
<fieldset class="hr_place_choices" data-hr-place-options><legend>Místo výkonu práce</legend>
    <?php foreach ($newRegions as $region): ?><label><input type="checkbox" name="id_oblast_prace[]" value="<?= (int)$region['id_oblast_prace'] ?>"<?= in_array((string)$region['id_oblast_prace'],array_map('strval',(array)($formInput['id_oblast_prace']??[])),true)?' checked':'' ?>> <?= h($region['nazev']) ?></label><?php endforeach; ?>
    <?php foreach ($pobocky as $branch): ?><label><input type="checkbox" name="id_pob[]" value="<?= h($branch['id']) ?>"<?= isset($selectedBranches[(string)(int)$branch['id']])?' checked':'' ?>> <?= h($branch['label']) ?></label><?php endforeach; ?>
</fieldset>
<fieldset class="hr_place_choices" data-hr-main-choices><legend>MAIN pobočky</legend>
    <?php foreach ($pobocky as $branch): ?><label data-hr-main-choice><input type="checkbox" name="id_main[]" value="<?= h($branch['id']) ?>" data-hr-regions="<?= h(implode(',',$newBranchRegions[(int)$branch['id']]??[])) ?>"<?= in_array((string)$branch['id'],array_map('strval',(array)($formInput['id_main']??[])),true)?' checked':'' ?>> <?= h($branch['label']) ?></label><?php endforeach; ?>
</fieldset>
