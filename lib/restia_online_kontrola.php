<?php
// lib/restia_online_kontrola.php
// Verze: V4
// Aktualizace: 14.05.2026
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }
    $GLOBALS['cb_restia_online_session_ready'] = true;
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/../config/secrets.php';

if (PHP_SAPI === 'cli') {
    $PROSTREDI = 'SERVER';
}

$db = db();
$q = $db->query("SELECT restia_online FROM set_system WHERE id_set = 1");
$row = ($q instanceof mysqli_result) ? $q->fetch_assoc() : null;
if ($q instanceof mysqli_result) {
    $q->free();
}
if ((int)($row['restia_online'] ?? 0) === 0) {
    return;
}

if (!function_exists('cb_restia_online_kontrola_update_row')) {
    function cb_restia_online_kontrola_update_row(
        mysqli $db,
        int $idAkce,
        int $zapisy,
        int $aktualizace,
        int $ignore,
        int $aktivni
    ): void {
        $stmt = $db->prepare("
            UPDATE online_restia
            SET konec = NOW(),
                zapisy = ?,
                aktualizace = ?,
                `ignore` = ?,
                aktivni = ?
            WHERE id_akce = ?
        ");
        if ($stmt === false) {
            throw new RuntimeException('Nepodarilo se pripravit UPDATE online_restia.');
        }
        $stmt->bind_param("iiiii", $zapisy, $aktualizace, $ignore, $aktivni, $idAkce);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_online_kontrola_notify_admin')) {
    function cb_restia_online_kontrola_notify_admin(
        mysqli $db,
        int $idAkce,
        int $zapisy,
        int $aktualizace,
        int $ignore
    ): void {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        $resSet = $db->query('SELECT restia_notifikace FROM set_system WHERE id_set = 1 LIMIT 1');
        $rowSet = ($resSet instanceof mysqli_result) ? $resSet->fetch_assoc() : null;
        if ($resSet instanceof mysqli_result) {
            $resSet->free();
        }
        if ((int)($rowSet['restia_notifikace'] ?? 0) !== 1) {
            return;
        }

        $stmt = $db->prepare('SELECT konec FROM online_restia WHERE id_akce = ? LIMIT 1');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $idAkce);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        $konec = trim((string)($row['konec'] ?? ''));
        if ($konec === '') {
            $konec = date('Y-m-d H:i:s');
        }
        $konecDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $konec, new DateTimeZone('Europe/Prague'));
        $konecText = ($konecDt instanceof DateTimeImmutable) ? $konecDt->format('j.n.Y H:i:s') : $konec;
        $celkem = $zapisy + $aktualizace + $ignore;

        $obsah = 'Aktualizace ' . $konecText . "\n"
            . 'zápisy      ' . (string)$zapisy . "\n"
            . 'aktualizace ' . (string)$aktualizace . "\n"
            . 'ignore      ' . (string)$ignore . "\n"
            . 'celkem      ' . (string)$celkem;

        require_once __DIR__ . '/../notifikace/notifikace_2fa.php';
        cb_push_send_admin_info([1], 'restia_cron', $obsah, 'Admin info');
    }
}

if (!function_exists('cb_restia_online_kontrola')) {
    function cb_restia_online_kontrola(bool $force = false)
    {
        $db = db();

        $cbUser = $_SESSION['cb_user'] ?? null;
        $idUserRaw = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
        $idUser = ($idUserRaw > 0) ? $idUserRaw : null;

        $q = $db->query("SELECT id_akce FROM online_restia WHERE aktivni = 1 LIMIT 1");
        if ($q instanceof mysqli_result) {
            $isRunning = ($q->num_rows > 0);
            $q->free();
            if ($isRunning) {
                return;
            }
        }

        $q = $db->query("SELECT konec FROM online_restia WHERE aktivni = 0 ORDER BY konec DESC LIMIT 1");
        if (!$force && $q instanceof mysqli_result) {
            $row = $q->fetch_assoc();
            $q->free();
            if (!empty($row['konec'])) {
                $last = strtotime((string)$row['konec']);
                if ($last !== false && (time() - $last) < 120) {
                    return;
                }
            }
        } elseif ($q instanceof mysqli_result) {
            $q->free();
        }

        $stmt = $db->prepare("
            INSERT INTO online_restia (id_user, start, aktivni)
            VALUES (?, NOW(), 1)
        ");
        if ($stmt === false) {
            throw new RuntimeException('Nepodarilo se pripravit INSERT online_restia.');
        }
        $stmt->bind_param("i", $idUser);
        $stmt->execute();
        $idAkce = (int)$stmt->insert_id;
        $stmt->close();

        $zapisy = 0;
        $aktualizace = 0;
        $ignore = 0;

        try {
            $file = __DIR__ . '/restia_online.php';
            $sessionReleased = false;

            if (session_status() === PHP_SESSION_ACTIVE) {
                $GLOBALS['cb_restia_online_session_ready'] = true;
                session_write_close();
                $sessionReleased = true;
            }

            if (!file_exists($file)) {
                if (function_exists('zapis_log_chyby')) {
                    zapis_log_chyby('Restia online: Soubor nenalezen restia_online.php');
                }
            } else {
                $GLOBALS['cb_restia_online_progress_callback'] = static function (array $progress) use ($db, $idAkce, &$zapisy, &$aktualizace, &$ignore): void {
                    $zapisy = (int)($progress['zapisy'] ?? 0);
                    $aktualizace = (int)($progress['aktualizace'] ?? 0);
                    $ignore = (int)($progress['ignore'] ?? 0);
                    $isFinished = ((int)($progress['finished'] ?? 0) === 1) ? 1 : 0;
                    cb_restia_online_kontrola_update_row(
                        $db,
                        $idAkce,
                        $zapisy,
                        $aktualizace,
                        $ignore,
                        $isFinished ? 0 : 1
                    );
                };

                $result = include $file;

                if (is_array($result)) {
                    $zapisy = (int)($result['zapisy'] ?? 0);
                    $aktualizace = (int)($result['aktualizace'] ?? 0);
                    $ignore = (int)($result['ignore'] ?? 0);
                }
            }

            if ($sessionReleased) {
                unset($GLOBALS['cb_restia_online_session_ready']);
            }
            unset($GLOBALS['cb_restia_online_progress_callback']);
        } catch (Throwable $e) {
            unset($GLOBALS['cb_restia_online_session_ready'], $GLOBALS['cb_restia_online_progress_callback']);

            if (function_exists('zapis_log_chyby')) {
                zapis_log_chyby('Restia online chyba: ' . $e->getMessage());
            }
        }

        cb_restia_online_kontrola_update_row($db, $idAkce, $zapisy, $aktualizace, $ignore, 0);
        cb_restia_online_kontrola_notify_admin($db, $idAkce, $zapisy, $aktualizace, $ignore);
    }
}

if (!defined('CB_RESTIA_ONLINE_KONTROLA_AUTO_RUN') || CB_RESTIA_ONLINE_KONTROLA_AUTO_RUN !== false) {
    cb_restia_online_kontrola();
}
