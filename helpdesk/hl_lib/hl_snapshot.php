<?php
// Ucel souboru: Sestavuje HelpDesk snapshot uzivatele vcetne aktualnich HR slotu.
declare(strict_types=1);
// Udaje a zobrazovane jmeno osoby vcetne titulu sdili centralni cteci zdroj.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';

// MAIN se cte ze samostatne historie; povoleni pobocek zustava v hr_pracoviste.
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';

require_once __DIR__ . '/hl_prava.php';

function cb_helpdesk_json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($json)) {
        return $json;
    }

    return '{}';
}

/** Připraví osobní popisek a role pro nový snapshot; již uložené snapshoty nepřepisuje. */
/** Připraví osobní popisek a role pro nový snapshot; již uložené snapshoty nepřepisuje. */
function cb_helpdesk_snapshot_load_user(mysqli $conn, int $idUser): array
{
    $data = [
        'jmeno' => '',
        'role' => null,
        'role_nazev' => null,
    ];

    // Nazvy roli spojujeme do jednoho textu; SQL oddelovac musi zustat uvnitr jednoho PHP retezce.
    $stmt = $conn->prepare("
        SELECT
            ou.jmeno,
            ou.prijmeni,ou.druhe_jmeno,ou.titul_za,
            GROUP_CONCAT(DISTINCT cr.role ORDER BY ur.id_role SEPARATOR ', ') AS role_nazev
        FROM hr_person hp
        LEFT JOIN " . cb_osoby_zdroj_sql() . " ou ON ou.id_person = hp.id_person AND ou.platny = 1
        LEFT JOIN hr_pristupovy_profil ur ON ur.id_person = hp.id_person
        LEFT JOIN cis_role cr ON cr.id_role = ur.id_role
        WHERE hp.id_person = ?
        GROUP BY hp.id_person, ou.jmeno, ou.prijmeni,ou.druhe_jmeno,ou.titul_za
        LIMIT 1
    ");
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->bind_result($jmeno, $prijmeni, $druheJmeno, $titulZa, $roleNazev);
        if ($stmt->fetch()) {
            $celeJmeno = cb_osoba_jmeno(['jmeno'=>$jmeno,'prijmeni'=>$prijmeni,'druhe_jmeno'=>$druheJmeno,'titul_za'=>$titulZa]);
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
        SELECT up.id_pob, up.hlavni AS `main`, p.kod, p.nazev
        FROM ' . cb_hr_pracoviste_sql($conn) . ' up
        LEFT JOIN pobocka p ON p.id_pob = up.id_pob
        WHERE up.id_person = ? AND up.platny = 1 AND up.zruseno IS NULL AND (up.platnost_od IS NULL OR up.platnost_od<=CURDATE()) AND (up.platnost_do IS NULL OR up.platnost_do>=CURDATE())
        ORDER BY up.hlavni DESC, p.nazev ASC, up.id_pob ASC
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
    $slotTable = cb_hr_schema_table($conn, 'slot');
    $stmt = $conn->prepare('
        SELECT us.id_slot, cs.slot
        FROM ' . $slotTable . ' us
        LEFT JOIN cis_slot cs ON cs.id_slot = us.id_slot
        WHERE us.id_person = ? AND us.platny = 1
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

/** Zachová čtecí kontext jednotlivých stránek; historické snapshoty se nemění. */
function cb_helpdesk_snapshot_load_obdobi(): array
{
    $periods = $_SESSION['cb_obdobi_stranek'] ?? [];
    return is_array($periods) ? $periods : [];
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
