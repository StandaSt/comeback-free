<?php
// admin_lib/admin_smeny_plan_doplnit.php * Obsluha rucniho doplneni smeny_plan
declare(strict_types=1);

function cb_admin_smeny_plan_doplnit_handle(): void
{
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || (string)($_POST['cb_action'] ?? '') !== 'admin_smeny_plan_doplnit'
    ) {
        return;
    }

    $returnUrl = cb_root_url('index.php?m=administrace&page=spousteni_scriptu');
    $environment = (($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL') ? 'local' : 'server';

    try {
        if ((string)($_POST['admin_smeny_plan_confirm'] ?? '') !== '1') {
            throw new RuntimeException('Potvrďte doplnění chybějících naplánovaných směn.');
        }

        $scriptPath = realpath(__DIR__ . '/../../provoz/inicializace/doplnit_smeny_plan.php');
        if ($scriptPath === false) {
            throw new RuntimeException('Skript pro doplnění směn nebyl nalezen.');
        }

        if (!defined('CB_SMENY_PLAN_DOPLNIT_DIRECT')) {
            define('CB_SMENY_PLAN_DOPLNIT_DIRECT', true);
        }
        $GLOBALS['CB_SMENY_PLAN_DOPLNIT_ENVIRONMENT'] = $environment;
        unset($GLOBALS['CB_SMENY_PLAN_DOPLNIT_OUTPUT'], $GLOBALS['CB_SMENY_PLAN_DOPLNIT_COUNTS']);
        require $scriptPath;

        $output = trim((string)($GLOBALS['CB_SMENY_PLAN_DOPLNIT_OUTPUT'] ?? ''));
        $counts = is_array($GLOBALS['CB_SMENY_PLAN_DOPLNIT_COUNTS'] ?? null)
            ? $GLOBALS['CB_SMENY_PLAN_DOPLNIT_COUNTS']
            : [];
        unset(
            $GLOBALS['CB_SMENY_PLAN_DOPLNIT_ENVIRONMENT'],
            $GLOBALS['CB_SMENY_PLAN_DOPLNIT_OUTPUT'],
            $GLOBALS['CB_SMENY_PLAN_DOPLNIT_COUNTS']
        );

        $_SESSION['cb_admin_script_result'] = [
            'script' => 'smeny_plan',
            'success' => true,
            'message' => $output !== '' ? $output : 'Doplnění naplánovaných směn bylo dokončeno.',
        ];
        cb_user_akce_zapis([
            'id_user_akce_typ' => 15,
            'modul' => 'administrace',
            'objekt' => 'doplnit_smeny_plan',
            'pole' => 'spusteni',
            'hodnota_new' => $environment,
            'vysledek' => 1,
            'zdroj' => 'administrace',
            'detail' => $counts,
        ]);
    } catch (Throwable $e) {
        unset(
            $GLOBALS['CB_SMENY_PLAN_DOPLNIT_ENVIRONMENT'],
            $GLOBALS['CB_SMENY_PLAN_DOPLNIT_OUTPUT'],
            $GLOBALS['CB_SMENY_PLAN_DOPLNIT_COUNTS']
        );
        $_SESSION['cb_admin_script_result'] = [
            'script' => 'smeny_plan',
            'success' => false,
            'message' => $e->getMessage(),
        ];
        cb_user_akce_zapis([
            'id_user_akce_typ' => 15,
            'modul' => 'administrace',
            'objekt' => 'doplnit_smeny_plan',
            'pole' => 'spusteni',
            'vysledek' => 0,
            'err_msg' => $e->getMessage(),
            'zdroj' => 'administrace',
            'detail' => ['chyba' => $e->getMessage()],
        ]);
    }

    header('Location: ' . $returnUrl, true, 303);
    exit;
}

