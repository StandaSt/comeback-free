<?php
declare(strict_types=1);

/* Ruční spuštění stejné verzované synchronizace, kterou používá noční CRON. */

function cb_admin_restia_katalog_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cb_admin_restia_katalog_handle(): void
{
    $action = (string)($_POST['cb_action'] ?? '');
    $allowedActions = [
        'admin_restia_katalog',
        'admin_restia_katalog_pobocky',
        'admin_restia_katalog_pobocka',
    ];
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !in_array($action, $allowedActions, true)) {
        return;
    }

    $isAjax = $action !== 'admin_restia_katalog';
    $returnUrl = cb_root_url('index.php?m=administrace&page=spousteni_scriptu');
    try {
        if ((string)($_POST['admin_restia_katalog_confirm'] ?? '') !== '1') {
            throw new RuntimeException('Potvrďte načtení katalogu z Restie.');
        }
        require_once __DIR__ . '/../../provoz/lib/restia_katalog.php';
        $user = $_SESSION['cb_user'] ?? null;
        $idUser = is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
        $idLogin = (int)($_SESSION['cb_id_login'] ?? 0);

        if ($action === 'admin_restia_katalog_pobocky') {
            $branches = array_map(
                static fn(array $branch): array => [
                    'id_pob' => (int)$branch['id_pob'],
                    'nazev' => (string)$branch['nazev'],
                ],
                cb_restia_katalog_branches(db())
            );
            cb_admin_restia_katalog_json(['ok' => true, 'pobocky' => $branches]);
        }

        if ($action === 'admin_restia_katalog_pobocka') {
            $result = cb_restia_katalog_sync_one(
                (int)($_POST['id_pob'] ?? 0),
                $idUser > 0 ? $idUser : null,
                $idLogin > 0 ? $idLogin : null
            );
            $counts = cb_restia_katalog_counts([$result]);
            cb_user_akce_zapis([
                'id_user_akce_typ' => 14,
                'modul' => 'administrace',
                'objekt' => 'restia_katalog',
                'id_objektu' => (int)($_POST['id_pob'] ?? 0),
                'pole' => 'synchronizace_pobocky',
                'hodnota_new' => cb_restia_katalog_summary([$result]),
                'vysledek' => 1,
                'zdroj' => 'administrace',
            ]);
            cb_admin_restia_katalog_json([
                'ok' => true,
                'pobocka' => (string)$result['pobocka'],
                'stazeno' => (int)$counts['stazeno'],
                'aktualizovano' => (int)$counts['aktualizovano'],
            ]);
        }

        $results = cb_restia_katalog_sync_all($idUser > 0 ? $idUser : null, $idLogin > 0 ? $idLogin : null);
        $counts = cb_restia_katalog_counts($results);
        $message = 'Staženo: ' . (int)$counts['stazeno'] . ', aktualizováno: ' . (int)$counts['aktualizovano'];
        $success = !cb_restia_katalog_has_errors($results);
        $_SESSION['cb_admin_script_result'] = [
            'script' => 'restia_katalog',
            'success' => $success,
            'message' => $message,
        ];
        cb_user_akce_zapis([
            'id_user_akce_typ' => 14,
            'modul' => 'administrace',
            'objekt' => 'restia_katalog',
            'pole' => 'synchronizace',
            'hodnota_new' => cb_restia_katalog_summary($results),
            'vysledek' => $success ? 1 : 0,
            'zdroj' => 'administrace',
        ]);
    } catch (Throwable $error) {
        if ($isAjax) {
            cb_user_akce_zapis([
                'id_user_akce_typ' => 14,
                'modul' => 'administrace',
                'objekt' => 'restia_katalog',
                'id_objektu' => (int)($_POST['id_pob'] ?? 0),
                'pole' => 'synchronizace_pobocky',
                'vysledek' => 0,
                'err_msg' => $error->getMessage(),
                'zdroj' => 'administrace',
            ]);
            cb_admin_restia_katalog_json(['ok' => false, 'chyba' => $error->getMessage()], 400);
        }
        $_SESSION['cb_admin_script_result'] = [
            'script' => 'restia_katalog',
            'success' => false,
            'message' => $error->getMessage(),
        ];
        cb_user_akce_zapis([
            'id_user_akce_typ' => 14,
            'modul' => 'administrace',
            'objekt' => 'restia_katalog',
            'pole' => 'synchronizace',
            'vysledek' => 0,
            'err_msg' => $error->getMessage(),
            'zdroj' => 'administrace',
        ]);
    }
    header('Location: ' . $returnUrl, true, 303);
    exit;
}
