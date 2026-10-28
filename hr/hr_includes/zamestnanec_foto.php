<?php
declare(strict_types=1);
/* Účel: Zobrazí portrét; vložení či výměnu čtvercového výřezu nabídne pouze v režimu úprav pod právem 307. */
$photoReturn = cb_root_url('index.php?' . http_build_query(array_filter([
    'm'=>'hr','page'=>'zamestnanec','id'=>$idPerson,'sekce'=>$employeeSection,'upravit'=>$isEdit ? '1' : null,
],static fn($value):bool=>$value!==null)));
?>
<div class="hr_employee_photo_edit">
    <?php if (!empty($employee['foto'])): ?>
        <img class="hr_employee_photo hr_employee_photo_large" src="<?= h(cb_root_url((string)$employee['foto'])) ?>" alt="Fotografie zaměstnance <?= h((string)$employee['cele_jmeno']) ?>">
    <?php else: ?>
        <div class="hr_employee_photo hr_employee_photo_large hr_employee_avatar_placeholder" role="img" aria-label="Fotografie zaměstnance zatím není vložena"></div>
    <?php endif; ?>
    <?php if ($isEdit && cb_pravo_ma(307)): ?>
        <form id="hr-employee-photo-form" class="hr_employee_photo_form hr_new_employee_photo_row" method="post" enctype="multipart/form-data" action="<?= h($photoReturn) ?>" data-hr-employee-form="photo" data-photo-square>
            <input type="hidden" name="cb_action" value="hr_zamestnanec_foto_ulozit">
            <input type="hidden" name="id_person" value="<?= (int)$idPerson ?>">
            <input type="hidden" name="sekce" value="<?= h($employeeSection) ?>">
            <input type="hidden" name="upravit" value="<?= $isEdit ? '1' : '0' ?>">
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-photo-input hidden>
            <button class="hr_employee_photo_add" type="button" data-photo-choose aria-label="<?= !empty($employee['foto']) ? 'Změnit fotografii zaměstnance' : 'Vložit fotografii zaměstnance' ?>" title="<?= !empty($employee['foto']) ? 'Změnit fotografii' : 'Vložit fotografii' ?>">+</button>
            <button type="button" data-photo-crop-open hidden>Vybrat výřez</button>
            <figure data-photo-preview hidden><img alt="Náhled vybrané fotografie"></figure>
            <dialog class="hr_photo_crop_dialog" data-photo-crop-dialog aria-label="Čtvercový výřez fotografie">
                <div class="hr_photo_crop_dialog_content">
                    <h3>Výřez fotografie</h3>
                    <p>Tažením vyberte čtverec. Uloží se pouze vybraná část fotografie.</p>
                    <div class="hr_photo_crop_surface" data-photo-crop-surface><img alt="Fotografie pro výřez" data-photo-crop-image draggable="false"><span class="hr_photo_crop_selection" data-photo-crop-selection></span></div>
                    <div class="hr_photo_crop_actions"><button class="hr_secondary_button" type="button" data-photo-crop-cancel>Zrušit</button><button class="hr_primary_button" type="button" data-photo-crop-apply>Uložit výřez</button></div>
                </div>
            </dialog>
        </form>
    <?php endif; ?>
</div>
