<?php
// includes/mod_parovani.php * Verze: V2 * Aktualizace: 06.03.2026
declare(strict_types=1);

/*
 * Kontrola spárovaného mobilu po přihlášení
 * - LOCAL: párování se nevynucuje
 * - ostatní prostředí: bez aktivního zařízení zobrazí modal_registrace.php
 *
 * Vstup z index.php:
 * - přihlášený uživatel v session
 */

if (empty($_SESSION['login_ok'])) {
    return;
}

$cbUser = $_SESSION['cb_user'] ?? null;
$idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

$maMobil = false;

$prostredi = (string)($GLOBALS['PROSTREDI'] ?? '');
if ($prostredi === 'LOCAL') {
    $maMobil = true;
} else {
    if ($idUser > 0) {
        $conn = db();

        $stmt = $conn->prepare('
            SELECT id
            FROM push_zarizeni
            WHERE id_user=? AND aktivni=1
            LIMIT 1
        ');

        if ($stmt) {
            $stmt->bind_param('i', $idUser);
            $stmt->execute();
            $stmt->store_result();
            $maMobil = ($stmt->num_rows > 0);
            $stmt->close();
        }
    }
}

if ($maMobil) {
    return;
}

echo '<div class="cb-login-fill"></div>';
require_once __DIR__ . '/../modaly/modal_registrace.php';
?>
</div>
</body>
</html>
<?php
exit;
