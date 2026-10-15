<?php
// Opravneni a pobockovy rozsah kontroly trzeb a archivu; Vyroba do kontroly nepatri.
declare(strict_types=1);
require_once __DIR__ . '/reporty_pristup.php';

const CB_KT_ZOBRAZIT = 218;
const CB_KT_ULOZIT = 219;

// Pred instalaci SQL nezname pravo nic nezpristupni ani nezablokuje ostatni Provoz.
function cb_kt_ma_pravo(int $right = CB_KT_ZOBRAZIT): bool
{
    return array_key_exists($right, $_SESSION['prava_stav'] ?? []) && cb_pravo_ma($right);
}

// Tydenni souhrn je pristupny pouze za celou povolenou pobocku, nikoli pres autorstvi jednoho reportu.
function cb_kt_pobocky(mysqli $db): array
{
    if (empty($_SESSION['login_ok']) || !cb_kt_ma_pravo()) {
        throw new CbUserVisibleException('Nemáte právo zobrazit Kontrolu tržeb.');
    }
    $scope = cb_reporty_pristup_nacti($db, (int)($_SESSION['cb_user']['id_user'] ?? 0));
    // Vyroba ma vlastni druh reportu, proto ji vyloucime i z primeho pristupu a ukladani.
    unset($scope['branches'][7]);
    return $scope['branches'];
}

// Stejne overeni pouziva cteni, detail archivu i zapis bez duvery v odeslane ID.
function cb_kt_vyzaduj_pobocku(mysqli $db, int $branch): array
{
    $branches = cb_kt_pobocky($db);
    if (!isset($branches[$branch])) {
        throw new CbUserVisibleException('K vybrané pobočce nemáte přístup.');
    }
    return $branches[$branch];
}
