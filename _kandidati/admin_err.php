<?php
// pages/admin_err.php V8 – počet řádků: 197 – aktuální čas v ČR: 19.1.2026 15:25
declare(strict_types=1);

/*
 * OBSAH STRÁNKY – Admin sekce
 * - admin rozcestník + přehled chyb
 * - přístup: jen uživatel s pozice < 5
 * - zobrazené chyby: stav 0 -> 1 (přečteno)
 * - vyřešení: ručně (stav 2)
 */

require_once __DIR__ . '/../lib/bootstrap.php';

// ===== přístup: jen pozice < 5 =====
$cbUser   = $_SESSION['cb_user'] ?? null;
$cbIdUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

$jeAdmin = false;
if ($cbIdUser > 0) {
    try {
        $conn = db();
        $stmt = $conn->prepare('SELECT pozice FROM `user` WHERE id_user = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $cbIdUser);
            $stmt->execute();
            $stmt->bind_result($pozice);
            if ($stmt->fetch()) {
                $jeAdmin = ((int)$pozice < 5);
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        $jeAdmin = false;
    }
}

// echo '<div class="page-head"><h2>Admin err</h2></div>';

if (!$jeAdmin) {
    echo '<section class="card"><p>Nemáš oprávnění.</p></section>';
    return;
}

$sekce = (string)($_GET['a'] ?? '');
if (!in_array($sekce, ['', 'chyby'], true)) $sekce = '';

?>
<section class="card">

    <div class="admin-top" style="display:flex; gap:12px; align-items:center;">
        <select class="filter-input" onchange="if (this.value) window.location.href = this.value;">
            <option value="">Přehledy</option>
            <option value="<?= h(cb_url('index.php?page=uvod')) ?>">Úvod</option>
            <option value="<?= h(cb_url('index.php?page=report')) ?>">Report</option>
            <option value="<?= h(cb_url('index.php?page=prehledy')) ?>">Přehledy</option>
            <option value="<?= h(cb_url('index.php?page=statistiky')) ?>">Statistiky</option>
            <option value="<?= h(cb_url('index.php?page=data')) ?>">Data</option>
            <option value="<?= h(cb_url('index.php?page=admin_sekce&a=chyby')) ?>">Chyby</option>
        </select>

        <select class="filter-input" onchange="if (this.value) window.location.href = this.value;">
            <option value="">Úpravy</option>
            <option value="<?= h(cb_url('index.php?page=uzivatele')) ?>">Uživatelé</option>
            <option value="<?= h(cb_url('index.php?page=zakaznici')) ?>">Zákazníci</option>
        </select>
    </div>

<?php
// default: pokud jsou nové chyby, otevři rovnou chyby
try {
    $conn = db();
    $stmt = $conn->prepare('SELECT COUNT(*) FROM chyba WHERE stav = 0');
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($newCnt);
        $stmt->fetch();
        $stmt->close();
        if ($sekce === '' && (int)$newCnt > 0) $sekce = 'chyby';
    }
} catch (Throwable $e) {
}

// zpracování "vyřešeno"
if ($sekce === 'chyby' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ids = $_POST['vyresit'] ?? [];
    if (is_array($ids) && $ids) {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($x) => $x > 0));
        if ($ids) {
            try {
                $conn = db();
                $place = implode(',', array_fill(0, count($ids), '?'));
                $sql = 'UPDATE chyba SET stav = 2, stav_kdy = NOW(), stav_id_user = ? WHERE id_chyba IN (' . $place . ')';
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $types = 'i' . str_repeat('i', count($ids));
                    $params = array_merge([$cbIdUser], $ids);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $stmt->close();
                }
            } catch (Throwable $e) {
            }
        }
    }
}

// výpis chyb
if ($sekce === 'chyby') {

    // při zobrazení označit nové jako přečtené
    try {
        $conn = db();
        $conn->query('UPDATE chyba SET stav = 1, stav_kdy = NOW(), stav_id_user = NULL WHERE stav = 0');
    } catch (Throwable $e) {
    }

    $rows = [];
    try {
        $conn = db();
        $sql = '
            SELECT id_chyba, kdy, zavaznost, oblast, kod, page, url, zprava, stav
            FROM chyba
            ORDER BY kdy DESC
            LIMIT 200
        ';
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $res->free();
        }
    } catch (Throwable $e) {
        $rows = [];
    }

    echo '<h3 class="admin-h3">Chyby (posledních 200)</h3>';

    if (!$rows) {
        echo '<p>Žádné chyby.</p>';
    } else {
        echo '<form method="post" action="' . h(cb_url('index.php?page=admin_sekce&a=chyby')) . '">';
        echo '<div class="table-wrap">';
        echo '<table class="table table-fixed admin-chyby">';
        echo '<thead><tr>';
        echo '<th class="c-vyresit">OK</th>';
        echo '<th class="c-kdy">kdy</th>';
        echo '<th class="c-stav">stav</th>';
        echo '<th class="c-zav">záv</th>';
        echo '<th class="c-obl">oblast</th>';
        echo '<th class="c-kod">kód</th>';
        echo '<th class="c-page">page</th>';
        echo '<th class="c-url">url</th>';
        echo '<th class="c-zpr">zpráva</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $id   = (int)($r['id_chyba'] ?? 0);
            $kdy  = (string)($r['kdy'] ?? '');
            $stav = (int)($r['stav'] ?? 0);

            $stavTxt = ($stav === 2) ? 'vyřešeno' : (($stav === 1) ? 'přečteno' : 'nové');

            echo '<tr>';
            echo '<td class="c-vyresit">';
            if ($stav !== 2) {
                echo '<input type="checkbox" name="vyresit[]" value="' . $id . '">';
            } else {
                echo '—';
            }
            echo '</td>';

            echo '<td class="c-kdy">' . h($kdy) . '</td>';
            echo '<td class="c-stav">' . h($stavTxt) . '</td>';
            echo '<td class="c-zav">' . h((string)($r['zavaznost'] ?? '')) . '</td>';
            echo '<td class="c-obl">' . h((string)($r['oblast'] ?? '')) . '</td>';
            echo '<td class="c-kod">' . h((string)($r['kod'] ?? '')) . '</td>';
            echo '<td class="c-page">' . h((string)($r['page'] ?? '')) . '</td>';
            echo '<td class="c-url">' . h((string)($r['url'] ?? '')) . '</td>';
            echo '<td class="c-zpr">' . h((string)($r['zprava'] ?? '')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
        echo '<div class="admin-actions">';
        echo '<button type="submit" class="btn">Označit vyřešené</button>';
        echo '<span class="admin-hint">Zaškrtej jen to, co je opravdu vyřešené.</span>';
        echo '</div>';
        echo '</form>';
    }
}
?>
</section>

<?php
/* pages/admin_err.php V8 – počet řádků: 197 – aktuální čas v ČR: 19.1.2026 15:25 */
