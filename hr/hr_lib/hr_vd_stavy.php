<?php
/*
 * Ucel souboru: Definuje pevna ID stavu verejneho dotazniku v modulu HR.
 * ID jsou soucasti domennoveho kontraktu HR a nesmi se odvozovat z poradi
 * zaznamu v ciselniku.
 */
declare(strict_types=1);

// Stav 0 je technicky stav verejneho dotazniku pred potvrzenim uchazecem.
const HR_VD_STAV_NEPOTVRZENO = 0;
const HR_VD_STAV_NOVY = 3;
const HR_VD_STAV_POHOVOR_POZDEJI = 6;
const HR_VD_STAV_NELZE_SE_DOVOLAT = 9;
const HR_VD_STAV_POHOVOR_DOMLUVEN = 12;
const HR_VD_STAV_NASTOUPIL = 15;
const HR_VD_STAV_DOMLUVEN_NASTUP = 24;
const HR_VD_STAV_NASTUPNI_DOTAZNIK_VYPLNEN = 27;
const HR_VD_STAV_SMLUVA_ODESLANA = 30;
const HR_VD_STAV_SMLUVA_PODEPSANA = 33;
const HR_VD_STAV_VD_NEPOTVRZENO = 39;
