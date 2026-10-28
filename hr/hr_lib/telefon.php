<?php
declare(strict_types=1);
/* Účel: Rozděluje a ověřuje hlavní HR telefon s měnitelnou mezinárodní předvolbou; česká čísla zachovává ve stávajícím úložišti. */

/** Jedno- a dvoumístné předvolby určují hranici; ostatní mezinárodní předvolby mají tři číslice. */
function hr_telefon_kratke_predvolby(): array
{
    // Rozdělení podle ITU E.164 (https://www.ituob.org/registers/e164-cc/).
    return ['1','7','20','27','30','31','32','33','34','36','39','40','41','43','44','45','46','47','48','49',
        '51','52','53','54','55','56','57','58','60','61','62','63','64','65','66','81','82','84','86','90','91','92','93','94','95','98'];
}

/** Připraví dva formulářové vstupy i z dříve uloženého kompaktního zahraničního čísla. */
function hr_telefon_rozdel(string $telefon): array
{
    $compact = preg_replace('/[\s().-]+/u', '', trim($telefon)) ?? '';
    if (str_starts_with($compact, '00')) $compact = '+'.substr($compact, 2);
    // Starší importy někdy uložily českou předvolbu bez úvodního plus.
    if (preg_match('/^420[0-9]{9}$/D', $compact)) $compact = '+'.$compact;
    if (!str_starts_with($compact, '+')) return ['predvolba'=>'+420', 'cislo'=>$telefon];
    $digits = substr($compact, 1);
    $length = 3;
    foreach (hr_telefon_kratke_predvolby() as $code) {
        if (str_starts_with($digits, $code)) { $length = strlen($code); break; }
    }
    return ['predvolba'=>'+'.substr($digits, 0, $length), 'cislo'=>substr($digits, $length)];
}

/** Ověří celé číslo před zápisem; zahraniční předvolbu nikdy nenahradí českou ani číslo nezkrátí. */
function hr_telefon_sestav(string $predvolba, string $cislo): string
{
    $cislo = trim($cislo);
    if ($cislo === '') return '';
    // Vložení celého čísla funguje i bez JS; předvolbu vezmeme z vložené hodnoty.
    if (str_starts_with($cislo, '+') || str_starts_with($cislo, '00')) {
        $parts = hr_telefon_rozdel($cislo);
        $predvolba = $parts['predvolba']; $cislo = $parts['cislo'];
    }
    $predvolba = trim($predvolba);
    if (str_starts_with($predvolba, '00')) $predvolba = '+'.substr($predvolba, 2);
    if (!str_starts_with($predvolba, '+')) $predvolba = '+'.$predvolba;
    if (!preg_match('/^\+[1-9][0-9]{0,2}$/D', $predvolba)) {
        throw new CbUserVisibleException('Telefonní předvolbu zadejte například +420 nebo +966.');
    }
    if (!preg_match('/^[0-9\s().-]+$/uD', $cislo)) {
        throw new CbUserVisibleException('Telefonní číslo může obsahovat pouze číslice, mezery, pomlčky a závorky.');
    }
    $digits = preg_replace('/\D+/', '', $cislo) ?? '';
    if ($predvolba === '+420') {
        if (strlen($digits) !== 9) throw new CbUserVisibleException('Český telefon musí mít 9 číslic bez předvolby.');
        return $digits;
    }
    if (strlen($digits) < 4 || strlen($predvolba.$digits) - 1 > 15) {
        throw new CbUserVisibleException('Zkontrolujte délku zahraničního telefonu; s předvolbou může mít nejvýše 15 číslic.');
    }
    return $predvolba.$digits;
}
