<?php
// admin_testy/00_restia_smazat_data.php * Verze: V2 * Aktualizace: 01.04.2026
declare(strict_types=1);

/*
CO TENHLE SCRIPT DĚLÁ

- zobrazí formulář pro smazání Restia dat
- nabídne 3 režimy:
  1) Restia komplet
  2) Objednávky a vše co k nim patří
  3) Menu
- smaže data jen po potvrzení přes POST
- maže ve správném pořadí kvůli vazbám (FK = cizí klíče)
- po smazání nastaví AUTO_INCREMENT zpět na 1
- nic nestahuje z Restie
- nic nemění mimo Restia tabulky

*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/system.php';
require_once __DIR__ . '/../config/secrets.php';

if (!function_exists('cb_restia_wipe_groups')) {
    function cb_restia_wipe_groups(): array
    {
        return [
            'restia_komplet' => [
                'label' => 'Restia komplet',
                'confirm' => 'Opravdu smazat všechna Restia data včetně objednávek a menu?',
                'tables' => [
                    'obj_polozka_kds_tag',
                    'obj_polozka_mod',
                    'obj_polozky',
                    'obj_kuryr',
                    'obj_sluzba',
                    'obj_adresa',
                    'obj_ceny',
                    'obj_casy',
                    'obj_raw',
                    'obj_import',
                    'objednavky_restia',
                    'res_alergen',
                    'res_cena',
                    'res_polozky',
                    'res_kategorie',
                ],
            ],
            'objednavky' => [
                'label' => 'Objednávky (a vše co k nim patří)',
                'confirm' => 'Opravdu smazat všechny Restia objednávky a navázaná data?',
                'tables' => [
                    'obj_polozka_kds_tag',
                    'obj_polozka_mod',
                    'obj_polozky',
                    'obj_kuryr',
                    'obj_sluzba',
                    'obj_adresa',
                    'obj_ceny',
                    'obj_casy',
                    'obj_raw',
                    'obj_import',
                    'objednavky_restia',
                ],
            ],
            'menu' => [
                'label' => 'Menu',
                'confirm' => 'Opravdu smazat celé Restia menu?',
                'tables' => [
                    'res_alergen',
                    'res_cena',
                    'res_polozky',
                    'res_kategorie',
                ],
            ],
        ];
    }
}

if (!function_exists('cb_restia_wipe_allowed')) {
    function cb_restia_wipe_allowed(string $key): bool
    {
        $groups = cb_restia_wipe_groups();
        return isset($groups[$key]);
    }
}

if (!function_exists('cb_restia_wipe_table_exists')) {
    function cb_restia_wipe_table_exists(mysqli $conn, string $table): bool
    {
        $sql = "
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Nepodařilo se připravit kontrolu tabulky ' . $table . '.');
        }

        $stmt->bind_param('s', $table);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = ($res instanceof mysqli_result) ? ($res->fetch_row() !== null) : false;

        if ($res instanceof mysqli_result) {
            $res->free();
        }

        $stmt->close();
        return $exists;
    }
}

if (!function_exists('cb_restia_wipe_delete_table')) {
    function cb_restia_wipe_delete_table(mysqli $conn, string $table): int
    {
        $sql = 'DELETE FROM `' . str_replace('`', '``', $table) . '`';
        $ok = $conn->query($sql);

        if ($ok === false) {
            throw new RuntimeException('DELETE selhal pro tabulku ' . $table . ': ' . $conn->error);
        }

        return (int)$conn->affected_rows;
    }
}

if (!function_exists('cb_restia_wipe_reset_ai')) {
    function cb_restia_wipe_reset_ai(mysqli $conn, string $table): void
    {
        $sql = 'ALTER TABLE `' . str_replace('`', '``', $table) . '` AUTO_INCREMENT = 1';
        $ok = $conn->query($sql);

        if ($ok === false) {
            throw new RuntimeException('AUTO_INCREMENT reset selhal pro tabulku ' . $table . ': ' . $conn->error);
        }
    }
}

if (!function_exists('cb_restia_wipe_run')) {
    function cb_restia_wipe_run(mysqli $conn, string $groupKey): array
    {
        $groups = cb_restia_wipe_groups();

        if (!isset($groups[$groupKey])) {
            throw new RuntimeException('Neplatná volba mazání.');
        }

        $tables = $groups[$groupKey]['tables'];
        $deleted = [];
        $skipped = [];

        $conn->begin_transaction();

        try {
            foreach ($tables as $table) {
                if (!cb_restia_wipe_table_exists($conn, $table)) {
                    $skipped[] = $table;
                    continue;
                }

                $rows = cb_restia_wipe_delete_table($conn, $table);
                $deleted[$table] = $rows;
                cb_restia_wipe_reset_ai($conn, $table);
            }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
        ];
    }
}

$groups = cb_restia_wipe_groups();
$conn = db();

$selected = trim((string)($_POST['cb_restia_wipe_group'] ?? ''));
$confirm = trim((string)($_POST['cb_restia_wipe_confirm'] ?? ''));
$wasPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

$msgOk = '';
$msgErr = '';
$result = null;

if ($selected === '' && isset($groups['restia_komplet'])) {
    $selected = 'restia_komplet';
}

if ($wasPost) {
    try {
        if (!cb_restia_wipe_allowed($selected)) {
            throw new RuntimeException('Nebyla vybraná platná varianta mazání.');
        }

        if ($confirm !== 'ANO') {
            throw new RuntimeException('Pro spuštění musíš do potvrzení napsat přesně ANO.');
        }

        $result = cb_restia_wipe_run($conn, $selected);
        $msgOk = 'Mazání proběhlo OK.';
    } catch (Throwable $e) {
        $msgErr = $e->getMessage();
    }
}
?>

<div class="wrap">

  <div class="box">
    <h1>Restia - smazat data</h1>
    <p>Vyber co chceš smazat. Mazání se provede až po potvrzení přes <code>ANO</code>.</p>
  </div>

  <div class="box">
    <form method="post">
      <div class="row">
        <label for="cb_restia_wipe_group">Co smazat</label>
        <select name="cb_restia_wipe_group" id="cb_restia_wipe_group">
          <?php foreach ($groups as $key => $cfg): ?>
            <option value="<?= h($key) ?>"<?= $selected === $key ? ' selected' : '' ?>><?= h($cfg['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (isset($groups[$selected])): ?>
        <div class="row">
          <p class="warn"><?= h((string)$groups[$selected]['confirm']) ?></p>
        </div>
      <?php endif; ?>

      <div class="row">
        <label for="cb_restia_wipe_confirm">Potvrzení</label>
        <input type="text" name="cb_restia_wipe_confirm" id="cb_restia_wipe_confirm" value="" placeholder="Napiš ANO">
      </div>

      <div class="row">
        <button type="submit">Smazat data</button>
      </div>
    </form>
  </div>

  <?php if ($msgErr !== ''): ?>
    <div class="box">
      <p class="err">CHYBA: <?= h($msgErr) ?></p>
    </div>
  <?php endif; ?>

  <?php if ($msgOk !== '' && is_array($result)): ?>
    <div class="box">
      <p class="ok"><?= h($msgOk) ?></p>
    </div>

    <div class="box">
      <h2>Smazané tabulky</h2>
      <table>
        <thead>
          <tr>
            <th>Tabulka</th>
            <th>Smazaných řádků</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($result['deleted'] ?? []) as $table => $rows): ?>
            <tr>
              <td><?= h((string)$table) ?></td>
              <td><?= h((string)$rows) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (($result['deleted'] ?? []) === []): ?>
            <tr>
              <td colspan="2">Nic se nemaželo.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (($result['skipped'] ?? []) !== []): ?>
      <div class="box">
        <h2>Přeskočené tabulky</h2>
        <p class="small"><?= h(implode(', ', (array)$result['skipped'])) ?></p>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="box">
    <h2>Co maže která volba</h2>
    <?php foreach ($groups as $cfg): ?>
      <p><strong><?= h((string)$cfg['label']) ?></strong></p>
      <p class="small"><?= h(implode(', ', (array)$cfg['tables'])) ?></p>
    <?php endforeach; ?>
  </div>

</div>
<?php
// admin_testy/00_restia_smazat_data.php * Verze: V2 * Aktualizace: 01.04.2026
// Počet řádků: 277
// Konec souboru
?>