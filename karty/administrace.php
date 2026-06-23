<?php
// K2
// karty/administrace.php * Verze: V5 * Aktualizace: 03.06.2026
declare(strict_types=1);

$card_min_html = '<p class="card_text odstup_vnejsi_0">administrace</p><br>
Zde se nastavují globální parametry systému.<br>
Logování, volba admina, upozornění na chyby, 2FA ověření a další věci.';
$card_max_html = '';

$cbAdminSystem = [
    'restia_online' => 0,
    'on_2fa' => 0,
    'system_logout' => 0,
    'pauza_obdobi' => 1000,
    'report_save' => 5,
    'restia_notifikace' => 1,
    'log_akce' => 0,
    'log_1' => 0,
    'log_2' => 0,
    'log_3' => 0,
    'log_4' => 0,
    'notif_chyby' => 0,
    'notif_bad_login' => 0,
];
$cbAdminError = '';
$cbAdminSaveName = trim((string)($_POST['cb_admin_set_name'] ?? ''));
$cbAdminSaveValue = trim((string)($_POST['cb_admin_set_value'] ?? ''));
$cbAdminActiveTab = 'system';
$cbAdminLogoutOptions = [2, 5, 10, 15, 20, 30, 60];
$cbAdminPauzaOptions = [0, 1000, 1500, 2000, 2500, 3000, 3500, 4000, 4500, 5000];
$cbAdminReportSaveOptions = [5, 10, 15, 30, 60];
$cbAdminAkceGlobalOptions = [
    '1' => 'Aktivovat logování akcí pro všechny uživatele',
    '0' => 'Deaktivovat logování',
];
$cbAdminLogLabels = [
    'log_1' => 'Měření výkonu systému (SQL + karty)',
    'log_2' => 'Měření načítání dashboardu',
    'log_3' => 'Sledování průběhu načítání a AJAX komunikace',
    'log_4' => 'Historie importu objednávek z Restia (po dnech)',
];
$cbAdminNotifLabels = [
    'notif_chyby' => [
        'nazev' => 'Chyby systému',
        'vyznam' => 'Push upozornění adminovi při systémové chybě',
    ],
    'notif_bad_login' => [
        'nazev' => 'Nepovedený login',
        'vyznam' => 'Push upozornění adminovi při neúspěšném přihlášení',
    ],
];

$cbAdminActiveUsers = [];
$cbAdminUsersLogOn = [];
$cbAdminUsersLogOff = [];
$cbAdminUsersAdmin = [];
$cbAdminUsersAdminAdd = [];

if (in_array($cbAdminSaveName, ['notif_chyby', 'notif_bad_login'], true)) {
    $cbAdminActiveTab = 'notif';
} elseif (in_array($cbAdminSaveName, ['log_1', 'log_2', 'log_3', 'log_4'], true)) {
    $cbAdminActiveTab = 'log_system';
} elseif (in_array($cbAdminSaveName, ['log_akce', 'log_akce_user_on', 'log_akce_user_off', 'log_akce_user_remove'], true)) {
    $cbAdminActiveTab = 'log_users';
} elseif (in_array($cbAdminSaveName, ['admin_user_on', 'admin_user_off'], true)) {
    $cbAdminActiveTab = 'admins';
}

