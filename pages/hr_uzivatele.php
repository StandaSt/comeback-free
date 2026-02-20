<?php
// pages/hr_uzivatele.php * Verze: V11 * Aktualizace: 20.2.2026 * Počet řádků: 483
declare(strict_types=1);

/*
 * Uživatelé
 * - bez hlavičky/patičky (řeší index.php)
 * - bez mysqli::get_result() (kvůli hostingu)
 * - TRACE režim: ?page=hr_uzivatele&trace=1
 *
 * Pozn.: email + telefon jsou přímo v tabulce `user` (1 email, 1 telefon).
 */

require_once __DIR__ . '/../lib/bootstrap.php';

$TRACE = (isset($_GET['trace']) && $_GET['trace'] === '1');

/* ===== TRACE ===== */
if ($TRACE) {
    @ini_set('display_errors', '0');
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) { @ob_end_flush(); }
    @ob_implicit_flush(true);

    // v TRACE režimu chceme chyby z mysqli jako výjimky (snadná diagnostika)
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

/* ===== helpers ===== */
function bt(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}
function qcol(string $alias, string $col): string {
    return bt($alias) . '.' . bt($col);
}
function clampInt($v, int $min, int $max, int $default): int {
    $i = filter_var($v, FILTER_VALIDATE_INT);
    if ($i === false) return $default;
    return max($min, min($max, $i));
}

/* formát telefonu – jen pro zobrazení */
function fmt_tel(string $v): string {
    $v = trim($v);
    if ($v === '') return '';

    $raw = preg_replace('~[^\d\+]~u', '', $v);
    if ($raw === null) $raw = $v;

    if (preg_match('~^\+(\d{1,3})(\d{9})$~', $raw, $m)) {
        $cc = $m[1];
        $n  = $m[2];
        return '+' . $cc . ' ' . substr($n, 0, 3) . ' ' . substr($n, 3, 3) . ' ' . substr($n, 6, 3);
    }

    if (preg_match('~^\d{9}$~', $raw)) {
        return substr($raw, 0, 3) . ' ' . substr($raw, 3, 3) . ' ' . substr($raw, 6, 3);
    }

    $digits = preg_replace('~\D~u', '', $raw);
    if ($digits === null) $digits = $raw;

    if (strlen($digits) <= 6) return $digits;
    return trim(substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6));
}

function stmtExec(mysqli $conn, string $sql, array $params = [], string $types = ''): mysqli_stmt {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('Nepodařilo se připravit SQL dotaz.');
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}

function stmtFetchAllAssoc(mysqli_stmt $stmt): array {
    $rows = [];
    $meta = $stmt->result_metadata();
    if (!$meta) return $rows;

    $fields = $meta->fetch_fields();
    $row = [];
    $bind = [];

    foreach ($fields as $f) {
        $row[$f->name] = null;
        $bind[] = &$row[$f->name];
    }

    $stmt->bind_result(...$bind);

    while ($stmt->fetch()) {
        $copy = [];
        foreach ($row as $k => $v) $copy[$k] = $v;
        $rows[] = $copy;
    }
    return $rows;
}

function build_url(array $base, array $override = []): string {
    $q = $base;
    foreach ($override as $k => $v) {
        if ($v === null) unset($q[$k]);
        else $q[$k] = $v;
    }
    return '?' . http_build_query($q);
}

/* logger (do /log/error.log) */
function log_error(Throwable $e): void {
    $dir = __DIR__ . '/../log';
    @mkdir($dir, 0775, true);
    $file = $dir . '/error.log';

    $line =
        '[' . date('Y-m-d H:i:s') . '] ' .
        get_class($e) . ': ' . $e->getMessage() .
        ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL .
        $e->getTraceAsString() . PHP_EOL .
        str_repeat('-', 80) . PHP_EOL;

    @file_put_contents($file, $line, FILE_APPEND);
}

