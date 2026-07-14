<?php
// inicializace/plnime_smeny_user.php * Verze: V2 * Aktualizace: 18.06.2026

declare(strict_types=1);

require_once __DIR__ . '/../lib/session_boot.php';

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/smeny_graphql.php';
require_once __DIR__ . '/../db/db_user.php';
require_once __DIR__ . '/../db/db_user_set_pobocka.php';
require_once __DIR__ . '/../db/db_user_role.php';
require_once __DIR__ . '/../db/db_user_slot.php';

const SMENY_USER_GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';
const SMENY_USER_PAGE_LIMIT = 100;
const SMENY_USER_LOCK_NAME = 'cb_plnime_smeny_user';

if (smenyUserIsCron()) {
    smenyUserRun();
} elseif (isset($_POST['run_smeny_user']) && (string)$_POST['run_smeny_user'] === '1') {
    smenyUserRun();
} else {
    smenyUserPreview();
}

function smenyUserIsCron(): bool
{
    return !empty($GLOBALS['cb_smeny_user_cron']);
}

function smenyUserH(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function smenyUserPreview(): void
{
    $stats = smenyUserDbStats();
    ?>
    <div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
      <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Inicializace uživatelů Směny</h2>
      <p class="card_text txt_seda">Script stáhne uživatele ze Směn a naplní tabulky uživatelů, poboček, rolí a slotů.</p>
      <p class="card_text txt_seda">Aktuálně v DB: <?= smenyUserH(number_format($stats['user'], 0, ',', ' ')) ?> uživatelů, <?= smenyUserH(number_format($stats['user_pobocka'], 0, ',', ' ')) ?> vazeb na pobočky, <?= smenyUserH(number_format($stats['user_role'], 0, ',', ' ')) ?> rolí, <?= smenyUserH(number_format($stats['user_slot'], 0, ',', ' ')) ?> slotů.</p>

      <div class="card_actions gap_8 displ_flex odstup_horni_10">
        <form method="post" action="<?= smenyUserH(cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1" data-cb-loader-text="Probíhá import uživatelů">
          <input type="hidden" name="run_smeny_user" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" data-cb-loader-text="Probíhá import uživatelů">Spustit import</button>
        </form>
        <form method="post" action="<?= smenyUserH(cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1">
          <input type="hidden" name="back_admin_init" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 displ_inline_flex">Zpět</button>
        </form>
      </div>
    </div>
    <?php
}

function smenyUserRun(): void
{
    set_time_limit(0);

    $token = (string)($_SESSION['cb_token'] ?? '');
    if ($token === '') {
        smenyUserResult('Chyba: chybí token Směn v session.', []);
        return;
    }

    $db = db();
    $db->set_charset('utf8mb4');

    $insertedOrUpdated = 0;
    $errors = 0;
    $roleAdd = 0;
    $roleDel = 0;
    $slotAdd = 0;
    $slotDel = 0;
    $offset = 0;
    $total = null;
    $activeUserIds = [];
    $lockAcquired = false;
    $aktualizaceId = 0;
    $fatalError = '';

    try {
        $lockAcquired = smenyUserAcquireLock($db);
        if (!$lockAcquired) {
            smenyUserResult('Import uživatelů už právě běží.', []);
            return;
        }

        $aktualizaceId = smenyUserStartAktualizace($db);

        do {
            try {
                $page = smenyUserFetchPage($token, SMENY_USER_PAGE_LIMIT, $offset);
            } catch (Throwable $e) {
                throw new RuntimeException('Chyba při stažení uživatelů ze Směn: ' . $e->getMessage(), 0, $e);
            }

            $items = $page['items'];
            $total = $page['total'];

            foreach ($items as $profile) {
                if (!is_array($profile) || (int)($profile['id'] ?? 0) <= 0) {
                    continue;
                }

                $idUser = (int)$profile['id'];
                $activeUserIds[$idUser] = $idUser;
                $working = $profile['workingBranchNames'] ?? [];
                if (!is_array($working)) {
                    $working = [];
                }
                $main = trim((string)($profile['mainBranchName'] ?? ''));

                $db->begin_transaction();
                try {
                    cb_db_upsert_user($db, $profile, false);
                    cb_db_set_user_pobocka($db, $idUser, $working, $main !== '' ? $main : null);
                    $roles = db_user_role_sync($db, $idUser, $profile, false);
                    $slots = db_user_slot_sync($db, $idUser, $profile);
                    $db->commit();

                    $insertedOrUpdated++;
                    $roleAdd += (int)($roles['add'] ?? 0);
                    $roleDel += (int)($roles['del'] ?? 0);
                    $slotAdd += (int)($slots['add'] ?? 0);
                    $slotDel += (int)($slots['del'] ?? 0);
                } catch (Throwable $e) {
                    $db->rollback();
                    $errors++;
                }
            }

            $offset += SMENY_USER_PAGE_LIMIT;
        } while ($total !== null && $offset < $total);

        smenyUserDeactivateMissingActiveUsers($db, array_values($activeUserIds));
    } catch (Throwable $e) {
        $fatalError = trim($e->getMessage());
    } finally {
        if ($aktualizaceId > 0) {
            smenyUserFinishAktualizace($db, $aktualizaceId, [
                'stazeno' => (int)($total ?? 0),
                'ulozeno_aktualizovano' => $insertedOrUpdated,
                'role_pridano' => $roleAdd,
                'role_odebrano' => $roleDel,
                'sloty_pridano' => $slotAdd,
                'sloty_odebrano' => $slotDel,
                'chyby' => $errors,
                'chyba_text' => $fatalError,
            ]);
        }
        if ($lockAcquired) {
            smenyUserReleaseLock($db);
        }
    }

    if ($fatalError !== '') {
        smenyUserResult($fatalError, []);
        return;
    }

    smenyUserResult('Import uživatelů dokončen', [
        'Staženo ze Směn' => (int)($total ?? 0),
        'Uloženo / aktualizováno' => $insertedOrUpdated,
        'Role přidáno' => $roleAdd,
        'Role odebráno' => $roleDel,
        'Sloty přidáno' => $slotAdd,
        'Sloty odebráno' => $slotDel,
        'Chyby' => $errors,
    ]);
}

function smenyUserAcquireLock(mysqli $db): bool
{
    $stmt = $db->prepare('SELECT GET_LOCK(?, 0)');
    if ($stmt === false) {
        throw new RuntimeException('DB: prepare selhal (GET_LOCK user import).');
    }

    $lockName = SMENY_USER_LOCK_NAME;
    $stmt->bind_param('s', $lockName);
    $stmt->execute();
    $stmt->bind_result($locked);
    $ok = $stmt->fetch() && (int)$locked === 1;
    $stmt->close();

    return $ok;
}

function smenyUserReleaseLock(mysqli $db): void
{
    $stmt = $db->prepare('DO RELEASE_LOCK(?)');
    if ($stmt === false) {
        return;
    }

    $lockName = SMENY_USER_LOCK_NAME;
    $stmt->bind_param('s', $lockName);
    $stmt->execute();
    $stmt->close();
}

function smenyUserStartAktualizace(mysqli $db): int
{
    $stmt = $db->prepare(
        'INSERT INTO user_aktualizace (
            spusteno, dokonceno, stav, stazeno, ulozeno_aktualizovano,
            role_pridano, role_odebrano, sloty_pridano, sloty_odebrano, chyby, chyba_text
        ) VALUES (NOW(), NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL)'
    );
    if ($stmt === false) {
        throw new RuntimeException('DB: prepare selhal (user_aktualizace insert).');
    }

    if (!$stmt->execute()) {
        $msg = $stmt->error;
        $stmt->close();
        throw new RuntimeException('INSERT user_aktualizace selhal: ' . $msg);
    }

    $id = (int)$stmt->insert_id;
    $stmt->close();

    if ($id <= 0) {
        throw new RuntimeException('INSERT user_aktualizace nevratil ID.');
    }

    return $id;
}

