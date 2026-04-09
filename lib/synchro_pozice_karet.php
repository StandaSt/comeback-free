<?php
// lib/synchro_pozice_karet.php
// Synchronizace pozic karet po změně na dashboardu
// Kódování: UTF-8 bez BOM

declare(strict_types=1);

require_once __DIR__ . '/../includes/dashboard.php'; // obsahuje layout logiku

/**
 * Synchronizuje pozice (col, line) VŠECH uživatelsky viditelných karet podle aktuální layout logiky.
 * @param int $idUser
 * @return void
 */
function synchronize_card_positions(int $idUser): void
{
    $conn = db();
    
    // 1. Zjisti počet sloupců dashboardu a karty uživatele plus nano režim
    $dashGridCols = 3;
    $nanoKde = 0;
    $nanoCardIds = [];
    
    // Počet sloupců
    $stmtCols = $conn->prepare('SELECT pocet_sl, nano_kde FROM user_set WHERE id_user = ? LIMIT 1');
    if ($stmtCols) {
        $stmtCols->bind_param('i', $idUser);
        $stmtCols->execute();
        $stmtCols->bind_result($pocetSl, $nanoKdeDb);
        if ($stmtCols->fetch()) {
            $effectivePocetSl = (int)$pocetSl;
            if ($effectivePocetSl === 5) {
                $dashGridCols = 5;
            } elseif ($effectivePocetSl === 4) {
                $dashGridCols = 4;
            }
        }
        $nanoKde = (int)$nanoKdeDb;
        $stmtCols->close();
    }

    // Karty v nano režimu
    $stmtNano = $conn->prepare('SELECT id_nano FROM user_nano WHERE id_user = ?');
    if ($stmtNano) {
        $stmtNano->bind_param('i', $idUser);
        $stmtNano->execute();
        $stmtNano->bind_result($nanoId);
        while ($stmtNano->fetch()) {
            $idNano = (int)$nanoId;
            if ($idNano > 0) {
                $nanoCardIds[$idNano] = true;
            }
        }
        $stmtNano->close();
    }

    // Všechny karty, které uživatel může mít (z user_card_set kvůli pořadí/pozicím)
    $userCards = [];
    $stmtCards = $conn->prepare('SELECT id_karta, col, line, poradi FROM user_card_set WHERE id_user = ?');
    if ($stmtCards) {
        $stmtCards->bind_param('i', $idUser);
        $stmtCards->execute();
        $stmtCards->bind_result($idKarta, $col, $line, $poradi);
        while ($stmtCards->fetch()) {
            $userCards[] = [
                'id_karta' => (int)$idKarta,
                'col' => $col === null ? null : (int)$col,
                'line' => $line === null ? null : (int)$line,
                'poradi' => (int)$poradi,
                'is_nano' => isset($nanoCardIds[$idKarta]),
            ];
        }
        $stmtCards->close();
    }
    if (!$userCards) return; // uživatel nemá karty

    // Rozřadit na nano a mini karty
    $kartyNano = [];
    $kartyMini = [];
    foreach ($userCards as $kartaRow) {
        if (!empty($kartaRow['is_nano'])) {
            $kartyNano[] = $kartaRow;
        } else {
            $kartyMini[] = $kartaRow;
        }
    }
    // --- Použít přesně stejnou logiku layoutu, jaká je v dashboardu ---
    $sortByFallback = function (array &$list): void {
        usort($list, function (array $a, array $b): int {
            $poradiA = (int)($a['poradi'] ?? 0);
            $poradiB = (int)($b['poradi'] ?? 0);
            $idA = (int)($a['id_karta'] ?? 0);
            $idB = (int)($b['id_karta'] ?? 0);
            if ($poradiA !== $poradiB) {
                return $poradiA <=> $poradiB;
            }
            return $idA <=> $idB;
        });
    };
    $applyUserSlots = function (array $list) use (&$sortByFallback, $dashGridCols): array {
        if (count($list) <= 1) {
            return $list;
        }
        $cols = ($dashGridCols > 0) ? $dashGridCols : 3;
        $lockedBySlot = [];
        $unlocked = [];
        foreach ($list as $card) {
            $col = ($card['col'] ?? null);
            $line = ($card['line'] ?? null);
            $hasLock = ($col !== null && $line !== null && (int)$col > 0 && (int)$line > 0 && (int)$col <= $cols);
            if ($hasLock) {
                $slot = (((int)$line - 1) * $cols) + (int)$col;
                if (!isset($lockedBySlot[$slot])) {
                    $lockedBySlot[$slot] = $card;
                    continue;
                }
            }
            $unlocked[] = $card;
        }
        if (!empty($unlocked)) {
            $sortByFallback($unlocked);
        }
        $result = [];
        $unlockIdx = 0;
        $total = count($list);
        for ($slot = 1; $slot <= $total; $slot++) {
            if (isset($lockedBySlot[$slot])) {
                $result[] = $lockedBySlot[$slot];
                continue;
            }
            if (isset($unlocked[$unlockIdx])) {
                $result[] = $unlocked[$unlockIdx];
                $unlockIdx++;
            }
        }
        while (isset($unlocked[$unlockIdx])) {
            $result[] = $unlocked[$unlockIdx];
            $unlockIdx++;
        }
        if (!empty($lockedBySlot)) {
            $lockedSlots = array_keys($lockedBySlot);
            sort($lockedSlots, SORT_NUMERIC);
            foreach ($lockedSlots as $slot) {
                $slotNum = (int)$slot;
                if ($slotNum <= $total) {
                    continue;
                }
                if (isset($lockedBySlot[$slotNum])) {
                    $result[] = $lockedBySlot[$slotNum];
                } elseif (isset($lockedBySlot[$slot])) {
                    $result[] = $lockedBySlot[$slot];
                }
            }
        }
        return $result;
    };

    $kartyNano = $applyUserSlots($kartyNano);
    $kartyMini = $applyUserSlots($kartyMini);

    // --- Sestav výsledné pozice (col, line) dle pořadí v gridu ---
    $toUpdate = [];
    $renderItems = [];
    if ($nanoKde === 1) {
        foreach (array_chunk($kartyNano, 9) as $nanoSkupina) {
            foreach ($nanoSkupina as $kartaNano) {
                $renderItems[] = [
                    'karta' => $kartaNano,
                    'is_nano' => true
                ];
            }
        }
    } else {
        foreach ($kartyNano as $kartaNano) {
            $renderItems[] = [
                'karta' => $kartaNano,
                'is_nano' => true
            ];
        }
    }
    if ($nanoKde === 0 && !empty($kartyNano) && !empty($kartyMini)) {
        // break, není karta
    }
    foreach ($kartyMini as $kartaMini) {
        $renderItems[] = [
            'karta' => $kartaMini,
            'is_nano' => false
        ];
    }

    // Přepočet col, line podle render pořadí
    $renderPosCounter = 0;
    foreach ($renderItems as $item) {
        $k = $item['karta'];
        $renderPosCounter++;
        $cols = $dashGridCols;
        $col = (($renderPosCounter - 1) % $cols) + 1;
        $line = (int)floor(($renderPosCounter - 1) / $cols) + 1;
        $idKarta = (int)$k['id_karta'];
        if (!$item['is_nano'] && $idKarta > 0) {
            $toUpdate[$idKarta] = [
                'col' => $col,
                'line' => $line
            ];
        }
        // nano kartám col/line defaultně neaktualizujeme (zůstává NULL)
    }
    if ($toUpdate) {
        foreach ($toUpdate as $idKarta => $pos) {
            $stmtUpd = $conn->prepare('UPDATE user_card_set SET col = ?, line = ? WHERE id_user = ? AND id_karta = ?');
            if ($stmtUpd) {
                $stmtUpd->bind_param('iiii', $pos['col'], $pos['line'], $idUser, $idKarta);
                $stmtUpd->execute();
                $stmtUpd->close();
            }
        }
    }
    // nano kartám nastavíme col/line NULL
    foreach ($nanoCardIds as $idNano => $_) {
        $stmtNanoUpd = $conn->prepare('UPDATE user_card_set SET col = NULL, line = NULL WHERE id_user = ? AND id_karta = ?');
        if ($stmtNanoUpd) {
            $stmtNanoUpd->bind_param('ii', $idUser, $idNano);
            $stmtNanoUpd->execute();
            $stmtNanoUpd->close();
        }
    }
}
