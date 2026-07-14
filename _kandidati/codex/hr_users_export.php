<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';
require __DIR__ . '/../../lib/app.php';

$sql = '
    SELECT
        u.id_user, u.jmeno, u.prijmeni, u.alias, u.email, u.aktivni,
        GROUP_CONCAT(DISTINCT up.id_pob ORDER BY up.id_pob SEPARATOR ",") AS pobocky
    FROM `user` u
    LEFT JOIN user_pobocka up ON up.id_user = u.id_user
    GROUP BY u.id_user, u.jmeno, u.prijmeni, u.alias, u.email, u.aktivni
    ORDER BY u.prijmeni ASC, u.jmeno ASC, u.id_user ASC
';

$res = db()->query($sql);
$rows = [];
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $pobocky = [];
        foreach (explode(',', (string)($row['pobocky'] ?? '')) as $idPob) {
            $idPob = (int)$idPob;
            if ($idPob > 0) {
                $pobocky[] = $idPob;
            }
        }

        $rows[] = [
            'id_user' => (int)($row['id_user'] ?? 0),
            'jmeno' => (string)($row['jmeno'] ?? ''),
            'prijmeni' => (string)($row['prijmeni'] ?? ''),
            'alias' => (string)($row['alias'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'aktivni' => (int)($row['aktivni'] ?? 0),
            'pobocky' => $pobocky,
        ];
    }
    $res->free();
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
