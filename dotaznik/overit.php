<?php
/*
 * Ucel souboru: Zpracuje potvrzeni nebo odmitnuti verejneho dotazniku z e-mailoveho odkazu.
 * Po zmene stavu bezpecne uzavre jednorazovy overovaci token.
 */
declare(strict_types=1);

require_once __DIR__ . '/../common/db/db_connect.php';
require_once __DIR__ . '/../hr/hr_lib/hr_vd_stavy.php';

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$stav = 'error';
$nadpis = 'Ověření se nepodařilo';
$zprava = 'Odkaz je neplatný, použitý nebo mu vypršela platnost.';

$token = trim((string)($_GET['t'] ?? ''));
$akce = trim((string)($_GET['a'] ?? 'potvrdit'));
if ($akce !== 'odmitnout') {
    $akce = 'potvrdit';
}

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/i', $token) === 1) {
    $db = db_connect();
    $hash = hash('sha256', $token);

    try {
        $db->begin_transaction();

        // Najde platny a nepouzity token pro overeni verejneho dotazniku.
        $stmt = $db->prepare('
            SELECT id_vd_token, id_vd
            FROM hr_vd_token
            WHERE token_hash = ?
              AND aktivni = 1
              AND pouzito IS NULL
              AND (platnost_do IS NULL OR platnost_do >= NOW())
            LIMIT 1
            FOR UPDATE
        ');
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (is_array($row)) {
            $idVdToken = (int)$row['id_vd_token'];
            $idVd = (int)$row['id_vd'];

            // Cile i puvodni stav jsou predany jako parametry, ne jako text SQL.
            $idCilovyStav = $akce === 'odmitnout'
                ? HR_VD_STAV_VD_NEPOTVRZENO
                : HR_VD_STAV_NOVY;
            $aktivni = $akce === 'odmitnout' ? 0 : 1;
            $idPuvodniStav = HR_VD_STAV_NEPOTVRZENO;

            // Potvrdi VD do naboru, nebo ho vyradi pri odmitnuti odkazu.
            $stmt = $db->prepare('
                UPDATE hr_vd
                SET id_vd_stav = ?,
                    aktivni = ?,
                    upraveno = NOW()
                WHERE id_vd = ?
                  AND id_vd_stav = ?
                  AND aktivni = 1
            ');
            $stmt->bind_param('iiii', $idCilovyStav, $aktivni, $idVd, $idPuvodniStav);
            $stmt->execute();
            $zmeneno = $stmt->affected_rows;
            $stmt->close();

            if ($zmeneno === 1) {
                // Oznaci token jako pouzity, aby odkaz nesel pouzit opakovane.
                $stmt = $db->prepare('
                    UPDATE hr_vd_token
                    SET pouzito = NOW(),
                        aktivni = 0
                    WHERE id_vd_token = ?
                    LIMIT 1
                ');
                $stmt->bind_param('i', $idVdToken);
                $stmt->execute();
                $stmt->close();

                $db->commit();
                $stav = 'success';
                if ($akce === 'odmitnout') {
                    $nadpis = 'Údaje byly odstraněny';
                    $zprava = 'Děkujeme za upozornění. Dotazník byl vyřazen a údaje nebudou použity pro nábor.';
                } else {
                    $nadpis = 'Dotazník byl ověřen';
                    $zprava = 'Děkujeme. Vaše údaje byly potvrzeny a dotazník je zařazený do náboru.';
                }
            } else {
                $db->rollback();
            }
        } else {
            $db->rollback();
        }
    } catch (Throwable $e) {
        $db->rollback();
        error_log('Overeni VD - chyba: ' . $e->getMessage());
        $zprava = 'Při ověření nastala chyba. Zkuste odkaz otevřít později.';
    }
}
?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($nadpis) ?> | Pizza Comeback</title>
    <link rel="stylesheet" href="dot_assets/dotaznik.css">
</head>
<body>
<div class="page">
    <main class="form-column">
        <section class="success-card">
            <div class="success-icon"><?= $stav === 'success' ? '✓' : '!' ?></div>
            <h2><?= e($nadpis) ?></h2>
            <p><?= e($zprava) ?></p>
            <a class="button secondary" href="./">Zpět na dotazník</a>
        </section>
    </main>
</div>
</body>
</html>
