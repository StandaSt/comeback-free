<?php
declare(strict_types=1);

/* Účel souboru: Odděluje zobrazení, editaci a publikování plánů směn. */

function cb_smeny_planovani_vidi(): bool { return function_exists('cb_pravo_ma') && cb_pravo_ma(403); }
function cb_smeny_planovani_edituje(): bool { return function_exists('cb_pravo_ma') && cb_pravo_ma(407); }
function cb_smeny_planovani_publikuje(): bool { return function_exists('cb_pravo_ma') && cb_pravo_ma(409); }

