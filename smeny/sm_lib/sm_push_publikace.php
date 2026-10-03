<?php
declare(strict_types=1);
/* Účel: Po potvrzeném zveřejnění jednorázově odešle oznámení právě této verze. Cron nepoužívá. */

/** Síťové chyby nevracejí publikaci zpět; výsledek doručení je oddělený od souhlasu zaměstnance. */
function cb_smeny_push_publikace(mysqli $db, int $plan, int $version): int
{
    require_once __DIR__.'/../../common/notifikace/notifikace_2fa.php';
    require_once __DIR__.'/sm_push.php';
    $stmt = cb_smeny_planovani_sql($db,
        'SELECT o.*,hp.id_user FROM smeny_oznameni o JOIN hr_person hp ON hp.id_person=o.id_person WHERE o.id_smeny_rozpis=? AND o.verze=? AND o.push_pokusy=0 ORDER BY o.id_smeny_oznameni',
        'ii', [$plan, $version]);
    $notices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $failed = 0;
    foreach ($notices as $notice) {
        $id = (int)$notice['id_smeny_oznameni'];
        // Podmíněný zápis brání dvojímu odeslání při opakovaném zavolání stejné publikace.
        $claim = cb_smeny_planovani_sql($db,
            'UPDATE smeny_oznameni SET push_pokusy=1 WHERE id_smeny_oznameni=? AND push_pokusy=0', 'i', [$id]);
        $send = $claim->affected_rows === 1;
        $claim->close();
        if (!$send) continue;
        try {
            $state = cb_smeny_push_odeslat($notice);
        } catch (Throwable $e) {
            $state = 'ceka';
            error_log('Směny push #'.$id.': '.$e->getMessage());
        }
        if ($state === 'ceka') $failed++;
        // Stav ceka zde znamená nedoručeno. Žádný automatický další pokus se neplánuje.
        cb_smeny_planovani_sql($db,
            'UPDATE smeny_oznameni SET push_stav=?,push_dalsi_pokus=NULL WHERE id_smeny_oznameni=?',
            'si', [$state, $id])->close();
    }
    return $failed;
}
