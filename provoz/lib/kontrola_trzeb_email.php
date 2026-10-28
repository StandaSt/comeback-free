<?php
// Připravuje neměnný náhled e-mailu a ověřuje, že od náhledu nedošlo ke změně podkladů nebo kontaktů.
declare(strict_types=1);
require_once __DIR__ . '/../db/db_kontrola_trzeb_email.php';

// Náhled a konečné odeslání vždy používají serverem načtená oprávnění, kontakty a archiv.
function cb_kt_email_stav(mysqli $db, array $input): array
{
    if (!cb_kt_ma_pravo(CB_KT_ULOZIT)) throw new CbUserVisibleException('Nemáte právo odeslat výsledek kontroly.');
    // Všechny SELECTy čtou jeden konzistentní snímek, i když mezitím jiný uživatel ukládá kontrolu.
    $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    try {
        $allowed=cb_kt_pobocky($db);
        $filters=cb_kt_filtry($input,$allowed);
        $data=cb_kt_email_podklady($db,$allowed,$filters['from'],$filters['to']);
        if ($data===null) throw new CbUserVisibleException('Za zvolené období nejsou kompletní platné kontroly všech poboček s reportem, nebo k nim nemáte přístup.');
        return ['data'=>$data,'user_id'=>(int)$_SESSION['cb_user']['id_user'],
            'sender'=>cb_kt_email_kontakt($db,(int)$_SESSION['cb_user']['id_user'],true),
            'recipient'=>cb_kt_email_kontakt($db,CB_KT_EMAIL_PRIJEMCE_PERSON)];
    } finally { $db->rollback(); }
}

// Otisk zahrnuje celé tabulky, seznam poboček a oba kontakty, nikoli jen částky souhrnu.
function cb_kt_email_otisk(array $state): string
{
    return hash('sha256',json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}

// Jedno tělo je zobrazeno uživateli i předáno maileru; údaj o čase pochází z vytvoření náhledu.
function cb_kt_email_obsah(array $data, string $generated): string
{
    ob_start();
    try { require __DIR__ . '/../includes/kontrola_trzeb_email_obsah.php'; return (string)ob_get_contents(); }
    finally { ob_end_clean(); }
}
