<?php
declare(strict_types=1);

/* Účel souboru: Uloží a jednorázově načte zprávu stránky Šablony. */

function cb_smeny_sablony_flash_ulozit(string $type, string $text): void
{
    $_SESSION['cb_smeny_sablony_flash'] = ['type' => $type, 'text' => $text];
}

/** @return array{type:string,text:string}|null */
function cb_smeny_sablony_flash_nacist(): ?array
{
    $flash = $_SESSION['cb_smeny_sablony_flash'] ?? null;
    unset($_SESSION['cb_smeny_sablony_flash']);
    return is_array($flash) ? $flash : null;
}

