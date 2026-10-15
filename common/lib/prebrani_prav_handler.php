<?php
declare(strict_types=1);

/* Obslouzi pouze dve explicitni POST akce Prebrani prav USER 1 pred smerovanim modulu.
 * Po ulozeni pokynu i po opusteni rezimu presmeruje na stavajici standardni odhlaseni.
 */
require_once __DIR__ . '/prebrani_prav.php';
require_once __DIR__ . '/form_post.php';

/** Nelze aktivovat pres GET, ciziho uzivatele ani uz probihajici test. */
function cb_prebrani_prav_handle(mysqli $db): void
{
    $action = (string)($_POST['cb_action'] ?? '');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !in_array($action, ['admin_prebrani_prav', 'admin_prebrani_prav_opustit'], true)) {
        return;
    }
    $return = cb_root_url('index.php?m=administrace&page=uzivatele');
    try {
        cb_crf_vyzaduj();
        if (empty($_SESSION['login_ok']) || (int)($_SESSION['cb_user']['id_user'] ?? 0) !== 1) {
            throw new CbUserVisibleException('Přebrání práv je dostupné pouze přihlášenému USER 1.');
        }
        if ($action === 'admin_prebrani_prav_opustit') {
            if (cb_prebrani_prav_profil() === null) {
                throw new CbUserVisibleException('Přebrání práv není aktivní.');
            }
        } else {
            if (cb_prebrani_prav_profil() !== null || !cb_user_ma_roli(1)) {
                throw new CbUserVisibleException('Nejprve se přihlaste se svými normálními adminskými právy.');
            }
            if (!cb_prebrani_prav_pripraveno($db)) {
                throw new CbUserVisibleException('Nejprve spusťte SQL pro tabulku admin_new_identita.');
            }
            $source = (int)($_POST['id_user_zdroj'] ?? 0);
            cb_prebrani_prav_nacti($db, $source);
            $db->begin_transaction();
            try {
                $db->query('DELETE FROM admin_new_identita');
                $stmt = $db->prepare('INSERT INTO admin_new_identita(id_user_zdroj,vytvoreno) VALUES (?,NOW())');
                $stmt->bind_param('i', $source);
                $stmt->execute();
                $stmt->close();
                $db->commit();
            } catch (Throwable $error) {
                $db->rollback();
                throw $error;
            }
        }
    } catch (Throwable $error) {
        cb_form_finish($return, false, $error instanceof CbUserVisibleException ? $error->getMessage()
            : 'Přebrání práv se nepodařilo připravit. Obnovte stránku a zkuste to znovu.');
    }
    // Ignorujeme formularovy cb_return: dalsi krok musi byt skutecny logout, ne navrat do IS.
    unset($_POST['cb_return']);
    cb_form_finish(cb_root_url('index.php?action=logout&duvod=1'), true, 'Následuje odhlášení.');
}
