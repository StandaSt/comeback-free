<?php
// pages/obj_zakaznici.php V6 – počet řádků: 445 – aktuální čas v ČR: 18.1.2026 11:23
declare(strict_types=1);

/*
 * Zákazníci
 * - bez hlavičky/patičky (řeší index.php)
 * - bez mysqli::get_result() (kvůli hostingu)
 * - TRACE režim: ?page=zakaznici&trace=1
 */

require_once __DIR__ . '/../lib/bootstrap.php';

$TRACE = (isset($_GET['trace']) && $_GET['trace'] === '1');

/* ===== TRACE (jen pro dočasné ladění) ===== */
if ($TRACE) {
    @ini_set('display_errors', '0');
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) { @ob_end_flush(); }
    @ob_implicit_flush(true);
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

/* telefon do tabulky: prázdné -> "-" */
function fmt_tel_cell(mixed $v): string {
    $sv = trim((string)$v);
    if ($sv === '') return '-';
    return fmt_tel($sv);
}

/* poslední objednávka – jen datum "12.6.24" */
function fmt_datum(mixed $v): string {
    $s = trim((string)$v);
    if ($s === '' || $s === '0000-00-00' || $s === '0000-00-00 00:00:00') return '';

    $ts = strtotime($s);
    if ($ts === false) return $s;

    return date('j.n.y', $ts); // 12.6.24
}

