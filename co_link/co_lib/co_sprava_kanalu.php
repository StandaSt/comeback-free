<?php
/* Co-Link: prehled kanalu pro spravce vcetne clenu a pravidel, bez udeleni pristupu ke zpravam. */
declare(strict_types=1);
require_once __DIR__ . '/co_spravce_kanalu.php';
require_once __DIR__ . '/../co_db/co_sprava_kanalu_db.php';

/** Podrobnosti nacteme pouze u kanalu, ktere osoba smi spravovat. */
function co_kanaly_sprava(int $user): array
{
    $channels = co_sprava_kanalu_nacist();
    $out = ['kanaly' => []];
    foreach ($channels as $c) {
        if (!co_spravce_kanalu($user, $c)) continue;
        $c['lide'] = array_map('intval', array_column(co_sprava_kanalu_cleny_nacist((int)$c['id']), 'uzivatel'));
        $c['pravidla'] = co_sprava_kanalu_pravidla_nacist((int)$c['id']);
        $out['kanaly'][] = $c;
    }
    return $out;
}
