<?php
// lib/card_json_response.php * Verze: V1 * Aktualizace: 23.04.2026
declare(strict_types=1);

if (!function_exists('cb_emit_card_json_response')) {
    function cb_emit_card_json_response(int $cardId, bool $loadMax, string $reqLabel): void
    {
        global $file, $cbPageExists;

        header('Content-Type: application/json; charset=utf-8');

        $cbUser = $_SESSION['cb_user'] ?? null;
        $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
        if ($idUser <= 0) {
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
        ob_start();
        try {
            require $file;
            $html = trim((string)ob_get_clean());
        } catch (Throwable $e) {
            $html = '';
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
            echo json_encode([
                'ok' => false,
                'err' => 'Max karta se nepodarila vyrenderovat',
            ], JSON_UNESCAPED_UNICODE);
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
