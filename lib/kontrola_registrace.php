<?php
// lib/kontrola_registrace.php * Verze: V2 * Aktualizace: 06.03.2026
declare(strict_types=1);

/*
 * Kontrola spárovaného mobilu po přihlášení
 * - LOCAL: párování se nevynucuje jen při vypnutém set_system.on_2fa
 * - ostatní prostředí: bez aktivního zařízení zobrazí modal_registrace.php
 *
 * Vstup z index.php:
 * - přihlášený uživatel v session
 */

if (empty($_SESSION['login_ok']) && empty($_SESSION['cb_auth_ok'])) {
    return;
}

$cbUser = $_SESSION['cb_user'] ?? null;
$idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

$maMobil = false;

$prostredi = (string)($GLOBALS['PROSTREDI'] ?? '');
$q2fa = db()->query('SELECT on_2fa FROM set_system WHERE id_set = 1 LIMIT 1');
$row2fa = $q2fa->fetch_assoc();
$on2fa = (int)$row2fa['on_2fa'];
$q2fa->free();

if ($prostredi === 'LOCAL' && $on2fa === 0) {
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
    if (empty($_SESSION['login_ok']) && !empty($_SESSION['cb_auth_ok'])) {
        $loginToken = (string)($_SESSION['cb_token'] ?? '');
        $_SESSION['login_ok'] = 1;
        unset($_SESSION['cb_auth_ok']);
        if ($loginToken !== '') {
            require_once __DIR__ . '/smeny_graphql.php';
            try {
                cb_login_finalize_after_ok($loginToken, 20);
            } catch (Throwable $e) {
                unset($_SESSION['login_ok']);
                $_SESSION['cb_auth_ok'] = 1;
                throw $e;
            }
        }
    }
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
