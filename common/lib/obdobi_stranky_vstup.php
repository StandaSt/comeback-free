<?php
declare(strict_types=1);
/* Účel: Přijme pouze autorizovanou změnu místního období nebo prodlevy a vrátí nový model panelu. */
require_once __DIR__ . '/obdobi_stranky.php';
require_once __DIR__ . '/uloz_akci.php';

/** Token, přihlášení a oprávnění chrání i přímé volání bez otevřené stránky. */
function cb_obdobi_stranky_vyrid_pozadavek(): void
{
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['login_ok'])) { http_response_code(401); echo json_encode(['ok'=>false,'err'=>'Přihlaste se znovu.']); return; }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !cb_crf_platny()) { http_response_code(403); echo json_encode(['ok'=>false,'err'=>'Neplatný bezpečnostní token. Obnovte stránku.']); return; }
    try {
        $input = json_decode((string)file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new CbUserVisibleException('Neplatný požadavek na změnu období.');
        $model = cb_obdobi_stranky_ulozit($input);
        echo json_encode(['ok'=>true,'period'=>$model], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        http_response_code(422); echo json_encode(['ok'=>false,'err'=>'Neplatný požadavek na změnu období.']);
    } catch (CbUserVisibleException $error) {
        http_response_code(422); echo json_encode(['ok'=>false,'err'=>$error->getMessage()], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $error) {
        cb_chyba_json_odesli($error, ['module'=>'provoz','action'=>'Změna místního období']);
    }
}
