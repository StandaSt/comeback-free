<?php
declare(strict_types=1);

require_once __DIR__ . '/../www/db/db_connect.php';

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$stav = 'error';
$nadpis = 'Ověření se nepodařilo';
$zprava = 'Odkaz je neplatný, nebo už neexistuje.';

$token = trim((string)($_GET['t'] ?? ''));

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/i', $token) === 1) {
    $db = db_connect();

    try {
        $db->begin_transaction();

        $stmt = $db->prepare('
            SELECT id, stav
            FROM aaa_email_overeni
            WHERE token = ?
            LIMIT 1
            FOR UPDATE
        ');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (is_array($row)) {
            $id = (int)$row['id'];
            if ((string)$row['stav'] === 'potvrzeno') {
                $db->commit();
                $stav = 'success';
                $nadpis = 'E-mail je již potvrzený';
                $zprava = 'Tato e-mailová adresa už byla potvrzena.';
            } else {
                $stmt = $db->prepare('
                    UPDATE aaa_email_overeni
                    SET stav = \'potvrzeno\',
                        potvrzeno = NOW(),
                        posledni_kontrola = NOW()
                    WHERE id = ?
                    LIMIT 1
                ');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $zmeneno = $stmt->affected_rows;
                $stmt->close();

                if ($zmeneno === 1) {
                    $db->commit();
                    $stav = 'success';
                    $nadpis = 'E-mail byl potvrzen';
                    $zprava = 'Děkujeme. E-mailová adresa byla úspěšně potvrzena.';
                } else {
                    $db->rollback();
                }
            }
        } else {
            $db->rollback();
        }
    } catch (Throwable $e) {
        $db->rollback();
        error_log('Overeni kontrolniho e-mailu - chyba: ' . $e->getMessage());
        $zprava = 'Při ověření nastala chyba. Zkuste odkaz otevřít později.';
    }
}
?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($nadpis) ?> | Pizza Comeback</title>
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:Arial, sans-serif; background:#f2f5f8; color:#24313f; }
        .box { width:min(520px, calc(100% - 32px)); box-sizing:border-box; padding:34px 30px; border-radius:10px; background:#fff; box-shadow:0 12px 36px rgba(30, 45, 62, .18); text-align:center; }
        .icon { width:54px; height:54px; margin:0 auto 18px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:bold; color:#fff; background:<?= $stav === 'success' ? '#1f9d55' : '#c0392b' ?>; }
        h1 { margin:0 0 12px; font-size:25px; line-height:1.25; }
        p { margin:0; font-size:17px; line-height:1.55; }
    </style>
</head>
<body>
<main class="box">
    <div class="icon"><?= $stav === 'success' ? '✓' : '!' ?></div>
    <h1><?= e($nadpis) ?></h1>
    <p><?= e($zprava) ?></p>
</main>
</body>
</html>
