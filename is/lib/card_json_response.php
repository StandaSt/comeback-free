<?php
// lib/card_json_response.php * Verze: V2 * Aktualizace: 03.06.2026
declare(strict_types=1);

if (!function_exists('cb_emit_card_json_response')) {
    function cb_emit_card_json_response(int $cardId, bool $loadMax, string $reqLabel): void
    {
        global $file, $cbPageExists;

        header('Content-Type: application/json; charset=utf-8');

        $cbUser = $_SESSION['cb_user'] ?? null;
        $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
        if ($idUser <= 0 || empty($_SESSION['login_ok'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($cardId <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'err' => 'Neplatna karta'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!$cbPageExists) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'err' => 'Pozadovana karta neexistuje'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $prevGet = $_GET;
        $prevSingleCardId = $GLOBALS['cb_dashboard_single_card_id'] ?? null;

        $_GET['cb_card_id'] = (string)$cardId;
        if ($loadMax) {
            $_GET['cb_load_max'] = '1';
        } else {
            unset($_GET['cb_load_max']);
        }
        $GLOBALS['cb_dashboard_single_card_id'] = $cardId;

        $html = '';
        $renderError = null;
        ob_start();
        try {
            require $file;
            $html = trim((string)ob_get_clean());
        } catch (Throwable $e) {
            $html = '';
            $renderError = $e;
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        $_GET = $prevGet;
        if ($prevSingleCardId === null) {
            unset($GLOBALS['cb_dashboard_single_card_id']);
        } else {
            $GLOBALS['cb_dashboard_single_card_id'] = $prevSingleCardId;
        }

        if ($html === '') {
            http_response_code(500);
            $payload = [
                'ok' => false,
                'err' => 'Max karta se nepodarila vyrenderovat',
            ];
            if ($renderError instanceof Throwable) {
                try {
                    require_once __DIR__ . '/../../www/notifikace/notifikace_2fa.php';
                    cb_push_send_error_admin($renderError->getMessage(), $renderError->getFile(), $renderError->getLine(), 1);
                } catch (Throwable $pushError) {
                }

                $payload['err_detail'] = $renderError->getMessage();
                $payload['err_file'] = $renderError->getFile();
                $payload['err_line'] = $renderError->getLine();
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'cardId' => $cardId,
            'cardHtml' => $html,
            'loadMax' => $loadMax ? 1 : 0,
            'request' => $reqLabel,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('cb_dashboard_resolve_file')) {
    function cb_dashboard_resolve_file(string $soubor): ?string
    {
        $raw = trim(str_replace('\\', '/', $soubor));
        if ($raw === '') {
            return null;
        }

        $name = basename($raw);
        $name = preg_replace('~\.php$~i', '', $name) ?: '';
        if (!preg_match('~^[a-z0-9_]{2,80}$~', $name)) {
            return null;
        }

        $base = realpath(__DIR__ . '/..');
        if ($base === false) {
            return null;
        }

        $full = realpath($base . '/karty/' . $name . '.php');
        if ($full === false || !is_file($full)) {
            return null;
        }
        if (strpos($full, $base) !== 0) {
            return null;
        }

        return $full;
    }
}

if (!function_exists('cb_dashboard_card_source_path')) {
    function cb_dashboard_card_source_path(string $soubor): string
    {
        $raw = trim(str_replace('\\', '/', $soubor));
        $name = basename($raw);
        $name = preg_replace('~\.php$~i', '', $name) ?: '';
        $base = realpath(__DIR__ . '/..');

        if ($base === false) {
            return __DIR__ . '/../karty/' . $name . '.php';
        }

        return $base . '/karty/' . $name . '.php';
    }
}

if (!function_exists('cb_dashboard_render_card_error')) {
    function cb_dashboard_render_card_error(string $title, string $message, array $details = []): string
    {
        $escape = static function (string $value): string {
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $html = '<div class="odstup_vnitrni_0">';
        $html .= '<p class="card_text txt_cervena text_tucny odstup_vnejsi_0">' . $escape($title) . '</p>';
        $html .= '<p class="card_text txt_cervena odstup_vnejsi_0">' . $escape($message) . '</p>';

        foreach ($details as $label => $value) {
            $text = trim((string)$value);
            if ($text === '') {
                continue;
            }
            $html .= '<p class="card_text txt_seda odstup_vnejsi_0">' . $escape((string)$label . ': ' . $text) . '</p>';
        }

        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('cb_emit_card_max_json_response')) {
    function cb_emit_card_max_json_response(int $cardId, string $reqLabel): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $cbUser = $_SESSION['cb_user'] ?? null;
        $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
        $idRole = (is_array($cbUser) && isset($cbUser['id_role'])) ? (int)$cbUser['id_role'] : 0;

        if ($idUser <= 0 || empty($_SESSION['login_ok'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($cardId <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'err' => 'Neplatna karta'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        require_once __DIR__ . '/../includes/priprav_kartu_max.php';

        $conn = db();
        $stmt = $conn->prepare('
            SELECT id_karta, nazev, soubor, min_role, poradi, aktivni, subtitle_min, subtitle_max, refresh_op
            FROM karty
            WHERE id_karta = ?
              AND aktivni = 1
              AND min_role >= ?
            LIMIT 1
        ');
        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'err' => 'Nepodarilo se nacist kartu'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt->bind_param('ii', $cardId, $idRole);
        $stmt->execute();
        $stmt->bind_result($idKartaDb, $nazev, $soubor, $minRole, $poradi, $aktivni, $subtitleMin, $subtitleMax, $refreshOp);

        $karta = null;
        if ($stmt->fetch()) {
            $karta = [
                'id_karta' => (int)$idKartaDb,
                'nazev' => (string)$nazev,
                'soubor' => (string)$soubor,
                'min_role' => (int)$minRole,
                'poradi' => (int)$poradi,
                'aktivni' => (int)$aktivni,
                'subtitle_min' => (string)$subtitleMin,
                'subtitle_max' => (string)$subtitleMax,
                'refresh_op' => (int)$refreshOp,
            ];
        }
        $stmt->close();

        if (!is_array($karta)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'err' => 'Na kartu neni pravo nebo neexistuje'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $maxHtml = trim(cb_priprav_kartu_max($karta));
        if ($maxHtml === '') {
            http_response_code(500);
            echo json_encode(['ok' => false, 'err' => 'Max karta se nepodarila vyrenderovat'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'cardId' => $cardId,
            'maxHtml' => $maxHtml,
            'request' => $reqLabel,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
