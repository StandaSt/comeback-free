<?php
/* Co-Link: spolecny vstup pouze nacte konfiguraci a jednoucelove knihovny; sam zadne dotazy neprovadi. */
declare(strict_types=1);
// Konfigurace zustava v globalnim rozsahu a ve stejnem poradi jako v puvodni knihovne.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/co_sql.php';
require_once __DIR__ . '/co_rows.php';
require_once __DIR__ . '/co_transakce.php';
require_once __DIR__ . '/../co_db/co_instalaci_overit.php';
require_once __DIR__ . '/../co_db/co_osobu_nacist.php';
require_once __DIR__ . '/co_prava.php';
require_once __DIR__ . '/co_cookie.php';
require_once __DIR__ . '/co_identita.php';
require_once __DIR__ . '/co_csrf.php';
require_once __DIR__ . '/co_puvod.php';
require_once __DIR__ . '/co_pripoj.php';
require_once __DIR__ . '/co_uloziste.php';
require_once __DIR__ . '/../co_db/co_audit_vlozit.php';
