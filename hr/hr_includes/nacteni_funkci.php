<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/lib/mailer.php';
require_once __DIR__ . '/../../common/lib/prvni_vstup.php';
require_once __DIR__ . '/../../common/lib/user_spojeni.php';
require_once __DIR__ . '/../../common/lib/email_zmena.php';
require_once __DIR__ . '/../../common/lib/email_zmena_oznameni.php';
require_once __DIR__ . '/../../common/lib/firemni_pristup.php';
require_once __DIR__ . '/../../common/lib/uloz_akci.php';

/**
 * Nacita zakladni HR knihovny a databazove soubory pro stranky modulu.
 */

// Pomocna logika bez primeho SQL.
require_once __DIR__ . '/../hr_lib/uzivatel.php';
require_once __DIR__ . '/../hr_lib/hr_vd_stavy.php';
require_once __DIR__ . '/../hr_lib/post_nabor.php';
require_once __DIR__ . '/../hr_lib/hr_post_pozadavek_vytvorit.php';
require_once __DIR__ . '/../hr_lib/hr_post_pozadavek_zrusit.php';
require_once __DIR__ . '/../hr_lib/post_zamestnanec.php';
require_once __DIR__ . '/../hr_lib/hr_post_zamestnanec_uprava.php';
require_once __DIR__ . '/../hr_lib/hr_post_zamestnanec_overit.php';
require_once __DIR__ . '/../hr_lib/hr_post_pozice_pridat.php';
require_once __DIR__ . '/../hr_lib/hr_post_pozice_zmenit_stav.php';
require_once __DIR__ . '/../hr_lib/hr_post_pobocka_pridat.php';
require_once __DIR__ . '/../hr_lib/hr_post_pobocka_zmenit_stav.php';
require_once __DIR__ . '/../hr_lib/hr_post_zamestnanec_pozice_zmenit.php';
require_once __DIR__ . '/../hr_lib/hr_post_zamestnanec_pobocky_zmenit.php';
require_once __DIR__ . '/../hr_lib/formatovani.php';
require_once __DIR__ . '/../hr_lib/vd_formatovani.php';

// Databazove cteni a zapisy.
require_once __DIR__ . '/../hr_db/ciselniky.php';
require_once __DIR__ . '/../hr_db/vd_ciselniky.php';
require_once __DIR__ . '/../hr_db/vd_akce.php';
require_once __DIR__ . '/../hr_db/vd_expirace.php';
require_once __DIR__ . '/../hr_db/vd_detail.php';
require_once __DIR__ . '/../hr_db/dokumenty_uchazecu.php';
require_once __DIR__ . '/../hr_db/zamestnanci.php';
require_once __DIR__ . '/../hr_db/zamestnanec_ulozeni.php';
require_once __DIR__ . '/../hr_db/hr_zamestnanec_uprava.php';
require_once __DIR__ . '/../hr_db/prehled.php';
require_once __DIR__ . '/../hr_db/vd_prehled.php';
require_once __DIR__ . '/../hr_db/pozadavky.php';
require_once __DIR__ . '/../hr_db/hr_nastaveni_pozice.php';
require_once __DIR__ . '/../hr_db/hr_nastaveni_pobocky.php';
require_once __DIR__ . '/../hr_db/hr_zarazeni_historie.php';
require_once __DIR__ . '/../hr_db/hr_pracoviste_historie.php';
