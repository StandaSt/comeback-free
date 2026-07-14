<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';
require __DIR__ . '/../../lib/app.php';

$aliasy = [
    291 => ['Hegedüs Erik'],
    408 => ['Pekárková Milena'],
    431 => ['Pechová Barbora'],
    385 => ['Lanský Zoltan'],
    234 => ['Rothová Anastasia'],
    577 => ['Chlubnová Adéla'],
    493 => ['Eszenyiová Patrícia'],
    464 => ['Jáklová Dominika'],
    469 => ['Šarközi Dávid'],
    1 => ['Roth Stanislav st.'],
    3 => ['Roth Stanislav ml.'],
    520 => ['Hegedüs Gergö'],
    147 => ['Chyský David'],
    37 => ['Hofmanová Jana', 'Jana Hofmanová'],
    604 => ['Bušek Pavel'],
    611 => ['Čonka Ludovít'],
];

function cbAliasList(?string $value): array
{
    $items = array_map('trim', explode('+!+', (string)$value));
    return array_values(array_unique(array_filter($items, static fn (string $item): bool => $item !== '')));
}

$db = db();
$db->set_charset('utf8mb4');
$db->begin_transaction();

try {
    $select = $db->prepare('SELECT alias FROM `user` WHERE id_user = ? LIMIT 1 FOR UPDATE');
    $update = $db->prepare('UPDATE `user` SET alias = ? WHERE id_user = ? LIMIT 1');
    if (!$select instanceof mysqli_stmt || !$update instanceof mysqli_stmt) {
        throw new RuntimeException('Nepodařilo se připravit SQL pro aliasy.');
    }

    $updated = 0;
    $missing = [];

    foreach ($aliasy as $idUser => $newAliases) {
        $idUser = (int)$idUser;
        $select->bind_param('i', $idUser);
        if (!$select->execute()) {
            throw new RuntimeException('SELECT alias selhal: ' . $select->error);
        }

        $result = $select->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->free();
        }

        if (!is_array($row)) {
            $missing[] = $idUser;
            continue;
        }

        $current = cbAliasList($row['alias'] ?? null);
        $merged = array_values(array_unique(array_merge($current, $newAliases)));
        $alias = implode('+!+', $merged);
        if ($alias === (string)($row['alias'] ?? '')) {
            continue;
        }

        $update->bind_param('si', $alias, $idUser);
        if (!$update->execute()) {
            throw new RuntimeException('UPDATE alias selhal: ' . $update->error);
        }
        $updated++;
    }

    $select->close();
    $update->close();
    $db->commit();

    echo json_encode(['ok' => 1, 'updated' => $updated, 'missing' => $missing], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    $db->rollback();
    echo json_encode(['ok' => 0, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}