try {
    $conn = db();
    if (method_exists($conn, 'set_charset')) {
        $conn->set_charset('utf8mb4');
    }

    if ($cbAdminSaveName !== '') {
        $allowedBoolFields = ['restia_online', 'on_2fa', 'restia_notifikace', 'log_1', 'log_2', 'log_3', 'log_4', 'notif_chyby', 'notif_bad_login'];
        if (in_array($cbAdminSaveName, $allowedBoolFields, true)) {
            $saveValue = ($cbAdminSaveValue === '1') ? 1 : 0;
            $sql = 'UPDATE set_system SET `' . $cbAdminSaveName . '` = ? WHERE id_set = 1 LIMIT 1';
            $stmt = $conn->prepare($sql);
            if ($stmt instanceof mysqli_stmt) {
                $stmt->bind_param('i', $saveValue);
                $stmt->execute();
                $stmt->close();
            } else {
                $cbAdminError = 'Uložení nastavení selhalo.';
            }
        } elseif ($cbAdminSaveName === 'system_logout') {
            $saveValue = (int)$cbAdminSaveValue;
            if (!in_array($saveValue, $cbAdminLogoutOptions, true)) {
                $saveValue = 20;
            }
            $stmt = $conn->prepare('UPDATE set_system SET system_logout = ? WHERE id_set = 1 LIMIT 1');
            if ($stmt instanceof mysqli_stmt) {
                $stmt->bind_param('i', $saveValue);
                $stmt->execute();
                $stmt->close();
            } else {
                $cbAdminError = 'Uložení nastavení selhalo.';
            }
        } elseif ($cbAdminSaveName === 'pauza_obdobi') {
            $saveValue = (int)$cbAdminSaveValue;
            if (!in_array($saveValue, $cbAdminPauzaOptions, true)) {
                $saveValue = 1000;
            }
            $stmt = $conn->prepare('UPDATE set_system SET pauza_obdobi = ? WHERE id_set = 1 LIMIT 1');
            if ($stmt instanceof mysqli_stmt) {
                $stmt->bind_param('i', $saveValue);
                $stmt->execute();
                $stmt->close();
            } else {
                $cbAdminError = 'Uložení nastavení selhalo.';
            }
        } elseif ($cbAdminSaveName === 'report_save') {
            $saveValue = (int)$cbAdminSaveValue;
            if (!in_array($saveValue, $cbAdminReportSaveOptions, true)) {
                $saveValue = 5;
            }
            $stmt = $conn->prepare('UPDATE set_system SET report_save = ? WHERE id_set = 1 LIMIT 1');
            if ($stmt instanceof mysqli_stmt) {
                $stmt->bind_param('i', $saveValue);
                $stmt->execute();
                $stmt->close();
            } else {
                $cbAdminError = 'Uložení nastavení selhalo.';
            }
        }
    }

    if ($cbAdminError === '' && $cbAdminSaveName === 'log_akce') {
        $saveValue = ($cbAdminSaveValue === '1') ? 1 : 0;
        $stmt = $conn->prepare('UPDATE set_system SET log_akce = ? WHERE id_set = 1 LIMIT 1');
        if ($stmt instanceof mysqli_stmt) {
            $stmt->bind_param('i', $saveValue);
            $stmt->execute();
            $stmt->close();
        } else {
            $cbAdminError = 'Ulozeni nastaveni selhalo.';
        }
    }

    if ($cbAdminError === '' && ($cbAdminSaveName === 'log_akce_user_on' || $cbAdminSaveName === 'log_akce_user_off')) {
        $idUser = (int)$cbAdminSaveValue;
        if ($idUser > 0) {
            $logOn = ($cbAdminSaveName === 'log_akce_user_on') ? 1 : 0;
            $logOff = ($cbAdminSaveName === 'log_akce_user_off') ? 1 : 0;

            $stmtDel = $conn->prepare('DELETE FROM user_akce_on_off WHERE id_user = ?');
            if ($stmtDel instanceof mysqli_stmt) {
                $stmtDel->bind_param('i', $idUser);
                $stmtDel->execute();
                $stmtDel->close();
            } else {
                $cbAdminError = 'Ulozeni nastaveni selhalo.';
            }

            if ($cbAdminError === '') {
                $stmtIns = $conn->prepare('INSERT INTO user_akce_on_off (id_user, log_on, log_off) VALUES (?, ?, ?)');
                if ($stmtIns instanceof mysqli_stmt) {
                    $stmtIns->bind_param('iii', $idUser, $logOn, $logOff);
                    $stmtIns->execute();
                    $stmtIns->close();
                } else {
                    $cbAdminError = 'Ulozeni nastaveni selhalo.';
                }
            }
        }
    }

    if ($cbAdminError === '' && $cbAdminSaveName === 'log_akce_user_remove') {
        $idUser = (int)$cbAdminSaveValue;
        if ($idUser > 0) {
            $stmt = $conn->prepare('DELETE FROM user_akce_on_off WHERE id_user = ?');
            if ($stmt instanceof mysqli_stmt) {
                $stmt->bind_param('i', $idUser);
                $stmt->execute();
                $stmt->close();
            } else {
                $cbAdminError = 'Ulozeni nastaveni selhalo.';
            }
        }
    }

    if ($cbAdminError === '' && ($cbAdminSaveName === 'admin_user_on' || $cbAdminSaveName === 'admin_user_off')) {
        $idUser = (int)$cbAdminSaveValue;
        if ($idUser > 0) {
            $adminValue = ($cbAdminSaveName === 'admin_user_on') ? 1 : 0;
            if ($idUser === 1) {
                $adminValue = 1;
            }

            $stmtAdmin = $conn->prepare('UPDATE `user` SET `admin` = ? WHERE id_user = ? LIMIT 1');
            if ($stmtAdmin instanceof mysqli_stmt) {
                $stmtAdmin->bind_param('ii', $adminValue, $idUser);
                $stmtAdmin->execute();
                $stmtAdmin->close();
            } else {
                $cbAdminError = 'Ulozeni nastaveni selhalo.';
            }

            if ($cbAdminError === '') {
                if ($adminValue === 1) {
                    $stmtRole = $conn->prepare('INSERT IGNORE INTO user_role (id_user, id_role) VALUES (?, 1)');
                    if ($stmtRole instanceof mysqli_stmt) {
                        $stmtRole->bind_param('i', $idUser);
                        $stmtRole->execute();
                        $stmtRole->close();
                    } else {
                        $cbAdminError = 'Ulozeni nastaveni selhalo.';
                    }
                } else {
                    $stmtRole = $conn->prepare('DELETE FROM user_role WHERE id_user = ? AND id_role = 1');
                    if ($stmtRole instanceof mysqli_stmt) {
                        $stmtRole->bind_param('i', $idUser);
                        $stmtRole->execute();
                        $stmtRole->close();
                    } else {
                        $cbAdminError = 'Ulozeni nastaveni selhalo.';
                    }
                }
            }

            if ($cbAdminError === '') {
                if ($adminValue === 1) {
                    $stmtUserRole = $conn->prepare('UPDATE `user` SET id_role = 1 WHERE id_user = ? LIMIT 1');
                    if ($stmtUserRole instanceof mysqli_stmt) {
                        $stmtUserRole->bind_param('i', $idUser);
                        $stmtUserRole->execute();
                        $stmtUserRole->close();
                    } else {
                        $cbAdminError = 'Ulozeni nastaveni selhalo.';
                    }
                } else {
                    $stmtMinRole = $conn->prepare('SELECT MIN(id_role) AS min_role FROM user_role WHERE id_user = ?');
                    if ($stmtMinRole instanceof mysqli_stmt) {
                        $stmtMinRole->bind_param('i', $idUser);
                        $stmtMinRole->execute();
                        $resMinRole = $stmtMinRole->get_result();
                        $rowMinRole = ($resMinRole instanceof mysqli_result) ? $resMinRole->fetch_assoc() : null;
                        if ($resMinRole instanceof mysqli_result) {
                            $resMinRole->free();
                        }
                        $stmtMinRole->close();
                        $minRole = is_array($rowMinRole) ? (int)($rowMinRole['min_role'] ?? 0) : 0;
                        if ($minRole <= 0) {
                            $resFallbackRole = $conn->query('SELECT id_role FROM cis_role WHERE aktivni = 1 ORDER BY id_role DESC LIMIT 1');
                            if ($resFallbackRole instanceof mysqli_result) {
                                $rowFallbackRole = $resFallbackRole->fetch_assoc();
                                $resFallbackRole->free();
                                $minRole = (int)($rowFallbackRole['id_role'] ?? 0);
                            }
                        }

                        if ($minRole > 0) {
                            $stmtUserRole = $conn->prepare('UPDATE `user` SET id_role = ? WHERE id_user = ? LIMIT 1');
                            if ($stmtUserRole instanceof mysqli_stmt) {
                                $stmtUserRole->bind_param('ii', $minRole, $idUser);
                                $stmtUserRole->execute();
                                $stmtUserRole->close();
                            } else {
                                $cbAdminError = 'Ulozeni nastaveni selhalo.';
                            }
                        }
                    } else {
                        $cbAdminError = 'Ulozeni nastaveni selhalo.';
                    }
                }
            }
        }
    }

    if ($cbAdminError === '') {
        $res = $conn->query('
            SELECT restia_online, on_2fa, system_logout, pauza_obdobi, report_save, restia_notifikace, log_akce, log_1, log_2, log_3, log_4, notif_chyby, notif_bad_login
            FROM set_system
            WHERE id_set = 1
            LIMIT 1
        ');

        if ($res instanceof mysqli_result) {
            $row = $res->fetch_assoc();
            if (is_array($row)) {
                $cbAdminSystem['restia_online'] = (int)($row['restia_online'] ?? 0);
                $cbAdminSystem['on_2fa'] = (int)($row['on_2fa'] ?? 0);
                $cbAdminSystem['system_logout'] = (int)($row['system_logout'] ?? 0);
                $cbAdminSystem['pauza_obdobi'] = (int)($row['pauza_obdobi'] ?? 1000);
                $cbAdminSystem['report_save'] = (int)($row['report_save'] ?? 5);
                $cbAdminSystem['restia_notifikace'] = (int)($row['restia_notifikace'] ?? 1);
                $cbAdminSystem['log_akce'] = (int)($row['log_akce'] ?? 0);
                $cbAdminSystem['log_1'] = (int)($row['log_1'] ?? 0);
                $cbAdminSystem['log_2'] = (int)($row['log_2'] ?? 0);
                $cbAdminSystem['log_3'] = (int)($row['log_3'] ?? 0);
                $cbAdminSystem['log_4'] = (int)($row['log_4'] ?? 0);
                $cbAdminSystem['notif_chyby'] = (int)($row['notif_chyby'] ?? 0);
                $cbAdminSystem['notif_bad_login'] = (int)($row['notif_bad_login'] ?? 0);
                if (function_exists('cb_store_system_settings')) {
                    cb_store_system_settings($row);
                }
            } else {
                $cbAdminError = 'Nastavení systému nebylo nalezeno.';
            }
            $res->free();
        } else {
            $cbAdminError = 'Načtení nastavení systému selhalo.';
        }
    }
    if ($cbAdminError === '') {
        $resUsers = $conn->query("
            SELECT id_user, prijmeni, jmeno
            FROM `user`
            WHERE in_system = 1
            ORDER BY prijmeni, jmeno
        ");
        if ($resUsers instanceof mysqli_result) {
            while ($rowUser = $resUsers->fetch_assoc()) {
                $idUser = (int)($rowUser['id_user'] ?? 0);
                if ($idUser <= 0) {
                    continue;
                }
                $prijmeni = trim((string)($rowUser['prijmeni'] ?? ''));
                $jmeno = trim((string)($rowUser['jmeno'] ?? ''));
                $label = trim($prijmeni . ' ' . $jmeno);
                if ($label === '') {
                    $label = 'ID ' . $idUser;
                }
                $cbAdminActiveUsers[] = [
                    'id_user' => $idUser,
                    'label' => $label,
                ];
            }
            $resUsers->free();
        }
    }
    if ($cbAdminError === '') {
        $resLogUsers = $conn->query("
            SELECT uao.id_user, uao.log_on, uao.log_off, u.prijmeni, u.jmeno
            FROM user_akce_on_off uao
            LEFT JOIN `user` u ON u.id_user = uao.id_user
            ORDER BY u.prijmeni, u.jmeno, uao.id_user
        ");
        if ($resLogUsers instanceof mysqli_result) {
            while ($rowLogUser = $resLogUsers->fetch_assoc()) {
                $idUser = (int)($rowLogUser['id_user'] ?? 0);
                if ($idUser <= 0) {
                    continue;
                }
                $prijmeni = trim((string)($rowLogUser['prijmeni'] ?? ''));
                $jmeno = trim((string)($rowLogUser['jmeno'] ?? ''));
                $label = trim($prijmeni . ' ' . $jmeno);
                if ($label === '') {
                    $label = 'ID ' . $idUser;
                }
                if ((int)($rowLogUser['log_on'] ?? 0) === 1) {
                    $cbAdminUsersLogOn[] = [
                        'id_user' => $idUser,
                        'label' => $label,
                    ];
                }
                if ((int)($rowLogUser['log_off'] ?? 0) === 1) {
                    $cbAdminUsersLogOff[] = [
                        'id_user' => $idUser,
                        'label' => $label,
                    ];
                }
            }
            $resLogUsers->free();
        }
    }
    if ($cbAdminError === '') {
        $resAdminAddUsers = $conn->query("
            SELECT id_user, prijmeni, jmeno
            FROM `user`
            WHERE aktivni = 1
              AND in_system = 1
              AND id_role <= 3
              AND `admin` = 0
              AND id_user <> 1
            ORDER BY prijmeni, jmeno
        ");
        if ($resAdminAddUsers instanceof mysqli_result) {
            while ($rowAdminAddUser = $resAdminAddUsers->fetch_assoc()) {
                $idUser = (int)($rowAdminAddUser['id_user'] ?? 0);
                if ($idUser <= 0) {
                    continue;
                }
                $prijmeni = trim((string)($rowAdminAddUser['prijmeni'] ?? ''));
                $jmeno = trim((string)($rowAdminAddUser['jmeno'] ?? ''));
                $label = trim($prijmeni . ' ' . $jmeno);
                if ($label === '') {
                    $label = 'ID ' . $idUser;
                }
                $cbAdminUsersAdminAdd[] = [
                    'id_user' => $idUser,
                    'label' => $label,
                ];
            }
            $resAdminAddUsers->free();
        }
    }
    if ($cbAdminError === '') {
        $resAdminUsers = $conn->query("
            SELECT id_user, prijmeni, jmeno, `admin`
            FROM `user`
            WHERE in_system = 1
              AND (id_user = 1 OR (`admin` = 1 AND aktivni = 1))
            ORDER BY id_user ASC
        ");
        if ($resAdminUsers instanceof mysqli_result) {
            while ($rowAdminUser = $resAdminUsers->fetch_assoc()) {
                $idUser = (int)($rowAdminUser['id_user'] ?? 0);
                if ($idUser <= 0) {
                    continue;
                }
                $prijmeni = trim((string)($rowAdminUser['prijmeni'] ?? ''));
                $jmeno = trim((string)($rowAdminUser['jmeno'] ?? ''));
                $label = trim($prijmeni . ' ' . $jmeno);
                if ($label === '') {
                    $label = 'ID ' . $idUser;
                }
                $cbAdminUsersAdmin[] = [
                    'id_user' => $idUser,
                    'label' => $label,
                    'fixed' => ($idUser === 1),
                ];
            }
            $resAdminUsers->free();
        }
    }
} catch (Throwable $e) {
    $cbAdminError = 'Načtení nastavení systému selhalo.';
}

$cbAdminBoolClass = static function (int $value): string {
    return $value === 1 ? 'txt_zelena text_tucny' : 'txt_cervena text_tucny';
};

$cbAdminFormAction = cb_url('/index.php');

ob_start();
?>
<?php if ($cbAdminError !== ''): ?>
  <p class="card_text txt_cervena odstup_vnejsi_0"><?= h($cbAdminError) ?></p>
<?php else: ?>
  <?php // VZHLED K2 JE ZAMCENY: bez vyslovneho schvaleni nemenit sirky sloupcu, zarovnani, zalamovani ani texty (vcetne diakritiky). ?>
  <div class="card_stack gap_8">
    <style>
      .cb_admin_tabs{display:flex;gap:8px;flex-wrap:wrap;}
      .cb_admin_tabs label{padding:4px 10px;border:1px solid #d0d0d0;background:#fff;cursor:pointer;}
      .cb_admin_panel{display:none;}
      #cb_admin_tab_system:checked ~ .cb_admin_tabs label[for="cb_admin_tab_system"],
      #cb_admin_tab_log_system:checked ~ .cb_admin_tabs label[for="cb_admin_tab_log_system"],
      #cb_admin_tab_log_users:checked ~ .cb_admin_tabs label[for="cb_admin_tab_log_users"],
      #cb_admin_tab_admins:checked ~ .cb_admin_tabs label[for="cb_admin_tab_admins"],
      #cb_admin_tab_notif:checked ~ .cb_admin_tabs label[for="cb_admin_tab_notif"]{font-weight:700;}
      #cb_admin_tab_system:checked ~ .cb_admin_panel_system,
      #cb_admin_tab_log_system:checked ~ .cb_admin_panel_log_system,
      #cb_admin_tab_log_users:checked ~ .cb_admin_panel_log_users,
      #cb_admin_tab_admins:checked ~ .cb_admin_panel_admins,
      #cb_admin_tab_notif:checked ~ .cb_admin_panel_notif{display:block;}
    </style>
    <input type="radio" id="cb_admin_tab_system" name="cb_admin_tab"<?= $cbAdminActiveTab === 'system' ? ' checked' : '' ?> hidden>
    <input type="radio" id="cb_admin_tab_log_system" name="cb_admin_tab"<?= $cbAdminActiveTab === 'log_system' ? ' checked' : '' ?> hidden>
    <input type="radio" id="cb_admin_tab_log_users" name="cb_admin_tab"<?= $cbAdminActiveTab === 'log_users' ? ' checked' : '' ?> hidden>
    <input type="radio" id="cb_admin_tab_admins" name="cb_admin_tab"<?= $cbAdminActiveTab === 'admins' ? ' checked' : '' ?> hidden>
    <input type="radio" id="cb_admin_tab_notif" name="cb_admin_tab"<?= $cbAdminActiveTab === 'notif' ? ' checked' : '' ?> hidden>
    <div class="cb_admin_tabs">
      <label for="cb_admin_tab_system" class="zaobleni_6">set systém</label>
      <label for="cb_admin_tab_log_system" class="zaobleni_6">logování systém</label>
      <label for="cb_admin_tab_log_users" class="zaobleni_6">logování users</label>
      <label for="cb_admin_tab_admins" class="zaobleni_6">set admins</label>
      <label for="cb_admin_tab_notif" class="zaobleni_6">Upozornění</label>
    </div>
    <div class="cb_admin_panel cb_admin_panel_system">
    <p class="card_text text_tucny odstup_vnejsi_0" style="font-size:16px;text-align:left;">Globální nastavení systému</p>
    <div class="table-wrap ram_normal bg_bila" style="width:100%;margin:0 auto;">
      <table class="table ram_normal bg_bila radek_1_35 sirka100" style="width:100%;table-layout:auto;">
        <thead>
          <tr>
            <th style="width:1%;white-space:nowrap;">Nastavení</th>
            <th style="width:1%;white-space:nowrap;">Aktuální hodnota</th>
            <th style="white-space:nowrap;">Význam</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="white-space:nowrap;">Restia online</td>
            <td class="<?= h($cbAdminBoolClass($cbAdminSystem['restia_online'])) ?>">
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="restia_online">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <option value="1"<?= $cbAdminSystem['restia_online'] === 1 ? ' selected' : '' ?>>Aktivni</option>
                  <option value="0"<?= $cbAdminSystem['restia_online'] === 0 ? ' selected' : '' ?>>Neaktivní</option>
                </select>
              </form>
            </td>
            <td style="white-space:nowrap;">Online aktualizace objednávek Restia</td>
          </tr>
          <tr>
            <td style="white-space:nowrap;">2FA</td>
            <td class="<?= h($cbAdminBoolClass($cbAdminSystem['on_2fa'])) ?>">
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="on_2fa">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <option value="1"<?= $cbAdminSystem['on_2fa'] === 1 ? ' selected' : '' ?>>Aktivní</option>
                  <option value="0"<?= $cbAdminSystem['on_2fa'] === 0 ? ' selected' : '' ?>>Neaktivní</option>
                </select>
              </form>
            </td>
            <td style="white-space:nowrap;">Dvoufázové ověření při přihlášení</td>
          </tr>
          <tr>
            <td style="white-space:nowrap;">System logout</td>
            <td class="text_tucny">
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="system_logout">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <?php foreach ($cbAdminLogoutOptions as $cbLogoutValue): ?>
                    <option value="<?= h((string)$cbLogoutValue) ?>"<?= $cbAdminSystem['system_logout'] === $cbLogoutValue ? ' selected' : '' ?>><?= h((string)$cbLogoutValue) ?> min</option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td style="white-space:nowrap;">Odhlášení uživatele pro neaktivitu, limit</td>
          </tr>
          <tr>
            <td style="white-space:nowrap;">Pauza období</td>
            <td class="text_tucny">
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="pauza_obdobi">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <?php foreach ($cbAdminPauzaOptions as $cbPauzaValue): ?>
                    <option value="<?= h((string)$cbPauzaValue) ?>"<?= $cbAdminSystem['pauza_obdobi'] === $cbPauzaValue ? ' selected' : '' ?>><?= h((string)$cbPauzaValue) ?> ms</option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td style="white-space:nowrap;">Prodleva při ruční volbě globálního nastavení období</td>
          </tr>
          <tr>
            <td style="white-space:nowrap;">Uložení reportu</td>
            <td class="text_tucny">
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="report_save">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <?php foreach ($cbAdminReportSaveOptions as $cbReportSaveValue): ?>
                    <option value="<?= h((string)$cbReportSaveValue) ?>"<?= $cbAdminSystem['report_save'] === $cbReportSaveValue ? ' selected' : '' ?>><?= h((string)$cbReportSaveValue) ?> min</option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td style="white-space:nowrap;">Kolik minut před uzavřením restaurace lze uložit</td>
          </tr>
          <tr>
            <td style="white-space:nowrap;">Restia CRON</td>
            <td class="<?= h($cbAdminBoolClass($cbAdminSystem['restia_notifikace'])) ?>">
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="restia_notifikace">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <option value="1"<?= $cbAdminSystem['restia_notifikace'] === 1 ? ' selected' : '' ?>>Aktivní</option>
                  <option value="0"<?= $cbAdminSystem['restia_notifikace'] === 0 ? ' selected' : '' ?>>Vypnuto</option>
                </select>
              </form>
            </td>
            <td style="white-space:nowrap;">Upozornění adminovi na průběh aktualizace</td>
          </tr>
        </tbody>
      </table>
    </div>
    </div>
    <div class="cb_admin_panel cb_admin_panel_notif">
    <p class="card_text text_tucny odstup_vnejsi_0" style="font-size:16px;text-align:left;">Upozornění adminovi</p>
    <div class="table-wrap ram_normal bg_bila" style="width:100%;margin:0 auto;">
      <table class="table ram_normal bg_bila radek_1_35 sirka100" style="width:100%;table-layout:auto;">
        <thead>
          <tr>
            <th style="width:1%;white-space:nowrap;">Aktivní</th>
            <th style="width:1%;white-space:nowrap;">Upozornění</th>
            <th>Význam</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cbAdminNotifLabels as $cbNotifKey => $cbNotifInfo): ?>
            <tr>
              <td class="txt_c" style="white-space:nowrap;">
                <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                  <input type="hidden" name="cb_admin_set_name" value="<?= h($cbNotifKey) ?>">
                  <input type="hidden" name="cb_admin_set_value" value="0">
                  <input
                    type="checkbox"
                    name="cb_admin_set_value"
                    value="1"
                    <?= ((int)($cbAdminSystem[$cbNotifKey] ?? 0) === 1) ? 'checked' : '' ?>
                    onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}"
                  >
                </form>
              </td>
              <td style="white-space:nowrap;"><?= h((string)$cbNotifInfo['nazev']) ?></td>
              <td><?= h((string)$cbNotifInfo['vyznam']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    </div>
    <div class="cb_admin_panel cb_admin_panel_log_system">
    <p class="card_text text_tucny odstup_vnejsi_0" style="font-size:16px;text-align:left;">Logování akcí systému, měření časů</p>
    <div class="table-wrap ram_normal bg_bila" style="width:100%;margin:0 auto;">
      <table class="table ram_normal bg_bila radek_1_35 sirka100" style="width:100%;table-layout:auto;">
        <thead>
          <tr>
            <th style="width:1%;white-space:nowrap;">Aktivní</th>
            <th>Aktivace logování</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cbAdminLogLabels as $cbLogKey => $cbLogLabel): ?>
            <tr>
              <td class="txt_c" style="white-space:nowrap;">
                <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                  <input type="hidden" name="cb_admin_set_name" value="<?= h($cbLogKey) ?>">
                  <input type="hidden" name="cb_admin_set_value" value="0">
                  <input
                    type="checkbox"
                    name="cb_admin_set_value"
                    value="1"
                    <?= ((int)($cbAdminSystem[$cbLogKey] ?? 0) === 1) ? 'checked' : '' ?>
                    onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}"
                  >
                </form>
              </td>
              <td><?= h($cbLogLabel) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    </div>
    <div class="cb_admin_panel cb_admin_panel_log_users">
    <p class="card_text text_tucny odstup_vnejsi_0" style="font-size:16px;text-align:left;">Logování akcí uživatelů</p>
    <div class="table-wrap ram_normal bg_bila" style="width:100%;margin:0 auto;">
      <table class="table ram_normal bg_bila radek_1_35 sirka100" style="width:100%;table-layout:auto;">
        <thead>
          <tr>
            <th style="width:1%;white-space:nowrap;">Logování akcí</th>
            <th>Nastavení</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="white-space:nowrap;">Globální nastavení</td>
            <td>
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="log_akce">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <?php foreach ($cbAdminAkceGlobalOptions as $cbGlobalValue => $cbGlobalLabel): ?>
                    <option value="<?= h($cbGlobalValue) ?>"<?= $cbAdminSystem['log_akce'] === (int)$cbGlobalValue ? ' selected' : '' ?>><?= h($cbGlobalLabel) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
          </tr>
          <tr>
            <td style="white-space:nowrap;">Přidej uživatele do logování</td>
            <td>
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="log_akce_user_on">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.value!==''){if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}}">
                  <option value="">Vyber uživatele</option>
                  <?php foreach ($cbAdminActiveUsers as $cbUser): ?>
                    <option value="<?= h((string)$cbUser['id_user']) ?>"><?= h((string)$cbUser['label']) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <div class="odstup_horni_4">
                <?php if ($cbAdminUsersLogOn === []): ?>
                  Žádný logovaný uživatel
                <?php else: ?>
                  <?php foreach ($cbAdminUsersLogOn as $cbLogUserOn): ?>
                    <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1" style="display:inline;">
                      <input type="hidden" name="cb_admin_set_name" value="log_akce_user_remove">
                      <input type="hidden" name="cb_admin_set_value" value="<?= h((string)$cbLogUserOn['id_user']) ?>">
                      <button type="submit" class="odstup_vnejsi_0" style="border:0;background:transparent;color:#c00000;cursor:pointer;font-weight:700;line-height:1;padding:0 2px;" title="Odstranit z logování">×</button>
                    </form>
                    <span><?= h((string)$cbLogUserOn['label']) ?></span><br>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <tr>
            <td style="white-space:nowrap;">Zakaž logování u uživatele</td>
            <td>
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="log_akce_user_off">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.value!==''){if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}}">
                  <option value="">Vyber uživatele</option>
                  <?php foreach ($cbAdminActiveUsers as $cbUser): ?>
                    <option value="<?= h((string)$cbUser['id_user']) ?>"><?= h((string)$cbUser['label']) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <div class="odstup_horni_4">
                <?php if ($cbAdminUsersLogOff === []): ?>
                  Žádný uživatel vyjmutý z logování
                <?php else: ?>
                  <?php foreach ($cbAdminUsersLogOff as $cbLogUserOff): ?>
                    <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1" style="display:inline;">
                      <input type="hidden" name="cb_admin_set_name" value="log_akce_user_remove">
                      <input type="hidden" name="cb_admin_set_value" value="<?= h((string)$cbLogUserOff['id_user']) ?>">
                      <button type="submit" class="odstup_vnejsi_0" style="border:0;background:transparent;color:#c00000;cursor:pointer;font-weight:700;line-height:1;padding:0 2px;" title="Odstranit z logování">×</button>
                    </form>
                    <span><?= h((string)$cbLogUserOff['label']) ?></span><br>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </div>
    <div class="cb_admin_panel cb_admin_panel_admins">
    <p class="card_text text_tucny odstup_vnejsi_0" style="font-size:16px;text-align:left;">Administrátoři systému</p>
    <div class="table-wrap ram_normal bg_bila" style="width:100%;margin:0 auto;">
      <table class="table ram_normal bg_bila radek_1_35 sirka100" style="width:100%;table-layout:auto;">
        <tbody>
          <tr>
            <td colspan="2">
              <p class="card_text text_tucny odstup_vnejsi_0">Přidat admina</p>
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0 displ_flex gap_8 ai_stred" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="admin_user_on">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="var b=this.form.querySelector('button[type=submit]');if(b){var on=this.value!=='';b.disabled=!on;b.setAttribute('aria-disabled',on?'false':'true');}">
                  <option value="">vyber dalšího admina</option>
                  <?php foreach ($cbAdminUsersAdminAdd as $cbUser): ?>
                    <option value="<?= h((string)$cbUser['id_user']) ?>"><?= h((string)$cbUser['label']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" disabled aria-disabled="true">Přidat</button>
              </form>
            </td>
          </tr>
          <tr>
            <td colspan="2" style="padding-top:14px;">
              <p class="card_text text_tucny txt_cervena odstup_vnejsi_0">Odebrat admina</p>
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0 displ_flex gap_8 ai_stred" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="admin_user_off">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="var b=this.form.querySelector('button[type=submit]');if(b){var on=this.value!=='';b.disabled=!on;b.setAttribute('aria-disabled',on?'false':'true');}">
                  <option value="">Zvol admina k odstranění</option>
                  <?php foreach ($cbAdminUsersAdmin as $cbAdminUser): ?>
                    <?php if (!(bool)($cbAdminUser['fixed'] ?? false)): ?>
                      <option value="<?= h((string)$cbAdminUser['id_user']) ?>"><?= h((string)$cbAdminUser['label']) ?></option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" disabled aria-disabled="true">Odebrat</button>
              </form>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </div>
  </div>
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();