/* TRACE výpis (bez inline stylů) */
function trace_msg(bool $TRACE, string $msg): void {
    if (!$TRACE) return;
    echo '<div class="trace-msg">' . h($msg) . '</div>';
    @flush();
}

/* ===== VSTUPY ===== */
$per  = clampInt($_GET['per'] ?? 20, 20, 100, 20);
$page = clampInt($_GET['p'] ?? 1, 1, 1_000_000, 1);
$filters = is_array($_GET['f'] ?? null) ? $_GET['f'] : [];

/* aktivní filtr: 1=jen aktivní (default), 0=jen neaktivní, all=vše */
$akt = (string)($_GET['akt'] ?? '1');
if (!in_array($akt, ['1', '0', 'all'], true)) $akt = '1';

/* ===== KONFIG ZOBRAZENÍ ===== */
$cols = [
    'id' => [
        'label' => 'Poř.č.',
        'filter' => 'eq_int',
        'sql' => qcol('u', 'id_user'),
        'alias' => 'id_user',
        'fmt' => null,
    ],
    'prijmeni' => [
        'label' => 'příjmení',
        'filter' => 'like',
        'sql' => qcol('u', 'prijmeni'),
        'alias' => 'prijmeni',
        'fmt' => null,
    ],
    'jmeno' => [
        'label' => 'jméno',
        'filter' => 'like',
        'sql' => qcol('u', 'jmeno'),
        'alias' => 'jmeno',
        'fmt' => null,
    ],
    'telefon' => [
        'label' => 'telefon',
        'filter' => 'like',
        'sql' => "COALESCE(" . qcol('u', 'telefon') . ", '')",
        'alias' => 'telefon',
        'fmt' => function(mixed $v): string {
            $sv = trim((string)$v);
            if ($sv === '') return '-';
            return fmt_tel($sv);
        },
    ],
    'email' => [
        'label' => 'email',
        'filter' => 'like',
        'sql' => "COALESCE(" . qcol('u', 'email') . ", '')",
        'alias' => 'email',
        'fmt' => null,
    ],
    'reg' => [
        'label' => 'registrován',
        'filter' => false,
        'sql' => qcol('u', 'vytvoren_smeny'),
        'alias' => 'reg',
        'fmt' => null,
    ],
    'aktivni' => [
        'label' => 'aktivní',
        'filter' => false,
        'sql' => qcol('u', 'aktivni'),
        'alias' => 'aktivni',
        'fmt' => function(mixed $v): string {
            if ((string)$v === '1') return 'Ano';
            return 'Ne';
        },
    ],
    'akce' => [
        'label' => 'detaily uživatele',
        'filter' => false,
        'sql' => "''",
        'alias' => 'akce',
        'fmt' => null,
    ],
];

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    /* ===== FROM ===== */
    $fromSql = bt('user') . ' ' . bt('u');

    /* ===== WHERE ===== */
    $params = [];
    $types  = '';
    $where  = [];

    if ($akt !== 'all') {
        $where[]  = qcol('u', 'aktivni') . ' = ?';
        $params[] = (int)$akt;
        $types   .= 'i';
    }

    foreach ($cols as $key => $c) {
        $ft = $c['filter'] ?? false;
        if ($ft === false) continue;

        $raw = trim((string)($filters[$key] ?? ''));
        if ($raw === '') continue;

        if ($ft === 'eq_int') {
            $i = filter_var($raw, FILTER_VALIDATE_INT);
            if ($i === false) continue;
            $where[]  = ($c['sql']) . ' = ?';
            $params[] = (int)$i;
            $types   .= 'i';
            continue;
        }

        if ($ft === 'like') {
            $where[]  = ($c['sql']) . ' LIKE ?';
            $params[] = '%' . $raw . '%';
            $types   .= 's';
            continue;
        }
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    /* ===== COUNT ===== */
    $stmtCnt = stmtExec($conn, 'SELECT COUNT(*) FROM ' . $fromSql . $whereSql, $params, $types);
    $stmtCnt->bind_result($total);
    $stmtCnt->fetch();
    $stmtCnt->close();

    $pages  = max(1, (int)ceil((int)$total / $per));
    $page   = min($page, $pages);
    $offset = ($page - 1) * $per;

    /* ===== DATA ===== */
    $selectParts = [];
    foreach ($cols as $key => $c) {
        $selectParts[] = ($c['sql']) . ' AS ' . bt($c['alias']);
    }

    $sql =
        'SELECT ' . implode(', ', $selectParts) .
        ' FROM ' . $fromSql . $whereSql .
        ' ORDER BY ' . qcol('u', 'id_user') . ' DESC' .
        ' LIMIT ' . (int)$per . ' OFFSET ' . (int)$offset;

    $stmt = stmtExec($conn, $sql, $params, $types);
    $rowsData = stmtFetchAllAssoc($stmt);
    $stmt->close();

    /* ===== URL BASE ===== */
    $baseQ = [
        'page' => 'hr_uzivatele',
        'per'  => $per,
        'p'    => $page,
        'akt'  => $akt,
        'f'    => $filters,
    ];
    if ($TRACE) $baseQ['trace'] = '1';

    /* ===== TABULKA ===== */
    echo '<div class="card"><div class="table-wrap">';
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="hr_uzivatele">';
    if ($TRACE) echo '<input type="hidden" name="trace" value="1">';
    echo '<input type="hidden" name="p" value="1">';

    echo '<table class="table uzivatele-table"><thead>';

    /* FILTRY */
    $resetQ = ['page' => 'hr_uzivatele'];
    if ($TRACE) $resetQ['trace'] = '1';

    echo '<tr class="filter-row">';

    $keys = array_keys($cols);
    $i = 0;
    $n = count($keys);

    while ($i < $n) {
        $key = $keys[$i];
        $c = $cols[$key];

        if ($key === 'reg') {
            // sloučit 3 pravé buňky (reg + aktivni + akce) do jedné
            $href = build_url($resetQ);
            echo '<th class="c-filtr-reset" colspan="3">';
            echo '<div class="filter-actions">';
            echo '<a class="icon-btn icon-x small" href="' . h($href) . '">×</a>';
            echo '</div>';
            echo '</th>';
            $i += 3;
            continue;
        }

        echo '<th class="c-' . h($key) . '">';

        $ft = $c['filter'] ?? false;
        if ($ft !== false) {
            echo '<input class="filter-input" name="f[' . h($key) . ']" value="' . h($filters[$key] ?? '') . '">';
        }

        echo '</th>';
        $i++;
    }

    echo '</tr>';

    /* HLAVIČKY */
    echo '<tr>';
    foreach ($cols as $key => $c) {
        echo '<th class="c-' . h($key) . '">' . h($c['label']) . '</th>';
    }
    echo '</tr>';

    echo '</thead><tbody>';

    if (!$rowsData) {
        echo '<tr><td colspan="' . count($cols) . '">Žádná data</td></tr>';
    } else {
        $iconsHtml =
            '<span class="row-icons">' .
            '<img src="img/icons/search.svg" alt="Detail uživatele">' .
            '<img src="img/icons/calendar.svg" alt="Směny">' .
            '<img src="img/icons/clock-3.svg" alt="Hodiny">' .
            '<img src="img/icons/key.svg" alt="Loginy">' .
            '<img src="img/icons/role.svg" alt="Práva/pozice">' .
            '<img src="img/icons/graf.svg" alt="Aktivita">' .
            '<img src="img/icons/notes.svg" alt="Poznámka">' .
            '</span>';

        foreach ($rowsData as $r) {
            echo '<tr>';

            foreach ($cols as $key => $c) {
                if ($key === 'akce') {
                    echo '<td class="c-' . h($key) . '">' . $iconsHtml . '</td>';
                    continue;
                }

                $alias = $c['alias'];
                $val = $r[$alias] ?? '';

                $fmt = $c['fmt'] ?? null;
                if (is_callable($fmt)) {
                    $val = $fmt($val);
                }

                echo '<td class="c-' . h($key) . '">' . h((string)$val) . '</td>';
            }

            echo '</tr>';
        }
    }

    echo '</tbody></table>';

    /* ===== SPODNÍ LIŠTA ===== */
    echo '<div class="list-bottom">';

    echo '<div class="per-form">';
    echo '<span>Zobrazuji</span>';
    echo '<select name="per" class="filter-input per-select" onchange="this.form.p.value=1; this.form.submit();">';
    foreach ([20, 50, 100] as $opt) {
        $sel = '';
        if ($per === $opt) $sel = ' selected';
        echo '<option value="' . (int)$opt . '"' . $sel . '>' . (int)$opt . ' řádků</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div class="pagination-icon">';

    $mk = function(int $p, string $label, bool $disabled = false) use ($baseQ): string {
        $href = '#';
        if (!$disabled) $href = build_url($baseQ, ['p' => $p]);

        $cls = 'icon-btn w44';
        if ($disabled) $cls .= ' disabled';

        return '<a class="' . h($cls) . '" href="' . h($href) . '">' . h($label) . '</a>';
    };

    echo $mk(1, '«', $page <= 1);
    echo $mk(max(1, $page - 1), '‹', $page <= 1);

    $items = [];
    if ($pages <= 7) {
        for ($i = 1; $i <= $pages; $i++) $items[] = $i;
        while (count($items) < 7) $items[] = null;
    } elseif ($page <= 4) {
        $items = [1, 2, 3, 4, 5, '…', $pages];
    } elseif ($page >= $pages - 3) {
        $items = [1, '…', $pages - 4, $pages - 3, $pages - 2, $pages - 1, $pages];
    } else {
        $items = [1, '…', $page - 1, $page, $page + 1, '…', $pages];
    }

    foreach ($items as $it) {
        if ($it === null) {
            echo '<span class="icon-btn w44 placeholder">0</span>';
            continue;
        }
        if ($it === '…') {
            echo '<span class="icon-btn w44 disabled">…</span>';
            continue;
        }
        $pnum = (int)$it;
        if ($pnum === $page) {
            echo '<span class="icon-btn w44 page-current">' . $pnum . '</span>';
        } else {
            echo $mk($pnum, (string)$pnum, false);
        }
    }

    echo $mk(min($pages, $page + 1), '›', $page >= $pages);
    echo $mk($pages, '»', $page >= $pages);

    echo '</div>';

    echo '<div class="per-form right">';
    echo '<select name="akt" class="filter-input akt-select" onchange="this.form.p.value=1; this.form.submit();">';

    $selAkt1 = '';
    if ($akt === '1') $selAkt1 = ' selected';
    echo '<option value="1"' . $selAkt1 . '>Aktivní</option>';

    $selAkt0 = '';
    if ($akt === '0') $selAkt0 = ' selected';
    echo '<option value="0"' . $selAkt0 . '>Neaktivní</option>';

    $selAktAll = '';
    if ($akt === 'all') $selAktAll = ' selected';
    echo '<option value="all"' . $selAktAll . '>Vše</option>';

    echo '</select>';
    echo '</div>';

    echo '</div>'; // list-bottom

    echo '</form></div></div>';

} catch (Throwable $e) {
    log_error($e);

    echo '<section class="card"><p>Omlouváme se, ale stránku nelze momentálně zobrazit.</p></section>';

    if ($TRACE) {
        trace_msg(true, 'CHYBA: ' . $e->getMessage());
        trace_msg(true, 'Soubor: ' . $e->getFile() . ':' . $e->getLine());
        trace_msg(true, 'Log: /log/error.log');
    }
}

/* pages/hr_uzivatele.php * Verze: V11 * Aktualizace: 20.2.2026 * Počet řádků: 483 */
?>