function fmt_datum_cell(mixed $v): string {
    return fmt_datum($v);
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

/* ===== UI ===== */
// echo '<div class="page-head"><h2>Seznam zákazníků</h2></div>';

/* ===== VSTUPY ===== */
$per  = clampInt($_GET['per'] ?? 20, 20, 100, 20);
$page = clampInt($_GET['p'] ?? 1, 1, 1_000_000, 1);
$filters = is_array($_GET['f'] ?? null) ? $_GET['f'] : [];

/*
 * filtr blokovaných:
 * 0 = Aktivní (neblokovaní) DEFAULT
 * 1 = Blokovaní
 * all = Vše
 */
$blk = (string)($_GET['blk'] ?? '0');
if (!in_array($blk, ['0', '1', 'all'], true)) $blk = '0';

/* ===== KONFIG ZOBRAZENÍ (bez sloupce blok) ===== */
$cols = [
    'id' => [
        'label'   => 'id',
        'filter'  => false,
        'db'      => 'id_zak',
    ],
    'prijmeni' => [
        'label'   => 'prijmeni',
        'filter'  => true,
        'db'      => 'prijmeni',
        'where'   => qcol('z', 'prijmeni'),
    ],
    'jmeno' => [
        'label'   => 'jmeno',
        'filter'  => true,
        'db'      => 'jmeno',
        'where'   => qcol('z', 'jmeno'),
    ],
    'telefon' => [
        'label'   => 'telefon',
        'filter'  => true,
        'db'      => 'telefon',
        'where'   => qcol('z', 'telefon'),
        'fmt'     => 'fmt_tel_cell',
    ],
    'email' => [
        'label'   => 'email',
        'filter'  => true,
        'db'      => 'email',
        'where'   => qcol('z', 'email'),
    ],
    'ulice' => [
        'label'   => 'ulice',
        'filter'  => true,
        'db'      => 'ulice',
        'where'   => qcol('z', 'ulice'),
    ],
    'mesto' => [
        'label'   => 'mesto',
        'filter'  => true,
        'db'      => 'mesto',
        'where'   => qcol('z', 'mesto'),
    ],
    'pobocka' => [
        'label'   => 'pobočka',
        'filter'  => true,
        'db'      => 'pobocka',
        'where'   => qcol('p', 'kod'),
    ],
    'posl_obj' => [
        'label'     => 'posl_obj',
        'filter'    => false,
        'db'        => 'posledni_obj',
        'fmt'       => 'fmt_datum_cell',
        'filter_ui' => 'reset',
    ],
];

try {
    $conn = db();

    /* ===== FROM + JOIN ===== */
    $fromSql =
        bt('zakaznik') . ' ' . bt('z') . ' ' .
        'LEFT JOIN ' . bt('pobocka') . ' ' . bt('p') .
        ' ON ' . qcol('p', 'id_pob') . ' = ' . qcol('z', 'id_pob');

    /* ===== WHERE ===== */
    $params = [];
    $types  = '';
    $where  = [];

    if ($blk !== 'all') {
        $where[]  = qcol('z', 'blokovany') . ' = ?';
        $params[] = (int)$blk;
        $types   .= 'i';
    }

    foreach ($cols as $key => $c) {
        if (empty($c['filter'])) continue;

        $val = trim((string)($filters[$key] ?? ''));
        if ($val === '') continue;

        if (empty($c['where'])) continue;

        $where[]  = $c['where'] . ' LIKE ?';
        $params[] = '%' . $val . '%';
        $types   .= 's';
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
    $selectSql =
        qcol('z', 'id_zak')       . ' AS ' . bt('id_zak') . ', ' .
        qcol('z', 'prijmeni')     . ' AS ' . bt('prijmeni') . ', ' .
        qcol('z', 'jmeno')        . ' AS ' . bt('jmeno') . ', ' .
        'COALESCE(' . qcol('z', 'telefon') . ", '') AS " . bt('telefon') . ', ' .
        'COALESCE(' . qcol('z', 'email')   . ", '') AS " . bt('email') . ', ' .
        'COALESCE(' . qcol('z', 'ulice')   . ", '') AS " . bt('ulice') . ', ' .
        'COALESCE(' . qcol('z', 'mesto')   . ", '') AS " . bt('mesto') . ', ' .
        'COALESCE(' . qcol('p', 'kod')     . ", '') AS " . bt('pobocka') . ', ' .
        qcol('z', 'posledni_obj') . ' AS ' . bt('posledni_obj');

    $sql =
        'SELECT ' . $selectSql .
        ' FROM ' . $fromSql . $whereSql .
        ' ORDER BY ' . qcol('z', 'id_zak') . ' DESC' .
        ' LIMIT ' . (int)$per . ' OFFSET ' . (int)$offset;

    $stmt = stmtExec($conn, $sql, $params, $types);
    $rowsData = stmtFetchAllAssoc($stmt);
    $stmt->close();

    /* ===== URL BASE ===== */
    $baseQ = [
        'page' => 'obj_zakaznici',
        'per'  => $per,
        'p'    => $page,
        'blk'  => $blk,
        'f'    => $filters,
    ];
    if ($TRACE) $baseQ['trace'] = '1';

    /* ===== TABULKA ===== */
    echo '<div class="card"><div class="table-wrap">';
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="obj_zakaznici">';
    if ($TRACE) echo '<input type="hidden" name="trace" value="1">';
    echo '<input type="hidden" name="p" value="1">';

    echo '<table class="table zakaznici-table"><thead>';

    /* FILTRY */
    echo '<tr class="filter-row">';
    foreach ($cols as $key => $c) {
        echo '<th class="c-' . h($key) . '">';

        if (!empty($c['filter_ui']) && $c['filter_ui'] === 'reset') {
            $href = build_url(['page' => 'obj_zakaznici'] + ($TRACE ? ['trace' => '1'] : []));
            echo '<div class="filter-actions">';
            echo '<a class="icon-btn icon-x small" href="' . h($href) . '">×</a>';
            echo '</div>';

        } elseif (!empty($c['filter'])) {
            echo '<input class="filter-input" name="f[' . h($key) . ']" value="' . h($filters[$key] ?? '') . '">';
        }

        echo '</th>';
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
        foreach ($rowsData as $r) {
            echo '<tr>';

            foreach ($cols as $key => $c) {
                $dbKey = (string)($c['db'] ?? '');
                $raw = ($dbKey !== '') ? ($r[$dbKey] ?? '') : '';

                $val = (string)$raw;
                if (!empty($c['fmt'])) {
                    $val = (string)call_user_func($c['fmt'], $raw);
                }

                echo '<td class="c-' . h($key) . '">' . h($val) . '</td>';
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
        $sel = ($per === $opt) ? ' selected' : '';
        echo '<option value="' . (int)$opt . '"' . $sel . '>' . (int)$opt . ' řádků</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div class="pagination-icon">';

    $mk = function(int $p, string $label, bool $disabled = false) use ($baseQ): string {
        $href = $disabled ? '#' : build_url($baseQ, ['p' => $p]);
        $cls  = 'icon-btn w44' . ($disabled ? ' disabled' : '');
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
    echo '<select name="blk" class="filter-input blk-select" onchange="this.form.p.value=1; this.form.submit();">';
    echo '<option value="0"' . ($blk === '0' ? ' selected' : '') . '>Aktivní</option>';
    echo '<option value="1"' . ($blk === '1' ? ' selected' : '') . '>Blokovaní</option>';
    echo '<option value="all"' . ($blk === 'all' ? ' selected' : '') . '>Vše</option>';
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

/* pages/obj_zakaznici.php V6 – počet řádků: 445 – aktuální čas v ČR: 18.1.2026 11:23 */
?>