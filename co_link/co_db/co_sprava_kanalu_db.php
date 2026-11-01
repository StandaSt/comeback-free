<?php
/* Co-Link: cteni kanalu, clenu a pristupovych pravidel pro jejich spravu. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Zachova puvodni poradi kanalu vcetne archivovanych; opravneni filtruje sluzba. */
function co_sprava_kanalu_nacist(): array
{
    return co_rows('SELECT * FROM co_konverzace WHERE typ="kanal" ORDER BY archiv,nazev');
}

/** Vrati explicitni cleny spravovaneho kanalu v puvodnim poradi. */
function co_sprava_kanalu_cleny_nacist(int $id): array
{
    return co_rows('SELECT uzivatel FROM co_clen WHERE konverzace=?', [$id]);
}

/** Vrati pristup podle slotu a funkci; vyznam pravidel se nemenil. */
function co_sprava_kanalu_pravidla_nacist(int $id): array
{
    return co_rows('SELECT druh,hodnota FROM co_pristup WHERE konverzace=?', [$id]);
}
