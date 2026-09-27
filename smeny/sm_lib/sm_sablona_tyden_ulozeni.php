<?php
declare(strict_types=1);

/* Účel souboru: Uloží název a všechny sloty jednoho týdne šablony v jediné transakci. */

/** @return array<int,array<int,array<string,mixed>>> */
function cb_smeny_sablona_tyden_post_bloky(array $postedBlocks, array $branch): array
{
    $positions = cb_smeny_sablony_pozice($branch);
    $blocks = array_fill(1, 7, []);
    $usedIds = [];
    foreach ($postedBlocks as $dayValue => $postedDay) {
        $day = (int)$dayValue;
        if ($day < 1 || $day > 7 || !is_array($postedDay)) {
            throw new CbUserVisibleException('Odeslané sloty obsahují neplatný den týdne.');
        }
        if (count($postedDay) > 50) {
            throw new CbUserVisibleException('Jeden den může obsahovat nejvýše 50 slotů.');
        }
        $closing = cb_smeny_sablony_zaviraci_cas($branch, $day);
        if ($closing === '') {
            throw new CbUserVisibleException('Pobočka nemá pro tento den nastavený zavírací čas.');
        }
        $closingValue = cb_smeny_sablony_cas_minuty($closing, true);
        foreach ($postedDay as $postedBlock) {
            if (!is_array($postedBlock)) {
                throw new CbUserVisibleException('Odeslaný slot nemá správný formát.');
            }
            $idBlock = max(0, (int)($postedBlock['id'] ?? 0));
            $idSlot = (int)($postedBlock['id_slot'] ?? 0);
            $from = trim((string)($postedBlock['cas_od'] ?? ''));
            $to = trim((string)($postedBlock['cas_do'] ?? ''));
            if (!isset($positions[$idSlot])) {
                throw new CbUserVisibleException('Vybraná pozice není pro tuto pobočku povolená.');
            }
            if ($idBlock > 0 && isset($usedIds[$idBlock])) {
                throw new CbUserVisibleException('Stejný slot byl odeslán vícekrát. Obnovte stránku.');
            }
            $fromValue = cb_smeny_sablony_cas_overit($from, false, $closing);
            $toValue = cb_smeny_sablony_cas_overit($to, true, $closing);
            if ($toValue <= $fromValue) {
                throw new CbUserVisibleException('Konec slotu musí být později než jeho začátek.');
            }
            if ($toValue > $closingValue) {
                throw new CbUserVisibleException('Slot nemůže končit po zavírací době pobočky.');
            }
            if ($idBlock > 0) {
                $usedIds[$idBlock] = true;
            }
            $blocks[$day][] = [
                'id' => $idBlock,
                'id_slot' => $idSlot,
                'cas_od' => $from,
                'cas_do' => $to,
            ];
        }
    }
    return $blocks;
}

