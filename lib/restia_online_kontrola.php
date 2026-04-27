<?php
// lib/restia_online_kontrola.php
// Verze: V3
// Aktualizace: 27.04.2026

function cb_restia_online_kontrola()
{
    $db = db(); // správné DB připojení

    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
    if ($idUser <= 0) {
        return;
    }

    // 1) běží něco?
    $q = $db->query("SELECT id_akce FROM online_restia WHERE aktivni = 1 LIMIT 1");
    if ($q && $q->num_rows > 0) {
        return;
    }

    // 2) poslední dokončený běh
    $q = $db->query("SELECT konec FROM online_restia WHERE aktivni = 0 ORDER BY konec DESC LIMIT 1");

    if ($q && $row = $q->fetch_assoc()) {
        if ($row['konec']) {
            $last = strtotime($row['konec']);
            if ((time() - $last) < 120) {
                return;
            }
        }
    }

    // 3) start
    $stmt = $db->prepare("
        INSERT INTO online_restia (id_user, start, aktivni)
        VALUES (?, NOW(), 1)
    ");
    $stmt->bind_param("i", $idUser);
    $stmt->execute();

    $id_akce = $stmt->insert_id;

    $zapisy = 0;
    $aktualizace = 0;

    try {

        $file = __DIR__ . '/restia_online.php';

        if (!file_exists($file)) {

            // log do txt
            file_put_contents(
                __DIR__ . '/../log/restia_online.txt',
                "\n\nSoubor nenalezen: restia_online.php\n",
                FILE_APPEND
            );

            // log do DB
            if (function_exists('zapis_log_chyby')) {
                zapis_log_chyby('Restia online: Soubor nenalezen restia_online.php');
            }

        } else {

            $result = include $file;

            if (is_array($result)) {
                $zapisy = isset($result['zapisy']) ? $result['zapisy'] : 0;
                $aktualizace = isset($result['aktualizace']) ? $result['aktualizace'] : 0;
            }
        }

    } catch (Throwable $e) {

        file_put_contents(
            __DIR__ . '/../log/restia_online.txt',
            "\n\nChyba: " . $e->getMessage() . "\n",
            FILE_APPEND
        );

        if (function_exists('zapis_log_chyby')) {
            zapis_log_chyby('Restia online chyba: ' . $e->getMessage());
        }
    }

    // 4) konec (vždy!)
    $stmt = $db->prepare("
        UPDATE online_restia
        SET konec = NOW(),
            zapisy = ?,
            aktualizace = ?,
            aktivni = 0
        WHERE id_akce = ?
    ");
    $stmt->bind_param("iii", $zapisy, $aktualizace, $id_akce);
    $stmt->execute();
}

// spuštění
cb_restia_online_kontrola();
