<?php
declare(strict_types=1);

function cb_nastaveni_uzivatele_vyrid_post(): void
{
    if (empty($_SESSION['login_ok']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    if (isset($_POST['cb_theme_delta'])) {
        cb_nastaveni_uzivatele_uloz_theme();
    }

    if (isset($_SERVER['HTTP_X_COMEBACK_SET_PRODLEVA'])) {
        cb_nastaveni_uzivatele_uloz_prodlevu();
    }
}

function cb_nastaveni_uzivatele_uloz_theme(): void
{
    $cbThemeAjax = isset($_SERVER['HTTP_X_COMEBACK_THEME']);
    $cbThemeLevel = max(0, min(6, (int)cb_user_setting('dark', 0)));
    $cbThemeSaved = false;
    $cbUser = $_SESSION['cb_user'] ?? null;
    $cbIdUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

    if ($cbIdUser > 0) {
        $cbThemeDelta = (int)$_POST['cb_theme_delta'];
        $cbThemeDelta = $cbThemeDelta < 0 ? -1 : ($cbThemeDelta > 0 ? 1 : 0);
        $cbThemeLevel = max(0, min(6, $cbThemeLevel + $cbThemeDelta));
        $cbThemeStmt = db()->prepare('UPDATE user_set SET dark = ? WHERE id_user = ?');
        if ($cbThemeStmt instanceof mysqli_stmt) {
            $cbThemeStmt->bind_param('ii', $cbThemeLevel, $cbIdUser);
            $cbThemeSaved = $cbThemeStmt->execute();
            $cbThemeStmt->close();
            if ($cbThemeSaved) {
                cb_store_user_settings(['dark' => $cbThemeLevel]);
            }
        }
    } elseif ($cbThemeAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($cbThemeAjax) {
        header('Content-Type: application/json; charset=utf-8');
        if (!$cbThemeSaved) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'err' => 'Ulozeni selhalo'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['ok' => true, 'dark' => $cbThemeLevel], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cbThemeModule = strtolower(trim((string)($_POST['cb_theme_module'] ?? 'provoz')));
    $cbThemeModule = cb_modul_normalizuj($cbThemeModule);
    $cbThemeReturn = trim((string)($_POST['cb_theme_return'] ?? ''));
    if ($cbThemeReturn === '' || str_starts_with($cbThemeReturn, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $cbThemeReturn) === 1) {
        $cbThemeReturn = cb_root_url('index.php?m=' . rawurlencode($cbThemeModule));
    }
    header('Location: ' . $cbThemeReturn);
    exit;
}

function cb_nastaveni_uzivatele_uloz_prodlevu(): void
{
    header('Content-Type: application/json; charset=utf-8');

    $cbProdlevaRaw = $_POST['prodleva'] ?? null;
    if ($cbProdlevaRaw === null) {
        $cbProdlevaInput = json_decode((string)file_get_contents('php://input'), true);
        if (is_array($cbProdlevaInput)) {
            $cbProdlevaRaw = $cbProdlevaInput['prodleva'] ?? null;
        }
    }

    $cbProdlevaSec = (int)$cbProdlevaRaw;
    if ($cbProdlevaSec < 1 || $cbProdlevaSec > 10) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Neplatna prodleva'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cbProdlevaMs = $cbProdlevaSec * 1000;
    $cbUser = $_SESSION['cb_user'] ?? null;
    $cbIdUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
    if ($cbIdUser <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cbProdlevaSaved = false;
    $cbProdlevaStmt = db()->prepare('UPDATE user_set SET prodleva = ? WHERE id_user = ?');
    if ($cbProdlevaStmt instanceof mysqli_stmt) {
        $cbProdlevaStmt->bind_param('ii', $cbProdlevaMs, $cbIdUser);
        $cbProdlevaSaved = $cbProdlevaStmt->execute();
        $cbProdlevaStmt->close();
    }

    if (!$cbProdlevaSaved) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Ulozeni selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    cb_store_user_settings(['prodleva' => $cbProdlevaMs]);
    echo json_encode(['ok' => true, 'prodleva' => $cbProdlevaMs, 'sec' => $cbProdlevaSec], JSON_UNESCAPED_UNICODE);
    exit;
}
