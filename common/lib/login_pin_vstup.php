<?php
declare(strict_types=1);
/* Omezeny prihlasovaci krok PIN a soubezne push potvrzeni; pocatecni PIN nikdy neotevre IS pred zmenou. */
require_once __DIR__ . '/login_pin.php';

/** Kazdy krok overuje znovu aktivitu osoby i nezmenene heslo a PIN z doby zalozeni vyzvy. */
function cb_pin_overena_osoba(mysqli $db): array
{
    if (!cb_pin_ceka()) throw new RuntimeException('Čas pro ověření vypršel. Přihlaste se znovu.', 409);
    $pending = $_SESSION['cb_pin_ceka'];
    $user = cb_prvni_vstup_user($db, (int)$pending['id_user']);
    $row = cb_pin_nastaveni($db, (int)$pending['id_user']);
    if ($user === null || (int)$user['aktivni'] !== 1 || $row === null || !cb_pin_nastaven($row)
        || !hash_equals((string)$pending['heslo_otisk'], hash('sha256', (string)($user['heslo_hash'] ?? '')))
        || !hash_equals((string)$pending['pin_otisk'], cb_pin_otisk($row))) {
        throw new RuntimeException('Přihlašovací údaje se změnily. Přihlaste se znovu.', 409);
    }
    return $user;
}

/** Overeni dovoluje bud pouze zmenu pocatecniho PINu, nebo standardni dokonceni prihlaseni. */
function cb_pin_po_overeni(mysqli $db, array $user, bool $zmenaNutna): void
{
    cb_pin_push_uklid($db);
    unset($_SESSION['cb_pin_ceka']['push_token']);
    if ($zmenaNutna) {
        $_SESSION['cb_pin_ceka']['overeno'] = true;
        return;
    }
    try {
        // Centralni vstup muze vratit false, pokud spravce mezitim nastavil novy otevreny PIN.
        cb_prvni_vstup_dokonci_login($db, $user);
    } catch (Throwable $error) {
        cb_session_invalidate_auth();
        throw $error;
    }
    if (!empty($_SESSION['login_ok'])) unset($_SESSION['cb_pin_ceka'], $_SESSION['cb_pin_zprava']);
}

/** Polling notifikace patri stejne vyzve, ale expirace push nerusi dostupnost PINu. */
function cb_pin_push_vstup(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        if (!cb_pin_ceka()) {
            unset($_SESSION['cb_pin_ceka']);
            echo json_encode(['ok'=>true, 'stav'=>'exp']);
            exit;
        }
        if (($_GET['cancel'] ?? '') === '1') {
            cb_pin_push_uklid(db());
            cb_session_forget_auth();
            echo json_encode(['ok'=>true, 'stav'=>'ne']);
            exit;
        }
        if (($_GET['check'] ?? '') !== '1') { http_response_code(400); echo '{"ok":false}'; exit; }
        $pending = $_SESSION['cb_pin_ceka'];
        $row = empty($pending['push_token']) ? null : cb_db_pin_push_stav(db(), (int)$pending['id_user'], (string)$pending['push_token']);
        $state = (string)($row['stav'] ?? 'exp');
        if ($state === 'ne') {
            cb_session_forget_auth();
            $_SESSION['cb_flash'] = 'Přihlášení bylo v telefonu zamítnuto.';
        } elseif ($row === null || empty($row['platny']) || $state === 'exp') {
            $state = 'exp';
            unset($_SESSION['cb_pin_ceka']['push_token']);
        } elseif ($state === 'ok') {
            $user = cb_pin_overena_osoba(db());
            $settings = cb_pin_nastaveni(db(), (int)$pending['id_user']);
            cb_pin_po_overeni(db(), $user, cb_pin_pocatecni($settings ?? []));
        } else {
            $state = 'ceka';
        }
        echo json_encode(['ok'=>true, 'stav'=>$state, 'cil'=>!empty($_SESSION['login_ok']) ? cb_login_target_url() : cb_login_url()], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $error) {
        if ($error instanceof RuntimeException && $error->getCode() === 409) cb_session_forget_auth();
        // Do JSON nepatri SQL, token, heslo ani PIN.
        http_response_code(503);
        echo json_encode(['ok'=>false, 'stav'=>'chyba']);
    }
    exit;
}

