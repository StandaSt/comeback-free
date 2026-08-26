<?php
declare(strict_types=1);

/* Zpracuje pouze editaci zakladnich udaju jedne karty zamestnance. */

function hr_post_zamestnanec_uprava(mysqli $db, int $roleId, int $zadalUser): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        try {
            $zadalPerson = hr_current_person_id($db);
        } catch (RuntimeException $e) {
            if ($roleId !== 1) {
                throw $e;
            }
            $zadalPerson = 1;
        }
        hr_update_employee_basic_data($db, $idPerson, $_POST, $zadalPerson, $zadalUser);
        $_SESSION['hr_flash'] = ['type' => 'hr_success', 'text' => 'Karta zaměstnance byla uložena.'];
    } catch (Throwable $e) {
        $_SESSION['hr_flash'] = ['type' => 'hr_error', 'text' => $e->getMessage()];
        $_SESSION['hr_edit_input'] = $_POST;
    }

    header('Location: ' . cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson) . '&upravit=1'), true, 303);
    exit;
}

function hr_post_pracovni_pomer_uprava(mysqli $db, int $roleId, int $zadalUser): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        $zadalPerson = hr_current_person_id($db);
        $typ = (int)($_POST['id_pracovni_vztah_typ'] ?? 0); $nastupInput = str_replace(',', '.', trim((string)($_POST['datum_nastupu'] ?? '')));
        $uvazek = (float)($_POST['uvazek'] ?? 0); $hodin = (float)($_POST['hodin_tydne'] ?? 0);
        $nastupDate = DateTimeImmutable::createFromFormat('!d.m.Y', $nastupInput); $dateErrors = DateTimeImmutable::getLastErrors();
        if ($typ <= 0 || $nastupDate === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $nastupDate->format('d.m.Y') !== $nastupInput || $nastupDate > new DateTimeImmutable('today') || $uvazek < 0 || $hodin < 0 || $hodin > 100) { throw new RuntimeException('Vyplňte platný typ vztahu, datum nástupu ve formátu DD.MM.RRRR a 0–100 hodin týdně.'); }
        $nastup = $nastupDate->format('Y-m-d');
        $db->begin_transaction();
        $current = hr_fetch_employee_work_relation($db, $idPerson);
        if (!is_array($current)) { throw new RuntimeException('Aktuální pracovní poměr nebyl nalezen.'); }
        if ((int)$current['id_pracovni_vztah_typ'] !== $typ || (string)$current['datum_nastupu'] !== $nastup) {
            $old = (int)$current['id_pracovni_vztah']; $stmt = $db->prepare('UPDATE hr_pracovni_vztah SET platny=0, zruseno=NOW() WHERE id_pracovni_vztah=?'); $stmt->bind_param('i',$old); $stmt->execute(); $stmt->close();
            $stmt=$db->prepare('INSERT INTO hr_pracovni_vztah (id_person,id_pracovni_vztah_typ,datum_nastupu,id_person_zadal,vytvoreno,platny) VALUES (?,?,?,?,NOW(),1)'); $stmt->bind_param('iisi',$idPerson,$typ,$nastup,$zadalPerson); $stmt->execute(); $vztah=(int)$db->insert_id; $stmt->close();
        } else { $vztah=(int)$current['id_pracovni_vztah']; }
        if ((float)($current['uvazek'] ?? -1) !== $uvazek || (float)($current['hodin_tydne'] ?? -1) !== $hodin || $vztah !== (int)$current['id_pracovni_vztah']) {
            $stmt=$db->prepare('UPDATE hr_pracovni_uvazek SET platnost_do=DATE_SUB(CURDATE(),INTERVAL 1 DAY) WHERE id_pracovni_vztah=? AND platnost_do IS NULL'); $stmt->bind_param('i',$vztah); $stmt->execute(); $stmt->close();
            $stmt=$db->prepare('INSERT INTO hr_pracovni_uvazek (id_pracovni_vztah,uvazek,hodin_tydne,platnost_od,id_user_zadal,vytvoreno) VALUES (?,?,?,CURDATE(),?,NOW())'); $stmt->bind_param('iddi',$vztah,$uvazek,$hodin,$zadalUser); $stmt->execute(); $stmt->close();
        }
        $db->commit();
        cb_form_finish(cb_root_url('index.php?m=hr&page=zamestnanec&id='.rawurlencode((string)$idPerson).'&sekce=pracovni_pomer'), true, 'Pracovní poměr byl uložen.');
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        cb_form_finish(cb_root_url('index.php?m=hr&page=zamestnanec&id='.rawurlencode((string)$idPerson).'&sekce=pracovni_pomer'), false, $e->getMessage(), $_POST);
    }
}
