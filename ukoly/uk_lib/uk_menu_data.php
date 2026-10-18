<?php
declare(strict_types=1);
/* Účel: Připraví menu Úkolů ze stávající mapy stránek bez čtení samotných úkolů. */
require_once __DIR__.'/uk_pages.php';

/** Jediný seznam položek zůstává v definici stránek Úkolů. */
function cb_ukoly_menu_data(): array
{
    return ['title'=>'Úkoly-požadavky','aria_label'=>'Menu úkolů','items'=>cb_ukoly_pages()];
}
