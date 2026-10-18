<?php
declare(strict_types=1);
/* Účel: Načítá a atomicky ukládá i loguje uživatelský vzhled Směn; nezasahuje do rozpisů ani zámků. */
require_once __DIR__.'/sm_aktivita.php';

/** Neznámá nebo dosud nevyplněná hodnota vždy znamená známý klasický vzhled. */
function cb_smeny_styl_hodnota(mixed $value): int
{
    return $value === 2 || $value === '2' ? 2 : 1;
}

/** Třída je společná všem stránkám modulu, včetně obsahu načteného interní navigací. */
function cb_smeny_styl_trida(int $style): string
{
    return $style === 2 ? 'smeny_style--new' : 'smeny_style--classic';
}

/** Autoritou je user_set přihlášeného účtu; změna preference vyžaduje vlastní relaci a platné CRF. */
function cb_smeny_styl_nacist(mysqli $db): int
{
    $idUser = (int)($_SESSION['cb_user']['id_user'] ?? 0);
    if ($idUser <= 0) throw new CbUserVisibleException('Nejprve se přihlaste.');
    $save = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'smeny_styl_ulozit';
    if ($save) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $transaction=false;
        try {
            if (!cb_crf_platny()) throw new CbUserVisibleException('Platnost stránky vypršela. Obnovte ji.');
            $value = is_string($_POST['smeny_styl'] ?? null) ? $_POST['smeny_styl'] : '';
            if (!in_array($value, ['1','2'], true)) throw new CbUserVisibleException('Vyberte Klasický nebo Nový vzhled.');
            $style = (int)$value;
            $db->begin_transaction(); $transaction=true;
            $oldStmt=$db->prepare('SELECT smeny_styl FROM user_set WHERE id_user=? LIMIT 1 FOR UPDATE');
            $oldStmt->bind_param('i',$idUser); $oldStmt->execute();
            $oldStyle=cb_smeny_styl_hodnota($oldStmt->get_result()->fetch_assoc()['smeny_styl'] ?? 1); $oldStmt->close();
            $stmt = $db->prepare('UPDATE user_set SET smeny_styl=? WHERE id_user=?');
            $stmt->bind_param('ii',$style,$idUser);
            if (!$stmt->execute()) throw new RuntimeException('Uložení vzhledu Směn se nezdařilo.');
            $stmt->close();
            if(!cb_aktivita_server_zapis('smeny','vzhled_ulozen',cb_smeny_aktivita_kontext(),9,'user_set',$idUser,true,(string)$oldStyle,(string)$style)) throw new RuntimeException('Zápis historie činnosti se nezdařil.');
            $db->commit(); $transaction=false;
            echo json_encode(['ok'=>true,'style'=>$style]);
        } catch (Throwable $e) {
            if($transaction) $db->rollback();
            cb_smeny_aktivita_chyba($e);
            http_response_code($e instanceof CbUserVisibleException ? 400 : 500);
            echo json_encode(['ok'=>false,'message'=>cb_chyba_uzivatel($e,['module'=>'smeny','action'=>'Uložení vzhledu Směn','table'=>'user_set'])],JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    $stmt = $db->prepare('SELECT smeny_styl FROM user_set WHERE id_user=? LIMIT 1');
    $stmt->bind_param('i',$idUser); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return cb_smeny_styl_hodnota($row['smeny_styl'] ?? 1);
}
