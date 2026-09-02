<?php
declare(strict_types=1);

function cb_ai_analytik_zablokovat_pravo(int $idUser): void
{
    if ($idUser <= 0) {
        throw new RuntimeException('Nelze určit uživatele pro bezpečnostní blokaci.');
    }

    $conn = db();
    $idPravo = CB_AI_ANALYTIK_PRAVO;
    $idFirma = 1;
    $povoleno = 0;
    $stmt = $conn->prepare(
        'INSERT INTO prava_vyjimky (id_user, id_pravo, id_firma, povoleno)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE id_firma = VALUES(id_firma), povoleno = VALUES(povoleno)'
    );
    $stmt->bind_param('iiii', $idUser, $idPravo, $idFirma, $povoleno);
    $stmt->execute();
    $stmt->close();

    unset($_SESSION['prava'][CB_AI_ANALYTIK_PRAVO]);
}
