<?php
declare(strict_types=1);
/* Účel: Předá aktuální stránku společnému sestavení a vykreslení menu; vlastní seznam položek je v datové definici modulu. */
require_once __DIR__.'/../../common/lib/menu_modulu.php';
cb_menu_modulu_vykreslit('smeny', $smPage);
