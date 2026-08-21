<?php
declare(strict_types=1);

/*
 * Ucel souboru: Zpracuje akci ulozeni noveho zamestnance do HR evidence.
 * Provadi validaci a zapis pres HR DB logiku; neresi vyber HTTP akce ani layout.
 */
function hr_post_zamestnanec(mysqli $db, int $roleId): void
{
    try {
        try {
            $zadalPerson = hr_current_person_id($db);
        } catch (RuntimeException $e) {
            if ($roleId !== 1) {
                throw $e;
            }
            $zadalPerson = 1;
        }

        $idPerson = hr_insert_employee($db, $_POST, $zadalPerson);
        $_SESSION['hr_flash'] = [
            'type' => 'hr_success',
            'text' => 'Zaměstnanec byl uložen.',
        ];
        header('Location: ' . cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson)), true, 303);
        exit;
    } catch (Throwable $e) {
        $_SESSION['hr_flash'] = [
            'type' => 'hr_error',
            'text' => $e->getMessage(),
        ];
        header('Location: ' . cb_root_url('index.php?m=hr&page=novy_zamestnanec'), true, 303);
        exit;
    }
}
