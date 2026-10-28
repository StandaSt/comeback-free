<?php
declare(strict_types=1);
/* Účel: Chrání historii právem 111 a přebírá stávající osobní a reportový rozsah bez rozšíření oprávnění. */
require_once __DIR__.'/../../hr/hr_db/hr_zamestnanci_pristup.php';
require_once __DIR__.'/../../provoz/lib/reporty_pristup.php';

/** Chybějící právo před nasazením číselníku znamená zákaz, nikoli náhradní povolení. */
function cb_historie_pravo(int $right): bool
{
    return isset($_SESSION['prava_stav'][$right]) && cb_pravo_ma($right);
}

/** Stejný rozsah platí pro seznam, volby osob i přímé otevření detailu události. */
function cb_historie_rozsah(mysqli $db): array
{
    if (!cb_historie_pravo(111)) throw new CbUserVisibleException('Nemáte právo zobrazit historii činnosti.');
    $user = (int)($_SESSION['cb_user']['id_user'] ?? 0);
    $hr = cb_historie_pravo(301) ? hr_zamestnanci_pobocka_sql($db, $user, 'hp') : '0=1';
    $report = '0=1';
    $branches = [];
    if (cb_historie_pravo(202)) {
        try {
            $scope = cb_reporty_pristup_nacti($db, $user);
            $report = cb_reporty_archiv_sql($scope);
            $branches = $scope['branches'];
        } catch (CbUserVisibleException $error) {
            // Neúplná HR identita neposkytne náhradní přístup k reportům.
        }
    }
    return ['hr'=>$hr, 'report'=>$report, 'branches'=>$branches, 'user'=>$user, 'mzdy'=>cb_historie_pravo(318),
        'firmy'=>cb_firemni_pristup_firmy($db,$user)];
}
