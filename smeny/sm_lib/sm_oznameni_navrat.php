<?php
declare(strict_types=1);
/* Účel: Uchová odkaz na oznámení směn přes přihlášení a poté vrátí uživatele do jeho modálu. */

/** Zachytí pouze ID ještě před kontrolou expirace session, která může ihned přesměrovat na login. */
function cb_smeny_oznameni_zapamatovat(): int
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return 0;
    $id = (($_GET['m'] ?? '') === 'smeny' && ($_GET['page'] ?? '') === 'me_smeny')
        ? max(0, (int)($_GET['oznameni'] ?? 0)) : 0;
    if ($id > 0) $_SESSION['smeny_navrat_oznameni'] = $id;
    return $id;
}

/** Ukládáme jen číselné ID, nikdy libovolnou URL. Návrat provádíme až po kontrole přihlášení. */
function cb_smeny_oznameni_navrat(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
    $id = cb_smeny_oznameni_zapamatovat();
    // Úvodní animace důvěryhodného zařízení má vlastní přesměrování; počkáme na její dokončení.
    if (empty($_SESSION['login_ok']) || !empty($_SESSION['cb_duveryhodne_zarizeni_nacitam'])) return;
    $pending = (int)($_SESSION['smeny_navrat_oznameni'] ?? 0);
    unset($_SESSION['smeny_navrat_oznameni']);
    if ($pending > 0 && $id === 0) {
        header('Location: '.cb_root_url('index.php?m=smeny&page=me_smeny&oznameni='.$pending), true, 303);
        exit;
    }
}
