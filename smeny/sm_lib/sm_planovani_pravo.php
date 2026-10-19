<?php
declare(strict_types=1);

/* Účel souboru: Odděluje zobrazení plánů (403) od jejich editace a zveřejnění (407). */

/** Přehled ostatních lidí vyžaduje čtecí oprávnění. */
function cb_smeny_planovani_vidi(): bool { return function_exists('cb_pravo_ma') && cb_pravo_ma(403); }
/** Právo 407 řídí změny a dokončení pracovního rozpisu. */
function cb_smeny_planovani_edituje(): bool { return function_exists('cb_pravo_ma') && cb_pravo_ma(407); }
/** Zveřejnění je dokončení plánování, nikoli další samostatné schválení. */
function cb_smeny_planovani_publikuje(): bool { return cb_smeny_planovani_edituje(); }

