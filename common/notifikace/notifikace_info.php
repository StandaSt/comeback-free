<?php
declare(strict_types=1);
/* Uklada obecnou informaci s trvalym klicem; sdileny odesilac zajisti push i predani nedorucitelne zpravy adminovi. */
require_once __DIR__.'/notifikace_2fa.php';
require_once __DIR__.'/info_odesilac.php';

/** Klic brani opakovanemu ulozeni a odeslani stejne udalosti take pri soubeznych CRONech. */
function cb_info_posli_jednou(array $users, string $type, string $title, string $content, string $key, array $detail): array
{
    if ($key==='') throw new InvalidArgumentException('Chybí trvalý klíč notifikace.');
    return cb_info_odeslat($users,$type,$title,$content,
        ['prijemci'=>array_values(array_unique(array_map('intval',$users))),'detail'=>$detail],null,'','',$key);
}