function smenyUserFinishAktualizace(mysqli $db, int $idAktualizace, array $stats): void
{
    if ($idAktualizace <= 0) {
        return;
    }

    $chyby = max(0, (int)($stats['chyby'] ?? 0));
    $chybaText = trim((string)($stats['chyba_text'] ?? ''));
    if ($chybaText === '' && $chyby === 0) {
        $chybaText = null;
    } elseif ($chybaText === '' && $chyby > 0) {
        $chybaText = 'Import skončil s chybami.';
    }

    $stazeno = max(0, (int)($stats['stazeno'] ?? 0));
    $ulozeno = max(0, (int)($stats['ulozeno_aktualizovano'] ?? 0));
    $rolePridano = max(0, (int)($stats['role_pridano'] ?? 0));
    $roleOdebrano = max(0, (int)($stats['role_odebrano'] ?? 0));
    $slotyPridano = max(0, (int)($stats['sloty_pridano'] ?? 0));
    $slotyOdebrano = max(0, (int)($stats['sloty_odebrano'] ?? 0));

    $stmt = $db->prepare(
        'UPDATE user_aktualizace
         SET dokonceno = NOW(),
             stav = 1,
             stazeno = ?,
             ulozeno_aktualizovano = ?,
             role_pridano = ?,
             role_odebrano = ?,
             sloty_pridano = ?,
             sloty_odebrano = ?,
             chyby = ?,
             chyba_text = ?
         WHERE id_user_aktualizace = ?'
    );
    if ($stmt === false) {
        return;
    }

    $stmt->bind_param(
        'iiiiiiisi',
        $stazeno,
        $ulozeno,
        $rolePridano,
        $roleOdebrano,
        $slotyPridano,
        $slotyOdebrano,
        $chyby,
        $chybaText,
        $idAktualizace
    );
    $stmt->execute();
    $stmt->close();
}

