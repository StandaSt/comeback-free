<?php
declare(strict_types=1);

/*
 * Dvoukrokove prvni naplneni lokalniho HR: kontrola zdroju a potvrzeny import.
 */

function cb_admin_hr_kompletni_import_handle(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    $action = (string)($_POST['cb_action'] ?? '');
    $previewActions = [
        'admin_hr_kompletni_preview_pripravit',
        'admin_hr_kompletni_preview_zamestnanci',
        'admin_hr_kompletni_preview_mzdy',
        'admin_hr_kompletni_preview_dokumenty',
    ];
    if (!in_array($action, ['admin_hr_kompletni_preview', 'admin_hr_kompletni_import', ...$previewActions], true)) {
        return;
    }

    $returnUrl = cb_root_url('index.php?m=administrace&page=spousteni_scriptu');
    $isPreviewStep = in_array($action, $previewActions, true);
    try {
        if (($GLOBALS['PROSTREDI'] ?? '') !== 'LOCAL') {
            throw new CbUserVisibleException('Kompletní naplnění HR je povoleno pouze v lokálním prostředí.');
        }

        require_once __DIR__ . '/../../common/scripts/hr_kompletni_import.php';
        if ($isPreviewStep) {
            cb_admin_hr_kompletni_preview_krok($action, db());
        } elseif ($action === 'admin_hr_kompletni_preview') {
            $preview = cb_hr_kompletni_import_preview(db());
            $_SESSION['cb_admin_script_result'] = [
                'script' => 'hr_kompletni_preview',
                'success' => true,
                'message' => 'Podklady jsou připravené. Zápis do databáze neproběhl.',
                'preview' => $preview,
            ];
        } else {
            if ((string)($_POST['admin_hr_kompletni_confirm'] ?? '') !== '1') {
                throw new CbUserVisibleException('Potvrďte kompletní odstranění HR dat a nový import.');
            }
            $result = cb_hr_kompletni_import_proved(db());
            $_SESSION['cb_admin_script_result'] = [
                'script' => 'hr_kompletni_import',
                'success' => true,
                'message' => (string)$result['message'],
                'result' => $result,
            ];
            cb_user_akce_zapis([
                'id_user_akce_typ' => 14,
                'modul' => 'administrace',
                'objekt' => 'hr_kompletni_import',
                'pole' => 'spusteni',
                'hodnota_new' => (string)$result['audit'],
                'vysledek' => 1,
                'zdroj' => 'administrace',
            ]);
        }
    } catch (Throwable $e) {
        $publicMessage = cb_admin_chyba_text($e, 'Kompletní naplnění HR');
        if ($isPreviewStep) {
            cb_admin_hr_kompletni_json(['ok' => false, 'chyba' => $publicMessage], cb_admin_chyba_status($e));
        }
        $_SESSION['cb_admin_script_result'] = [
            'script' => $action === 'admin_hr_kompletni_preview' ? 'hr_kompletni_preview' : 'hr_kompletni_import',
            'success' => false,
            'message' => $publicMessage,
        ];
        cb_admin_chyba_audit(static function () use ($e): void {
            cb_user_akce_zapis([
                'id_user_akce_typ' => 14,
                'modul' => 'administrace',
                'objekt' => 'hr_kompletni_import',
                'pole' => 'spusteni',
                'vysledek' => 0,
                'err_msg' => $e->getMessage(),
                'zdroj' => 'administrace',
                'detail' => ['chyba' => $e->getMessage()],
            ]);
        });
    }

    header('Location: ' . $returnUrl, true, 303);
    exit;
}

function cb_admin_hr_kompletni_preview_krok(string $action, mysqli $db): never
{
    set_time_limit(0);
    if ($action === 'admin_hr_kompletni_preview_pripravit') {
        cb_hr_kompletni_over_schema($db);
        cb_hr_kompletni_priprav_zdroj();
        $_SESSION['cb_admin_hr_preview_parts'] = [];
        cb_admin_hr_kompletni_json(['ok' => true]);
    }

    $source = cb_hr_kompletni_pripraveny_zdroj();
    if ($action === 'admin_hr_kompletni_preview_zamestnanci') {
        $employees = cb_hr_kompletni_nacti_zamestnance($source['zamestnanci']);
        $matched = cb_hr_kompletni_spocitej_shody($db, $employees);
        $text = count($employees) . ' podání, ' . $matched['people'] . ' bezpečně přiřazených zaměstnanců, '
            . $matched['rejected'] . ' nepřiřazených nebo rozporných podání.';
        $_SESSION['cb_admin_hr_preview_parts']['formular'] = $text;
        cb_admin_hr_kompletni_json(['ok' => true]);
    }

    if ($action === 'admin_hr_kompletni_preview_mzdy') {
        $payroll = cb_hr_kompletni_nahled_mezd($db, $source['mzdy']);
        $text = $payroll['rows'] . ' řádků v ' . $payroll['months'] . ' uzavřených měsících, '
            . $payroll['from'] . ' až ' . $payroll['to'] . '.';
        $_SESSION['cb_admin_hr_preview_parts']['mzdy'] = $text;
        cb_admin_hr_kompletni_json(['ok' => true]);
    }

    $documents = cb_hr_kompletni_nahled_dokumentu($source['root']);
    $_SESSION['cb_admin_hr_preview_parts']['dokumenty'] = $documents['files'] . ' souborů pro '
        . $documents['people'] . ' zaměstnanců, ' . $documents['metadata'] . ' souborů s připraveným vytěžením.';
    $preview = (array)($_SESSION['cb_admin_hr_preview_parts'] ?? []);
    foreach (['formular', 'mzdy', 'dokumenty'] as $requiredPart) {
        if (!isset($preview[$requiredPart])) {
            throw new RuntimeException('Kontrola podkladů nebyla dokončena ve správném pořadí. Spusťte ji znovu.');
        }
    }
    unset($_SESSION['cb_admin_hr_preview_parts']);
    $_SESSION['cb_admin_script_result'] = [
        'script' => 'hr_kompletni_preview',
        'success' => true,
        'message' => 'Podklady jsou připravené. Zápis do databáze neproběhl.',
        'preview' => $preview,
    ];
    cb_hr_kompletni_smaz_strom($source['runtime']);
    cb_admin_hr_kompletni_json(['ok' => true, 'hotovo' => true]);
}

function cb_admin_hr_kompletni_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
