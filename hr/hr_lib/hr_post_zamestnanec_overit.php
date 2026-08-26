<?php
declare(strict_types=1);

/* Zpracuje jednorazove overeni jedne karty zamestnance. */

function hr_post_zamestnanec_overit(mysqli $db): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        $stmt = $db->prepare('UPDATE hr_person SET overen = 1 WHERE id_person = ? AND aktivni = 1 AND overen = 0');
        $stmt->bind_param('i', $idPerson);
        $stmt->execute();
        $updated = $stmt->affected_rows;
        $stmt->close();
        if ($updated !== 1) {
            throw new RuntimeException('Kartu nelze ověřit nebo již byla ověřena.');
        }
        $_SESSION['hr_flash'] = ['type' => 'hr_success', 'text' => 'Zaměstnanec byl ověřen.'];
    } catch (Throwable $e) {
        $_SESSION['hr_flash'] = ['type' => 'hr_error', 'text' => $e->getMessage()];
    }

    header('Location: ' . cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson)), true, 303);
    exit;
}