function smenyUserFetchPage(string $token, int $limit, int $offset): array
{
    $data = cb_smeny_graphql(
        SMENY_USER_GQL_URL,
        'query($limit:Int!, $offset:Int!, $filter: UserFilterArg){
            userPaginate{
                totalCount(filter:$filter)
                items(limit:$limit, offset:$offset, filter:$filter){
                    id
                    name
                    surname
                    email
                    phoneNumber
                    active
                    approved
                    createTime
                    lastLoginTime
                    roles{ id name }
                    shiftRoleTypeNames
                    workingBranchNames
                    mainBranchName
                }
            }
        }',
        [
            'limit' => $limit,
            'offset' => $offset,
            'filter' => [
                'active' => [true],
            ],
        ],
        $token,
        60
    );

    $root = $data['userPaginate'] ?? null;
    if (!is_array($root)) {
        throw new RuntimeException('Směny nevrátily userPaginate.');
    }

    $items = $root['items'] ?? [];
    if (!is_array($items)) {
        $items = [];
    }

    return [
        'total' => (int)($root['totalCount'] ?? count($items)),
        'items' => $items,
    ];
}

function smenyUserDeactivateMissingActiveUsers(mysqli $db, array $activeUserIds): void
{
    $activeUserIds = array_values(array_unique(array_map('intval', $activeUserIds)));
    $activeUserIds = array_values(array_filter($activeUserIds, static fn (int $id): bool => $id > 0));

    if ($activeUserIds === []) {
        $stmt = $db->prepare('UPDATE user SET aktivni = 0 WHERE aktivni = 1');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (deaktivace vsech useru).');
        }
        if (!$stmt->execute()) {
            $msg = $stmt->error;
            $stmt->close();
            throw new RuntimeException('UPDATE user aktivni=0 selhal: ' . $msg);
        }
        $stmt->close();
        return;
    }

    $placeholders = implode(',', array_fill(0, count($activeUserIds), '?'));
    $sql = 'UPDATE user SET aktivni = 0 WHERE aktivni = 1 AND id_user NOT IN (' . $placeholders . ')';
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('DB: prepare selhal (deaktivace nepritomnych useru).');
    }

    $types = str_repeat('i', count($activeUserIds));
    $bind = [];
    $bind[] = &$types;
    foreach ($activeUserIds as $idx => $idUser) {
        $activeUserIds[$idx] = (int)$idUser;
        $bind[] = &$activeUserIds[$idx];
    }

    if (!call_user_func_array([$stmt, 'bind_param'], $bind)) {
        $stmt->close();
        throw new RuntimeException('DB: bind_param selhal (deaktivace nepritomnych useru).');
    }

    if (!$stmt->execute()) {
        $msg = $stmt->error;
        $stmt->close();
        throw new RuntimeException('UPDATE user aktivni=0 nepritomni selhal: ' . $msg);
    }

    $stmt->close();
}

function smenyUserDbStats(): array
{
    $db = db();
    $out = [];
    foreach (['user', 'user_pobocka', 'user_role', 'user_slot'] as $table) {
        $res = $db->query('SELECT COUNT(*) AS cnt FROM `' . str_replace('`', '``', $table) . '`');
        $row = $res instanceof mysqli_result ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $out[$table] = (int)($row['cnt'] ?? 0);
    }
    return $out;
}

function smenyUserResult(string $title, array $rows): void
{
    if (smenyUserIsCron()) {
        return;
    }

    ?>
    <div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
      <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0"><?= smenyUserH($title) ?></h2>
      <?php if ($rows !== []): ?>
        <table class="table ram_normal bg_bila radek_1_35 sirka100">
          <tbody>
            <?php foreach ($rows as $label => $value): ?>
              <tr>
                <td><?= smenyUserH((string)$label) ?></td>
                <td class="txt_r"><strong><?= smenyUserH(number_format((int)$value, 0, ',', ' ')) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <div class="card_actions gap_8 displ_flex odstup_horni_10">
        <form method="post" action="<?= smenyUserH(cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1">
          <input type="hidden" name="back_admin_init" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 displ_inline_flex">Zpět</button>
        </form>
      </div>
    </div>
    <?php
}

// inicializace/plnime_smeny_user.php * Verze: V2 * Aktualizace: 18.06.2026