function cb_smeny_sablona_tyden_ulozit(mysqli $db, array $branches): int
{
    $idTemplate = (int)($_POST['id_smeny_sablona'] ?? 0);
    $idBranch = (int)($_POST['id_pob'] ?? 0);
    $name = trim((string)($_POST['nazev'] ?? ''));
    if ($idTemplate <= 0 || !isset($branches[$idBranch])) {
        throw new CbUserVisibleException('Šablona nebo pobočka nebyly nalezeny.');
    }
    if ($name === '' || mb_strlen($name) > 120) {
        throw new CbUserVisibleException('Zadejte název šablony dlouhý nejvýše 120 znaků.');
    }
    $template = cb_smeny_sablona_nacist($db, $idTemplate, $branches);
    if ($template === null || (int)$template['id_pob'] !== $idBranch) {
        throw new CbUserVisibleException('Šablona nebyla nalezena nebo nepatří do vybrané pobočky.');
    }
    $postedBlocks = $_POST['blocks'] ?? [];
    if (!is_array($postedBlocks)) {
        throw new CbUserVisibleException('Odeslané sloty nemají správný formát.');
    }
    $blocks = cb_smeny_sablona_tyden_post_bloky($postedBlocks, $branches[$idBranch]);
    $idPerson = cb_smeny_sablony_id_person($db);
    if ($idPerson <= 0) {
        throw new CbUserVisibleException('Přihlášený účet není propojený s osobou v HR.');
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT id_smeny_sablona FROM smeny_sablona WHERE id_smeny_sablona = ? AND id_pob = ? AND aktivni = 1 FOR UPDATE');
        $stmt->bind_param('ii', $idTemplate, $idBranch);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc() === null) {
            $stmt->close();
            throw new CbUserVisibleException('Šablonu mezitím změnil nebo odstranil jiný uživatel. Obnovte stránku.');
        }
        $stmt->close();

        $lockedTemplate = cb_smeny_sablona_nacist($db, $idTemplate, $branches);
        if ($lockedTemplate === null) {
            throw new CbUserVisibleException('Šablonu se nepodařilo znovu načíst. Obnovte stránku.');
        }
        $existing = [];
        foreach ($lockedTemplate['blocks'] as $dayBlocks) {
            foreach ($dayBlocks as $block) {
                $existing[(int)$block['id_smeny_sablona_blok']] = $block;
            }
        }
        $kept = [];
        $newAuditBlocks = [];

        $stmt = $db->prepare('UPDATE smeny_sablona SET nazev = ? WHERE id_smeny_sablona = ?');
        $stmt->bind_param('si', $name, $idTemplate);
        $stmt->execute();
        $stmt->close();

        $update = $db->prepare('UPDATE smeny_sablona_blok SET den_tydne = ?, id_slot = ?, cas_od = ?, cas_do = ?, poradi = ? WHERE id_smeny_sablona_blok = ? AND id_smeny_sablona = ?');
        $insert = $db->prepare('INSERT INTO smeny_sablona_blok (id_smeny_sablona, den_tydne, id_slot, cas_od, cas_do, poradi) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($blocks as $day => $dayBlocks) {
            $order = 0;
            foreach ($dayBlocks as $block) {
                $order++;
                $idBlock = (int)$block['id'];
                $idSlot = (int)$block['id_slot'];
                $from = (string)$block['cas_od'];
                $to = (string)$block['cas_do'];
                if ($idBlock > 0) {
                    if (!isset($existing[$idBlock])) {
                        throw new CbUserVisibleException('Jeden ze slotů už neexistuje. Obnovte stránku.');
                    }
                    $update->bind_param('iissiii', $day, $idSlot, $from, $to, $order, $idBlock, $idTemplate);
                    $update->execute();
                    $kept[$idBlock] = true;
                } else {
                    $insert->bind_param('iiissi', $idTemplate, $day, $idSlot, $from, $to, $order);
                    $insert->execute();
                    $idBlock = (int)$db->insert_id;
                    $kept[$idBlock] = true;
                }
                $newAuditBlocks[] = ['id' => $idBlock, 'den_tydne' => $day, 'id_slot' => $idSlot, 'cas_od' => $from, 'cas_do' => $to, 'poradi' => $order];
            }
        }
        $update->close();
        $insert->close();

        $delete = $db->prepare('DELETE FROM smeny_sablona_blok WHERE id_smeny_sablona_blok = ? AND id_smeny_sablona = ?');
        foreach ($existing as $idBlock => $oldBlock) {
            if (isset($kept[$idBlock])) {
                continue;
            }
            $delete->bind_param('ii', $idBlock, $idTemplate);
            $delete->execute();
        }
        $delete->close();

        cb_smeny_audit_zapis($db, $idPerson, 'ulozen_tyden', 'sablona', $idTemplate, [
            'nazev' => (string)$lockedTemplate['nazev'],
            'bloky' => $lockedTemplate['blocks'],
        ], [
            'nazev' => $name,
            'bloky' => $newAuditBlocks,
        ]);
        $db->commit();
    } catch (mysqli_sql_exception $e) {
        $db->rollback();
        if ((int)$e->getCode() === 1062) {
            throw new CbUserVisibleException('Na této pobočce už šablona se stejným názvem existuje.');
        }
        throw $e;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    return $idTemplate;
}
