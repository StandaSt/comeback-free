<?php
declare(strict_types=1);

function cb_nastaveni_uzivatele_vyrid_post(): void
{
    if (empty($_SESSION['login_ok']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    if (isset($_POST['cb_theme_delta'])) {
        try {
            cb_nastaveni_uzivatele_uloz_theme();
        } catch (Throwable $error) {
            $context = [
                'module' => 'SYSTEM',
                'action' => 'Uložení barevného motivu',
                'table' => 'user_set',
            ];
            if (isset($_SERVER['HTTP_X_COMEBACK_THEME'])) {
                cb_chyba_json_odesli($error, $context);
            }
            $_SESSION['cb_flash'] = cb_chyba_uzivatel($error, $context);
            header('Location: ' . cb_nastaveni_uzivatele_theme_navrat());
            exit;
        }
    }

    if (isset($_SERVER['HTTP_X_COMEBACK_SET_PRODLEVA'])) {
        try {
            cb_nastaveni_uzivatele_uloz_prodlevu();
        } catch (Throwable $error) {
            cb_chyba_json_odesli($error, [
                'module' => 'SYSTEM',
                'action' => 'Uložení prodlevy období',
                'table' => 'user_set',
            ]);
        }
    }

    if (isset($_SERVER['HTTP_X_COMEBACK_ACTIVE_MODULE'])) {
        try {
            cb_nastaveni_uzivatele_uloz_aktivni_modul();
        } catch (Throwable $error) {
            cb_chyba_json_odesli($error, [
                'module' => 'SYSTEM',
                'action' => 'Uložení aktivního modulu',
                'table' => 'user_set',
            ]);
        }
    }
}

function cb_nastaveni_uzivatele_theme_navrat(): string
{
    $module = cb_modul_normalizuj(strtolower(trim((string)($_POST['cb_theme_module'] ?? 'provoz'))));
    $returnUrl = trim((string)($_POST['cb_theme_return'] ?? ''));
    if ($returnUrl === '' || str_starts_with($returnUrl, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $returnUrl) === 1) {
        return cb_root_url('index.php?m=' . rawurlencode($module));
    }

    return $returnUrl;
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
        if (!($cbThemeStmt instanceof mysqli_stmt)) {
            throw new RuntimeException('Nepodařilo se připravit uložení barevného motivu.');
        }
        $cbThemeStmt->bind_param('ii', $cbThemeLevel, $cbIdUser);
        $cbThemeSaved = $cbThemeStmt->execute();
        $cbThemeStmt->close();
        if (!$cbThemeSaved) {
            throw new RuntimeException('Nepodařilo se uložit barevný motiv.');
        }
        cb_store_user_settings(['dark' => $cbThemeLevel]);
    } elseif ($cbThemeAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Platnost přihlášení vypršela. Přihlaste se prosím znovu.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($cbThemeAjax) {
        header('Content-Type: application/json; charset=utf-8');
        if (!$cbThemeSaved) {
            throw new RuntimeException('Barevný motiv nebyl uložen.');
        }
        echo json_encode(['ok' => true, 'dark' => $cbThemeLevel], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ' . cb_nastaveni_uzivatele_theme_navrat());
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
        echo json_encode(['ok' => false, 'err' => 'Prodleva musí být v rozsahu 1 až 10 sekund.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cbProdlevaMs = $cbProdlevaSec * 1000;
    $cbUser = $_SESSION['cb_user'] ?? null;
    $cbIdUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
    if ($cbIdUser <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Platnost přihlášení vypršela. Přihlaste se prosím znovu.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cbProdlevaStmt = db()->prepare('UPDATE user_set SET prodleva = ? WHERE id_user = ?');
    if (!($cbProdlevaStmt instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodařilo se připravit uložení prodlevy.');
    }
    $cbProdlevaStmt->bind_param('ii', $cbProdlevaMs, $cbIdUser);
    $cbProdlevaSaved = $cbProdlevaStmt->execute();
    $cbProdlevaStmt->close();
    if (!$cbProdlevaSaved) {
        throw new RuntimeException('Nepodařilo se uložit prodlevu.');
    }

    cb_store_user_settings(['prodleva' => $cbProdlevaMs]);
    echo json_encode(['ok' => true, 'prodleva' => $cbProdlevaMs, 'sec' => $cbProdlevaSec], JSON_UNESCAPED_UNICODE);
    exit;
}

function cb_nastaveni_uzivatele_uloz_aktivni_modul(): void
{
    header('Content-Type: application/json; charset=utf-8');

    $rawModule = $_POST['module'] ?? null;
    if ($rawModule === null) {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (is_array($input)) {
            $rawModule = $input['module'] ?? null;
        }
    }

    $module = strtolower(trim((string)$rawModule));
    if ($module === 'is') {
        $module = 'provoz';
    }
    if (!in_array($module, ['provoz', 'hr', 'smeny', 'ukoly', 'helpdesk', 'administrace'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Vybraný modul není platný.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cbUser = $_SESSION['cb_user'] ?? null;
    $cbIdUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
    if ($cbIdUser <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Platnost přihlášení vypršela. Přihlaste se prosím znovu.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = db()->prepare('UPDATE user_set SET aktivni_modul = ? WHERE id_user = ?');
    if (!($stmt instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodařilo se připravit uložení aktivního modulu.');
    }

    $stmt->bind_param('si', $module, $cbIdUser);
    $saved = $stmt->execute();
    $stmt->close();

    if (!$saved) {
        throw new RuntimeException('Nepodařilo se uložit aktivní modul.');
    }

    cb_store_user_settings(['aktivni_modul' => $module]);
    echo json_encode(['ok' => true, 'module' => $module], JSON_UNESCAPED_UNICODE);
    exit;
}
