<?php
declare(strict_types=1);
/* Účel: Jediný veřejný zdroj směn pro zaměstnance a provoz. Publikace nahrazuje celý týden pobočky. */

/** Vrací SQL poddotaz bez parametrů; filtr období/osoby přidává volající. Pracovní verze se sem nedostane. */
function cb_smeny_verejny_zdroj_sql(): string
{
    return 'SELECT r.tyden_od start_day,DATE(DATE_SUB(s.zacatek,INTERVAL 6 HOUR)) datum,
        r.id_pob,hp.id_user,s.id_person,s.id_slot,TIME(s.zacatek) cas_od,TIME(s.konec) cas_do,
        s.zacatek,s.konec,2 zdroj,s.id_smeny_smena,r.id_smeny_rozpis,s.verze
        FROM smeny_rozpis r JOIN smeny_smena s ON s.id_smeny_rozpis=r.id_smeny_rozpis AND s.verze=r.zverejnena_verze
        JOIN hr_person hp ON hp.id_person=s.id_person
        UNION ALL
        SELECT e.start_day,e.datum,e.id_pob,e.id_user,hp.id_person,e.id_slot,e.cas_od,e.cas_do,
        TIMESTAMP(e.datum,e.cas_od),TIMESTAMP(DATE_ADD(e.datum,INTERVAL (e.cas_do<=e.cas_od) DAY),e.cas_do),
        1,NULL,NULL,NULL FROM smeny_plan e
        LEFT JOIN hr_person hp ON hp.id_person=(SELECT MAX(h.id_person) FROM hr_person h WHERE h.id_user=e.id_user)
        WHERE e.zdroj=1 AND NOT EXISTS(SELECT 1 FROM smeny_rozpis r WHERE r.id_pob=e.id_pob AND r.tyden_od=e.start_day AND r.zverejnena_verze IS NOT NULL)';
}
