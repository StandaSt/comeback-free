<?php
declare(strict_types=1);

/*
 * Ucel souboru: Zpracuje akci ulozeni noveho zamestnance do HR evidence.
 * Provadi validaci a zapis pres HR DB logiku; neresi vyber HTTP akce ani layout.
 */
function hr_post_zamestnanec(mysqli $db, int $idUser): void
{
    try {
        $employee = hr_insert_employee($db, $_POST, $idUser);
        $idPerson = (int)$employee['id_person'];
        $link = cb_url_abs('?prvni_vstup=' . rawurlencode((string)$employee['token']));
        $body = '<p>Dobrý den, ' . h((string)$employee['jmeno']) . ',</p><p>pro první vstup do IS Comeback nastavte heslo zde:</p><p><a href="' . h($link) . '">První vstup do IS Comeback</a></p><p>Odkaz platí 3 dny.</p>';
        cb_mail_send('hr', (string)$employee['email'], 'První vstup do IS Comeback', $body, 'První vstup do IS Comeback: ' . $link);
        cb_form_finish(
            cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson)),
            true,
            'Zaměstnanec byl uložen.'
        );
    } catch (Throwable $e) {
        cb_form_finish(
            cb_root_url('index.php?m=hr&page=novy_zamestnanec'),
            false,
            $e->getMessage(),
            $_POST
        );
    }
}
