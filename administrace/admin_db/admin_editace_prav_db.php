<?php
declare(strict_types=1);

/*
 * Účel souboru: Veškerá databázová práce stránky Editovat práva.
 * Soubor nic nevykresluje a nepřijímá HTTP požadavky.
 */

/** Načte aktivní moduly pro oba výběry na stránce. */
function cb_admin_editace_prav_moduly(): array
{
    $result = db()->query('
        SELECT id_modul, modul
        FROM cis_moduly
        WHERE aktivni = 1
        ORDER BY poradi, id_modul
    ');

    $modules = [];
    while ($row = $result->fetch_assoc()) {
        $modules[] = [
            'id_modul' => (int)$row['id_modul'],
            'modul' => (string)$row['modul'],
        ];
    }
    $result->free();

    return $modules;
}

/** Načte práva jednoho modulu v aktuálním pořadí. */
function cb_admin_editace_prav_prava_modulu(int $idModul): array
{
    if ($idModul <= 0) {
        throw new RuntimeException('Neplatné ID modulu.');
    }

    $stmt = db()->prepare('
        SELECT id_pravo, id_modul, nazev, popis, poradi, aktivni
        FROM cis_prava
        WHERE id_modul = ?
        ORDER BY poradi, id_pravo
    ');
    $stmt->bind_param('i', $idModul);
    $stmt->execute();
    $result = $stmt->get_result();

    $rights = [];
    while ($row = $result->fetch_assoc()) {
        $rights[] = [
            'id_pravo' => (int)$row['id_pravo'],
            'id_modul' => (int)$row['id_modul'],
            'nazev' => (string)$row['nazev'],
            'popis' => (string)($row['popis'] ?? ''),
            'poradi' => (int)$row['poradi'],
            'aktivni' => (int)$row['aktivni'] === 1,
        ];
    }
    $result->free();
    $stmt->close();

    return $rights;
}

/** Ověří a normalizuje textová pole práva. */
function cb_admin_editace_prav_texty(string $nazev, string $popis): array
{
    $nazev = trim($nazev);
    $popis = trim($popis);

    if ($nazev === '') {
        throw new RuntimeException('Název práva nesmí být prázdný.');
    }
    if (mb_strlen($nazev) > 100) {
        throw new RuntimeException('Název práva může mít nejvýše 100 znaků.');
    }
    if (mb_strlen($popis) > 255) {
        throw new RuntimeException('Popis práva může mít nejvýše 255 znaků.');
    }

    return ['nazev' => $nazev, 'popis' => $popis];
}

/**
 * Přidá právo na konec modulu.
 * Zámek řádku modulu zabrání souběžnému vytvoření stejného ID nebo pořadí.
 */
function cb_admin_editace_prav_pridat(int $idModul, string $nazev, string $popis): array
{
    if ($idModul <= 0) {
        throw new RuntimeException('Neplatné ID modulu.');
    }
    $texts = cb_admin_editace_prav_texty($nazev, $popis);
    $db = db();
    $db->begin_transaction();

    try {
        $stmtModule = $db->prepare('
            SELECT modul
            FROM cis_moduly
            WHERE id_modul = ? AND aktivni = 1
            FOR UPDATE
        ');
        $stmtModule->bind_param('i', $idModul);
        $stmtModule->execute();
        $module = $stmtModule->get_result()->fetch_assoc();
        $stmtModule->close();
        if (!is_array($module)) {
            throw new RuntimeException('Vybraný modul neexistuje nebo není aktivní.');
        }

        $stmtMax = $db->prepare('
            SELECT COALESCE(MAX(id_pravo), 0) AS max_id, COALESCE(MAX(poradi), 0) AS max_poradi
            FROM cis_prava
            WHERE id_modul = ?
        ');
        $stmtMax->bind_param('i', $idModul);
        $stmtMax->execute();
        $max = $stmtMax->get_result()->fetch_assoc();
        $stmtMax->close();

        $blockStart = $idModul * 100;
        $blockEnd = $blockStart + 99;
        $maxId = (int)($max['max_id'] ?? 0);
        $idPravo = $maxId > 0 ? $maxId + 1 : $blockStart;
        if ($idPravo < $blockStart || $idPravo > $blockEnd) {
            throw new RuntimeException('Pro modul již není volné ID v jeho číselném bloku.');
        }
        $poradi = (int)($max['max_poradi'] ?? 0) + 1;
        $dbNazev = (string)$texts['nazev'];
        $dbPopis = $texts['popis'] === '' ? null : $texts['popis'];

        $stmtInsert = $db->prepare('
            INSERT INTO cis_prava (id_pravo, id_modul, nazev, popis, poradi, aktivni)
            VALUES (?, ?, ?, ?, ?, 1)
        ');
        $stmtInsert->bind_param('iissi', $idPravo, $idModul, $dbNazev, $dbPopis, $poradi);
        $stmtInsert->execute();
        $stmtInsert->close();
        $db->commit();

        return [
            'id_pravo' => $idPravo,
            'id_modul' => $idModul,
            'modul' => (string)$module['modul'],
            'nazev' => $texts['nazev'],
            'popis' => $texts['popis'],
            'poradi' => $poradi,
            'aktivni' => true,
        ];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/** Uloží název a popis existujícího práva. */
function cb_admin_editace_prav_upravit(int $idPravo, string $nazev, string $popis): array
{
    if ($idPravo <= 0) {
        throw new RuntimeException('Neplatné ID práva.');
    }
    $texts = cb_admin_editace_prav_texty($nazev, $popis);
    $db = db();

    $stmtLoad = $db->prepare('
        SELECT id_modul, nazev, popis
        FROM cis_prava
        WHERE id_pravo = ?
        LIMIT 1
    ');
    $stmtLoad->bind_param('i', $idPravo);
    $stmtLoad->execute();
    $old = $stmtLoad->get_result()->fetch_assoc();
    $stmtLoad->close();
    if (!is_array($old)) {
        throw new RuntimeException('Právo ID ' . $idPravo . ' neexistuje.');
    }

    $dbNazev = (string)$texts['nazev'];
    $dbPopis = $texts['popis'] === '' ? null : $texts['popis'];
    $stmtUpdate = $db->prepare('UPDATE cis_prava SET nazev = ?, popis = ? WHERE id_pravo = ?');
    $stmtUpdate->bind_param('ssi', $dbNazev, $dbPopis, $idPravo);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    return [
        'id_pravo' => $idPravo,
        'id_modul' => (int)$old['id_modul'],
        'nazev' => $texts['nazev'],
        'popis' => $texts['popis'],
        'nazev_pred' => (string)$old['nazev'],
        'popis_pred' => (string)($old['popis'] ?? ''),
    ];
}

/** Prohodí hodnoty poradi práva a jeho souseda ve zvoleném směru. */
function cb_admin_editace_prav_posunout(int $idPravo, string $smer): array
{
    if ($idPravo <= 0 || !in_array($smer, ['nahoru', 'dolu'], true)) {
        throw new RuntimeException('Neplatný požadavek na změnu pořadí.');
    }

    $db = db();
    $db->begin_transaction();

    try {
        $stmtCurrent = $db->prepare('
            SELECT id_pravo, id_modul, nazev, poradi
            FROM cis_prava
            WHERE id_pravo = ?
            FOR UPDATE
        ');
        $stmtCurrent->bind_param('i', $idPravo);
        $stmtCurrent->execute();
        $current = $stmtCurrent->get_result()->fetch_assoc();
        $stmtCurrent->close();
        if (!is_array($current)) {
            throw new RuntimeException('Právo ID ' . $idPravo . ' neexistuje.');
        }

        $idModul = (int)$current['id_modul'];
        $poradi = (int)$current['poradi'];
        $operator = $smer === 'nahoru' ? '<' : '>';
        $order = $smer === 'nahoru' ? 'DESC' : 'ASC';
        $stmtNeighbour = $db->prepare("
            SELECT id_pravo, nazev, poradi
            FROM cis_prava
            WHERE id_modul = ? AND poradi {$operator} ?
            ORDER BY poradi {$order}, id_pravo {$order}
            LIMIT 1
            FOR UPDATE
        ");
        $stmtNeighbour->bind_param('ii', $idModul, $poradi);
        $stmtNeighbour->execute();
        $neighbour = $stmtNeighbour->get_result()->fetch_assoc();
        $stmtNeighbour->close();
        if (!is_array($neighbour)) {
            throw new RuntimeException($smer === 'nahoru' ? 'Právo už je první.' : 'Právo už je poslední.');
        }

        $idNeighbour = (int)$neighbour['id_pravo'];
        $poradiNeighbour = (int)$neighbour['poradi'];
        $stmtSwap = $db->prepare('
            UPDATE cis_prava
            SET poradi = CASE
                WHEN id_pravo = ? THEN ?
                WHEN id_pravo = ? THEN ?
            END
            WHERE id_pravo IN (?, ?)
        ');
        $stmtSwap->bind_param(
            'iiiiii',
            $idPravo,
            $poradiNeighbour,
            $idNeighbour,
            $poradi,
            $idPravo,
            $idNeighbour
        );
        $stmtSwap->execute();
        $stmtSwap->close();
        $db->commit();

        return [
            'id_pravo' => $idPravo,
            'id_modul' => $idModul,
            'nazev' => (string)$current['nazev'],
            'poradi_pred' => $poradi,
            'poradi_nove' => $poradiNeighbour,
            'id_soused' => $idNeighbour,
            'nazev_soused' => (string)$neighbour['nazev'],
        ];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
