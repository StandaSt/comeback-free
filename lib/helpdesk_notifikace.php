<?php
// lib/helpdesk_notifikace.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

require_once __DIR__ . '/helpdesk_prava.php';

function cb_helpdesk_notifikace_pridat(mysqli $conn, int $idHelpdesk, ?int $idZprava, int $idUser, string $typ, string $text): void
{
    if ($idHelpdesk <= 0 || $idUser <= 0) {
        return;
    }

    $typ = trim($typ);
    $text = trim($text);
    if ($typ === '' || $text === '') {
        return;
    }

    if (mb_strlen($text, 'UTF-8') > 255) {
        $text = mb_substr($text, 0, 252, 'UTF-8') . '...';
    }

    $stmt = $conn->prepare('
        INSERT INTO helpdesk_notifikace
        (id_helpdesk, id_helpdesk_zprava, id_user, typ, text, vytvoreno)
        VALUES (?, ?, ?, ?, ?, NOW())
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        return;
    }

    $stmt->bind_param('iiiss', $idHelpdesk, $idZprava, $idUser, $typ, $text);
    $stmt->execute();
    $stmt->close();
}

function cb_helpdesk_notifikace_adminum(mysqli $conn, int $idHelpdesk, ?int $idZprava, int $idAutor, string $typ, string $text): void
{
    $admins = cb_helpdesk_admin_ids($conn);
    foreach ($admins as $idAdmin) {
        if ((int)$idAdmin === $idAutor) {
            continue;
        }
        cb_helpdesk_notifikace_pridat($conn, $idHelpdesk, $idZprava, (int)$idAdmin, $typ, $text);
    }
}

function cb_helpdesk_notifikace_ucastnikum(mysqli $conn, int $idHelpdesk, ?int $idZprava, int $idAutor, string $typ, string $text): void
{
    $users = [];

    $stmt = $conn->prepare('SELECT id_user_zalozil FROM helpdesk WHERE id_helpdesk = ? LIMIT 1');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $idHelpdesk);
        $stmt->execute();
        $stmt->bind_result($idZalozil);
        if ($stmt->fetch()) {
            $idZalozil = (int)$idZalozil;
            if ($idZalozil > 0) {
                $users[$idZalozil] = $idZalozil;
            }
        }
        $stmt->close();
    }

    $stmtSledujici = $conn->prepare('SELECT id_user FROM helpdesk_sledujici WHERE id_helpdesk = ?');
    if ($stmtSledujici instanceof mysqli_stmt) {
        $stmtSledujici->bind_param('i', $idHelpdesk);
        $stmtSledujici->execute();
        $res = $stmtSledujici->get_result();
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $idUser = (int)($row['id_user'] ?? 0);
                if ($idUser > 0) {
                    $users[$idUser] = $idUser;
                }
            }
            $res->free();
        }
        $stmtSledujici->close();
    }

    $stmtZpravy = $conn->prepare('SELECT DISTINCT id_user FROM helpdesk_zprava WHERE id_helpdesk = ?');
    if ($stmtZpravy instanceof mysqli_stmt) {
        $stmtZpravy->bind_param('i', $idHelpdesk);
        $stmtZpravy->execute();
        $res = $stmtZpravy->get_result();
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $idUser = (int)($row['id_user'] ?? 0);
                if ($idUser > 0) {
                    $users[$idUser] = $idUser;
                }
            }
            $res->free();
        }
        $stmtZpravy->close();
    }

    foreach ($users as $idUser) {
        if ((int)$idUser === $idAutor) {
            continue;
        }
        cb_helpdesk_notifikace_pridat($conn, $idHelpdesk, $idZprava, (int)$idUser, $typ, $text);
    }
}

// lib/helpdesk_notifikace.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
