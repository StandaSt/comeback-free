<?php
declare(strict_types=1);
/* Účel: Samostatný vstup otázky na umístění PC; chrání odpověď session a CRF a předá ji knihovně zařízení. */
require_once __DIR__ . '/pobockove_pc.php';

/** Ověřuje odpověď před HTML; běžnému vstupu bez SQL migrace ani interním požadavkům nepřekáží. */
function cb_pobockove_pc_vstup(): void
{
    $answer = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && (string)($_POST['cb_action'] ?? '') === 'pobockove_pc_potvrdit';
    if (!$answer && (empty($_SESSION['login_ok']) || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
        || cb_session_is_internal_request())) {
        return;
    }
    $cbPcChyba = '';
    $cbPcKandidat = null;
    try {
        if (empty($_SESSION['login_ok'])) {
            throw new CbUserVisibleException('Pro potvrzení počítače se přihlaste do IS.', 401);
        }
        $db = db();
        if (!cb_pobockove_pc_schema($db)) {
            if (!$answer) { return; }
            throw new RuntimeException('Není připravena tabulka pobockove_pc. Spusťte připravené SQL.');
        }
        $hash = cb_pobockove_pc_hash($db);
        if ($answer) {
            cb_crf_vyzaduj();
            if ($hash === '' || !in_array($_POST['odpoved'] ?? '', ['ano', 'ne'], true)) {
                throw new CbUserVisibleException('Potvrzení lze odeslat pouze z přihlášeného pobočkového PC.');
            }
            cb_pobockove_pc_potvrd($db, $hash, (int)($_POST['id_pob'] ?? 0), $_POST['odpoved'] === 'ano');
            header('Location: ' . cb_root_url(''), true, 303);
            exit;
        }
        if ($hash === '') { return; }
        $cbPcKandidat = cb_pobockove_pc_kandidat($db, $hash);
        if (!$cbPcKandidat) { return; }
    } catch (Throwable $error) {
        if (!$answer) {
            cb_chyba_oznam($error, ['module' => 'SYSTEM', 'action' => 'Rozpoznání pobočkového PC']);
            return;
        }
        http_response_code($error instanceof CbUserVisibleException
            ? (in_array($error->getCode(), [401, 403], true) ? $error->getCode() : 422) : 500);
        $cbPcChyba = cb_chyba_uzivatel($error, ['module' => 'SYSTEM', 'action' => 'Potvrzení pobočkového PC']);
    }
    header('Cache-Control: no-store');
    require __DIR__ . '/../modaly/modal_pobockove_pc.php';
    exit;
}