/** Vyridi formular a povinnou prvni zmenu pred HTML; zadne id_user neprijima z klienta. */
function cb_pin_vstup(): void
{
    $action = (string)($_POST['cb_action'] ?? '');
    $pinPost = in_array($action, ['login_pin', 'login_pin_novy', 'login_pin_zrusit'], true);
    if (!isset($_SESSION['cb_pin_ceka']) && !$pinPost) return;
    if (!empty($_SESSION['login_ok'])) { unset($_SESSION['cb_pin_ceka']); return; }
    if (isset($_GET['obnoveni_hesla']) || isset($_GET['zapomenute_heslo'])) {
        unset($_SESSION['cb_pin_ceka']);
        return;
    }
    if (!cb_pin_ceka()) {
        unset($_SESSION['cb_pin_ceka'], $_SESSION['cb_pin_zprava']);
        $_SESSION['cb_flash'] = 'Čas pro ověření vypršel. Přihlaste se znovu e-mailem a heslem.';
        header('Location: ' . cb_login_url(), true, 303);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $pinPost) {
        try {
            if (!cb_crf_platny()) throw new RuntimeException('Platnost formuláře vypršela. Zkuste to znovu.');
            if ($action === 'login_pin_zrusit') {
                cb_pin_push_uklid(db());
                cb_session_forget_auth();
            } else {
                $user = cb_pin_overena_osoba(db());
                $pending = $_SESSION['cb_pin_ceka'];
                $id = (int)$pending['id_user'];
                // Vyslovne zamitnuti v telefonu ukonci i moznost pouzit alternativni PIN teze vyzvy.
                if (!empty($pending['push_token'])) {
                    $push = cb_db_pin_push_stav(db(), $id, (string)$pending['push_token']);
                    if (($push['stav'] ?? '') === 'ne') throw new RuntimeException('Přihlášení bylo v telefonu zamítnuto.', 409);
                }
                if ($action === 'login_pin_novy') {
                    if (empty($pending['overeno'])) throw new RuntimeException('Nejprve ověřte přihlášení PINem nebo notifikací.');
                    $new = (string)($_POST['novy_pin'] ?? '');
                    if (!cb_pin_format($new) || !hash_equals($new, (string)($_POST['novy_pin_znovu'] ?? ''))) {
                        throw new RuntimeException('Zadejte dvakrát stejný nový čtyřmístný PIN.');
                    }
                    cb_pin_over(db(), $id, '', $new, (string)$pending['pin_otisk'], true);
                    cb_pin_po_overeni(db(), $user, false);
                } else {
                    $result = cb_pin_over(db(), $id, (string)($_POST['pin'] ?? ''), null, (string)$pending['pin_otisk']);
                    cb_pin_po_overeni(db(), $user, $result['zmena_nutna']);
                }
                header('Location: ' . (!empty($_SESSION['login_ok']) ? cb_login_target_url() : cb_login_url()), true, 303);
                exit;
            }
        } catch (mysqli_sql_exception $error) {
            $_SESSION['cb_pin_zprava'] = 'PIN se nepodařilo ověřit. Zkuste to později nebo kontaktujte správce IS.';
        } catch (RuntimeException $error) {
            if ($error->getCode() === 409) unset($_SESSION['cb_pin_ceka']);
            $_SESSION['cb_pin_zprava'] = $error->getMessage();
        } catch (Throwable $error) {
            $_SESSION['cb_pin_zprava'] = 'Přihlášení se nepodařilo dokončit. Zkuste to znovu.';
        }
        if (!isset($_SESSION['cb_pin_ceka']) && isset($_SESSION['cb_pin_zprava'])) {
            $_SESSION['cb_flash'] = $_SESSION['cb_pin_zprava'];
            unset($_SESSION['cb_pin_zprava']);
        }
        header('Location: ' . cb_login_url(), true, 303);
        exit;
    }
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header('Content-Type: text/html; charset=utf-8');
    $pinMessage = (string)($_SESSION['cb_pin_zprava'] ?? '');
    $pinPrvniZmena = !empty($_SESSION['cb_pin_ceka']['overeno']);
    $pinPush = !$pinPrvniZmena && !empty($_SESSION['cb_pin_ceka']['push_token']);
    unset($_SESSION['cb_pin_zprava']);
    require __DIR__ . '/../modaly/modal_login_pin.php';
    exit;
}
