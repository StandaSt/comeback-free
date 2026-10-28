<?php
// Potvrzeni nahrazeni s nedelitelnym obdobim; Neukladat ponecha rozepsana pole bez zapisu.
declare(strict_types=1);
?>
<dialog class="kt_confirm_dialog" data-kt-replace-dialog aria-labelledby="kt_replace_text">
  <p id="kt_replace_text"><?= h($ktReplacePrefix) ?><span class="kt_period_nowrap"><?= h($ktReplacePeriod) ?></span>. Označit ji jako neplatnou a uložit tuto verzi?</p>
  <div class="kt_confirm_actions"><button type="button" class="kt_confirm_save" data-kt-replace-save>Uložit</button><button type="button" data-kt-replace-cancel autofocus>Neukládat</button></div>
</dialog>
