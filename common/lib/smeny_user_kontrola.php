<?php
declare(strict_types=1);

/*
 * Synchronizace uživatelů ze Směn byla zrušena.
 * Soubor zůstává jako bezpečný kompatibilní vstup pro původní externí cron:
 * nevolá API a nemění user, role, pobočky ani sloty.
 */

function cb_smeny_user_kontrola(bool $preserveSession = false): void
{
    // Záměrně bez akce. Autoritou uživatele je HR/IS.
}

if (!defined('CB_SMENY_USER_KONTROLA_AUTO_RUN') || CB_SMENY_USER_KONTROLA_AUTO_RUN !== false) {
    cb_smeny_user_kontrola();
}
