<?php
declare(strict_types=1);

/**
 * Nacita zakladni HR knihovny a databazove soubory pro stranky modulu.
 */

// Pomocna logika bez primeho SQL.
require_once __DIR__ . '/../hr_lib/uzivatel.php';
require_once __DIR__ . '/../hr_lib/post_nabor.php';
require_once __DIR__ . '/../hr_lib/post_pozadavky.php';
require_once __DIR__ . '/../hr_lib/post_zamestnanec.php';
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
require_once __DIR__ . '/../hr_db/prehled.php';
require_once __DIR__ . '/../hr_db/vd_prehled.php';
require_once __DIR__ . '/../hr_db/pozadavky.php';
