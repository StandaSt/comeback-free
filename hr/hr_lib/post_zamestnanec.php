<?php
declare(strict_types=1);

/**
 * Zpracuje POST formularu pro rucni zalozeni zamestnance.
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
            'type' => 'success',
            'text' => 'Zaměstnanec byl uložen.',
        ];
        header('Location: ' . cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson)));
        exit;
    } catch (Throwable $e) {
        $_SESSION['hr_flash'] = [
            'type' => 'error',
            'text' => $e->getMessage(),
        ];
        header('Location: ' . cb_root_url('index.php?m=hr&page=novy_zamestnanec'));
        exit;
    }
}
