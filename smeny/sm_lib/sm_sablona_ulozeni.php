<?php
declare(strict_types=1);

/* Účel souboru: Vytvoří šablonu V2 pro pobočku nebo změní její název. Firma plyne z pobočky. */

/** @return array<int,array{den_tydne:int,id_slot:int,cas_od:string,cas_do:string,poradi:int}> */
function cb_smeny_sablona_vychozi_bloky(array $branch): array
{
    $blocks = [];
    $positions = cb_smeny_sablony_pozice($branch);
    foreach (range(1, 7) as $day) {
        $closing = cb_smeny_sablony_zaviraci_cas($branch, $day);
        if ($closing === '') {
            throw new CbUserVisibleException('Pobočka nemá pro každý den nastavený zavírací čas. Šablonu nelze vytvořit.');
        }
        $closingValue = cb_smeny_sablony_cas_overit($closing, true, $closing);
        if ($closingValue <= cb_smeny_sablony_cas_overit('10:00', false, $closing)) {
            throw new CbUserVisibleException('Zavírací čas pobočky musí být později než 10:00. Šablonu nelze vytvořit.');
        }
        $order = 0;
        foreach ($positions as $idSlot => $positionName) {
            $order++;
            $blocks[] = [
                'den_tydne' => $day,
                'id_slot' => $idSlot,
                'cas_od' => '10:00',
                'cas_do' => $closing,
                'poradi' => $order,
            ];
        }
    }
    return $blocks;
}

/** Hlavičku a výchozí bloky ukládá společně, aby nikdy nevznikla neúplná šablona. */
function cb_smeny_sablona_ulozit(mysqli $db, array $branches): int
{
    $idTemplate = max(0, (int)($_POST['id_smeny_sablona'] ?? 0));
    $idBranch = (int)($_POST['id_pob'] ?? 0);
    $name = trim((string)($_POST['nazev'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) {
        throw new CbUserVisibleException('Zadejte název šablony dlouhý nejvýše 120 znaků.');
    }
    if (!isset($branches[$idBranch])) {
        throw new CbUserVisibleException('Vybraná pobočka není povolená.');
    }
    $idPerson = cb_smeny_sablony_id_person($db);
    if ($idPerson <= 0) {
        throw new CbUserVisibleException('Přihlášený účet není propojený s osobou v HR.');
    }

    $db->begin_transaction();
    try {
        if ($idTemplate <= 0) {
            $stmt = $db->prepare('INSERT INTO smeny_sablona (id_pob, nazev, vytvoril_id_person) VALUES (?, ?, ?)');
            $stmt->bind_param('isi', $idBranch, $name, $idPerson);
            $stmt->execute();
            $idTemplate = (int)$db->insert_id;
            $stmt->close();
            $defaultBlocks = cb_smeny_sablona_vychozi_bloky($branches[$idBranch]);
            $stmt = $db->prepare('INSERT INTO smeny_sablona_blok (id_smeny_sablona, den_tydne, id_slot, cas_od, cas_do, poradi) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($defaultBlocks as $block) {
                $day = $block['den_tydne'];
                $idSlot = $block['id_slot'];
                $from = $block['cas_od'];
                $to = $block['cas_do'];
                $order = $block['poradi'];
                $stmt->bind_param('iiissi', $idTemplate, $day, $idSlot, $from, $to, $order);
                $stmt->execute();
            }
            $stmt->close();
            cb_smeny_audit_zapis($db, $idPerson, 'vytvorena', 'sablona', $idTemplate, null, [
                'id_pob' => $idBranch,
                'nazev' => $name,
                'bloky' => $defaultBlocks,
            ]);
        } else {
            $template = cb_smeny_sablona_nacist($db, $idTemplate, $branches);
            if ($template === null || (int)$template['id_pob'] !== $idBranch) {
                throw new CbUserVisibleException('Šablona nebyla nalezena nebo nepatří do vybrané pobočky.');
            }
            $oldName = (string)$template['nazev'];
            $stmt = $db->prepare('UPDATE smeny_sablona SET nazev = ? WHERE id_smeny_sablona = ?');
            $stmt->bind_param('si', $name, $idTemplate);
            $stmt->execute();
            $stmt->close();
            cb_smeny_audit_zapis($db, $idPerson, 'prejmenovana', 'sablona', $idTemplate, ['nazev' => $oldName], ['nazev' => $name]);
        }
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
