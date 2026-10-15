<?php
declare(strict_types=1);
/* Cte historii Franty a overuje vlastnictvi kazdeho pozadovaneho auditu. */

/** Kontrola patri i na detail; znalost ciziho ID sama o sobe neopravnuje ke cteni. */
function cb_ai_historie_audit(int $idAudit, int $idUser): array
{
    $stmt = db()->prepare('SELECT id_ai_analytik_audit, id_user, prompt, model, status,
        COALESCE(id_prompt_zdroj,id_ai_analytik_audit) AS zdroj,
        continuation_token_hash, continuation_expires_at, continuation_state_json,
        TIMESTAMPDIFF(SECOND,NOW(3),continuation_expires_at) AS zbyva
        FROM ai_analytik_audit WHERE id_ai_analytik_audit=?');
    $stmt->bind_param('i', $idAudit);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || ((int)$row['id_user'] !== $idUser && !cb_ai_analytik_uzivatel_je_admin(db(), $idUser))) {
        throw new CbAiAnalytikUzivatelskaChyba('Historie není dostupná nebo k ní nemáte oprávnění.');
    }
    return $row;
}

/** Strankuje pouziti jednoho promptu; samotne velke vysledky zatim nenacita. */
function cb_ai_historie_pouziti(int $idAudit, int $idUser, int $predId = 0): array
{
    $audit = cb_ai_historie_audit($idAudit, $idUser);
    $root = (int)$audit['zdroj'];
    $owner = (int)$audit['id_user'];
    $stmt = db()->prepare('SELECT a.id_ai_analytik_audit AS id, a.created_at, a.model, a.status,
        (SELECT COUNT(*) FROM ai_analytik_historie h WHERE h.id_ai_analytik_audit=a.id_ai_analytik_audit) AS kola
        FROM ai_analytik_audit a WHERE a.id_user=?
        AND (a.id_ai_analytik_audit=? OR a.id_prompt_zdroj=?)
        AND (?=0 OR a.id_ai_analytik_audit<?) ORDER BY a.id_ai_analytik_audit DESC LIMIT 21');
    $stmt->bind_param('iiiii', $owner, $root, $root, $predId, $predId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $more = count($rows) > 20;
    if ($more) { array_pop($rows); }
    return ['prompt' => (string)$audit['prompt'], 'uses' => $rows,
        'next' => $more ? (int)$rows[count($rows)-1]['id'] : 0];
}

/** Vratime vsechna kola jednoho pouziti vcetne chyb a dosud beziciho kola. */
function cb_ai_historie_kola(int $idAudit): array
{
    $stmt = db()->prepare('SELECT id_ai_analytik_historie AS id, poradi, vytvoreno, dokonceno,
        typ_zadani, zadani, model, stav, verze_formatu, zadani_json, odpoved_json, chyba
        FROM ai_analytik_historie WHERE id_ai_analytik_audit=? ORDER BY poradi');
    $stmt->bind_param('i', $idAudit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}
