<?php
declare(strict_types=1);
/* Trvale zapisuje vazbu opakovanych promptu a jednotliva kola historie Franty. */

/** Opakovane pouziti spoji jen se stejnym textem promptu jeho vlastnika. */
function cb_ai_historie_pripojit(int $idAudit, int $idZdroj, int $idUser, string $prompt): void
{
    if ($idZdroj <= 0) { return; }
    $stmt = db()->prepare('SELECT COALESCE(id_prompt_zdroj, id_ai_analytik_audit) AS zdroj, prompt
        FROM ai_analytik_audit WHERE id_ai_analytik_audit=? AND id_user=?');
    $stmt->bind_param('ii', $idZdroj, $idUser);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row) || trim((string)$row['prompt']) !== $prompt) { return; }
    $root = (int)$row['zdroj'];
    $stmt = db()->prepare('UPDATE ai_analytik_audit SET id_prompt_zdroj=?
        WHERE id_ai_analytik_audit=? AND id_user=? AND id_ai_analytik_audit<>?');
    $stmt->bind_param('iiii', $root, $idAudit, $idUser, $root);
    $stmt->execute();
    $stmt->close();
}

/** Zamknuti rodicovskeho auditu zajisti poradi i pri soubeznych pozadavcich. */
function cb_ai_historie_zacit(int $idAudit, int $idUser, string $typ, string $zadani, string $model, array $nastaveni): int
{
    $db = db();
    $json = json_encode($nastaveni, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT id_ai_analytik_audit FROM ai_analytik_audit
            WHERE id_ai_analytik_audit=? AND id_user=? FOR UPDATE');
        $stmt->bind_param('ii', $idAudit, $idUser);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_row();
        $stmt->close();
        if (!$found) { throw new RuntimeException('Audit pro historii není dostupný.'); }
        $stmt = $db->prepare('INSERT INTO ai_analytik_historie
            (id_ai_analytik_audit, poradi, typ_zadani, zadani, model, zadani_json)
            SELECT ?, COALESCE(MAX(poradi),0)+1, ?, ?, ?, ? FROM ai_analytik_historie
            WHERE id_ai_analytik_audit=?');
        $stmt->bind_param('issssi', $idAudit, $typ, $zadani, $model, $json, $idAudit);
        $stmt->execute();
        $id = (int)$db->insert_id;
        $stmt->close();
        $db->commit();
        return $id;
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}

/** Dokonci pouze bezici kolo; dalsi odpoved nikdy neprepise starsi snimek. */
function cb_ai_historie_dokoncit(int $id, string $stav, ?array $odpoved = null, ?string $chyba = null): void
{
    if ($id <= 0) { return; }
    $json = $odpoved === null ? null : json_encode($odpoved, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $stmt = db()->prepare("UPDATE ai_analytik_historie SET stav=?, dokonceno=NOW(3), odpoved_json=?, chyba=?
        WHERE id_ai_analytik_historie=? AND stav='bezi'");
    $stmt->bind_param('sssi', $stav, $json, $chyba, $id);
    $stmt->execute();
    $stmt->close();
}
