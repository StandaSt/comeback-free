<?php
// helpdesk/hl_lib/hl_snapshot.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

require_once __DIR__ . '/hl_prava.php';

function cb_helpdesk_json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($json)) {
        return $json;
    }

    return '{}';
}

function cb_helpdesk_snapshot_load_user(mysqli $conn, int $idUser): array
{
    $data = [
        'jmeno' => '',
        'role' => null,
        'role_nazev' => null,
    ];

    $stmt = $conn->prepare('
        SELECT
            u.jmeno,
            u.prijmeni,
            GROUP_CONCAT(DISTINCT cr.role ORDER BY ur.id_role SEPARATOR ', ') AS role_nazev
        FROM `user` u
        LEFT JOIN user_role ur ON ur.id_user = u.id_user
        LEFT JOIN cis_role cr ON cr.id_role = ur.id_role
        WHERE u.id_user = ?
        GROUP BY u.id_user, u.jmeno, u.prijmeni
        LIMIT 1
    ');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->bind_result($jmeno, $prijmeni, $roleNazev);
        if ($stmt->fetch()) {
            $celeJmeno = trim((string)$jmeno . ' ' . (string)$prijmeni);
            $data['jmeno'] = $celeJmeno;
            $data['role'] = null;
            $data['role_nazev'] = (string)$roleNazev;
        }
        $stmt->close();
    }

    return $data;
}

function cb_helpdesk_snapshot_load_pobocky(mysqli $conn, int $idUser): array
{
    $out = [];
    $stmt = $conn->prepare('
        SELECT up.id_pob, up.`main`, p.kod, p.nazev
        FROM user_pobocka up
        LEFT JOIN pobocka p ON p.id_pob = up.id_pob
        WHERE up.id_user = ?
        ORDER BY up.`main` DESC, p.nazev ASC, up.id_pob ASC
    ');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $out[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }

    return $out;
}

function cb_helpdesk_snapshot_load_sloty(mysqli $conn, int $idUser): array
{
    $out = [];
    $stmt = $conn->prepare('
        SELECT us.id_slot, cs.slot
        FROM user_slot us
        LEFT JOIN cis_slot cs ON cs.id_slot = us.id_slot
        WHERE us.id_user = ?
        ORDER BY us.id_slot ASC
    ');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $out[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }

    return $out;
}

function cb_helpdesk_snapshot_load_obdobi(): array
{
    $out = [];
    if (array_key_exists('cb_obdobi_od', $_SESSION)) {
        $out['od'] = (string)$_SESSION['cb_obdobi_od'];
    }
    if (array_key_exists('cb_obdobi_do', $_SESSION)) {
        $out['do'] = (string)$_SESSION['cb_obdobi_do'];
    }
    if (array_key_exists('cb_obdobi_mode', $_SESSION)) {
        $out['mode'] = (string)$_SESSION['cb_obdobi_mode'];
    }

    return $out;
}

function cb_helpdesk_snapshot_zapis(mysqli $conn, int $idHelpdesk, int $idZprava, int $idUser): void
{
    if ($idHelpdesk <= 0 || $idZprava <= 0 || $idUser <= 0) {
        return;
    }

    if (cb_helpdesk_is_admin()) {
        return;
    }

    $user = cb_helpdesk_snapshot_load_user($conn, $idUser);
    $pobocky = cb_helpdesk_snapshot_load_pobocky($conn, $idUser);
    $sloty = cb_helpdesk_snapshot_load_sloty($conn, $idUser);
    $obdobi = cb_helpdesk_snapshot_load_obdobi();

    $jmeno = (string)$user['jmeno'];
    $email = '';
    $role = $user['role'];
    $roleNazev = $user['role_nazev'];
    $pobockyJson = cb_helpdesk_json($pobocky);
    $slotyJson = cb_helpdesk_json($sloty);
    $kartyJson = '[]';
    $obdobiJson = cb_helpdesk_json($obdobi);
    $sessionJson = '{}';
    $rawJson = '{}';
    $urlStranky = (string)($_SERVER['HTTP_REFERER'] ?? $_SERVER['REQUEST_URI'] ?? '');
    $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ipAdresa = (string)($_SERVER['REMOTE_ADDR'] ?? '');

    $stmt = $conn->prepare('
        INSERT INTO helpdesk_snapshot
        (id_helpdesk, id_helpdesk_zprava, id_user, jmeno, email, role, role_nazev,
         pobocky_json, sloty_json, karty_json, obdobi_json, session_json, raw_json,
         url_stranky, user_agent, ip_adresa, vytvoreno)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        return;
    }

    $stmt->bind_param(
        'iiississssssssss',
        $idHelpdesk,
        $idZprava,
        $idUser,
        $jmeno,
        $email,
        $role,
        $roleNazev,
        $pobockyJson,
        $slotyJson,
        $kartyJson,
        $obdobiJson,
        $sessionJson,
        $rawJson,
        $urlStranky,
        $userAgent,
        $ipAdresa
    );
    $stmt->execute();
    $stmt->close();
}

// helpdesk/hl_lib/hl_snapshot.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
