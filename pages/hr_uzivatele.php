<?php
// pages/hr_uzivatele.php V5 – počet řádků: 445 – aktuální čas v ČR: 19.1.2026 15:00
declare(strict_types=1);

/*
 * Uživatelé
 * - bez hlavičky/patičky (řeší index.php)
 * - bez mysqli::get_result() (kvůli hostingu)
 * - TRACE režim: ?page=uzivatele&trace=1
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

final class SqlFail extends RuntimeException {
    public string $sql;
    public array $ctx;

    public function __construct(string $message, string $sql, array $ctx = [], int $code = 0, ?Throwable $prev = null) {
        parent::__construct($message, $code, $prev);
        $this->sql = $sql;
        $this->ctx = $ctx;
    }
}

function stmtExec(mysqli $conn, string $sql, array $params = [], string $types = ''): mysqli_stmt {
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new SqlFail('SQL prepare selhal.', $sql, [
                'errno' => $conn->errno,
                'error' => $conn->error,
            ]);
        }

        if ($params) {
            if ($types === '') {
                throw new SqlFail('Interní chyba: chybí $types pro bind_param().', $sql);
            }
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt;

    } catch (mysqli_sql_exception $e) {
        // v TRACE režimu to sem půjde (mysqli_report STRICT). Přemapujeme na naši výjimku s kontextem.
        throw new SqlFail('SQL execute selhal.', $sql, [
            'errno' => $e->getCode(),
            'error' => $e->getMessage(),
        ], 0, $e);
    }
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

function show_error(bool $TRACE, Throwable $e, array $qGet): void {
    echo '<section class="card">';
    echo '<p><strong>Chyba při načítání uživatelů.</strong></p>';

    if ($TRACE) {
        echo '<div class="trace-box">';
        echo '<div><strong>Typ:</strong> ' . h(get_class($e)) . '</div>';
        echo '<div><strong>Zpráva:</strong> ' . h($e->getMessage()) . '</div>';

        if ($e instanceof SqlFail) {
            echo '<div><strong>SQL:</strong> <code>' . h($e->sql) . '</code></div>';
            if (!empty($e->ctx)) {
                echo '<div><strong>Kontext:</strong> <code>' . h(json_encode($e->ctx, JSON_UNESCAPED_UNICODE)) . '</code></div>';
            }
        }

        echo '</div>';
    } else {
        // "příjemná cesta": rovnou link na stejné parametry + trace=1
        $q = $qGet;
        $q['page'] = 'hr_uzivatele';
        $q['trace'] = '1';
        $href = '?' . http_build_query($q);
        echo '<p><a href="' . h($href) . '">Zobrazit detaily</a></p>';
    }

    echo '</section>';
}

/* ===== KONFIG ===== */
$cols = [
    'id'       => ['label' => 'id',       'filter' => true],
    'prijmeni' => ['label' => 'prijmeni', 'filter' => true],
    'jmeno'    => ['label' => 'jmeno',    'filter' => true],
    'telefon'  => ['label' => 'telefon',  'filter' => true],
    'email'    => ['label' => 'email',    'filter' => true],
    'reg'      => ['label' => 'reg',      'filter' => false], // ENTER
    'aktivni'  => ['label' => 'aktivni',  'filter' => false], // X
];

/* ===== UI hlavička ===== */
// echo '<div class="page-head"><h2>Seznam uživatelů</h2></div>';

/* ===== VSTUPY ===== */
$per  = clampInt($_GET['per'] ?? 20, 20, 100, 20);
$page = clampInt($_GET['p'] ?? 1, 1, 1_000_000, 1);
$filters = is_array($_GET['f'] ?? null) ? $_GET['f'] : [];

/* aktivní filtr: 1=jen aktivní (default), 0=jen neaktivní, all=vše */
$akt = (string)($_GET['akt'] ?? '1');
if (!in_array($akt, ['1', '0', 'all'], true)) $akt = '1';

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    /* ===== FROM ===== */
    $fromSql = bt('user') . ' ' . bt('u');

    /* ===== WHERE ===== */
    $params = [];
    $types = '';
    $where = [];

    if ($akt !== 'all') {
        $where[] = qcol('u', 'aktivni') . ' = ?';
        $params[] = (int)$akt;
        $types .= 'i';
    }

    foreach ($cols as $key => $c) {
        if (!$c['filter']) continue;
        $val = trim((string)($filters[$key] ?? ''));
        if ($val === '') continue;

        if ($key === 'id') {
            $where[] = qcol('u', 'id_user') . ' = ?';
            $params[] = (int)$val;
            $types .= 'i';
            continue;
        }

        if ($key === 'prijmeni') {
            $where[] = qcol('u', 'prijmeni') . ' LIKE ?';
            $params[] = '%' . $val . '%';
            $types .= 's';
            continue;
        }

        if ($key === 'jmeno') {
            $where[] = qcol('u', 'jmeno') . ' LIKE ?';
            $params[] = '%' . $val . '%';
            $types .= 's';
            continue;
        }

        if ($key === 'telefon') {
            $where[] = qcol('u', 'telefon') . ' LIKE ?';
            $params[] = '%' . $val . '%';
            $types .= 's';
            continue;
        }

        if ($key === 'email') {
            $where[] = qcol('u', 'email') . ' LIKE ?';
            $params[] = '%' . $val . '%';
            $types .= 's';
            continue;
        }
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    /* ===== COUNT ===== */
    $stmtCnt = stmtExec(
        $conn,
        'SELECT COUNT(*) FROM ' . $fromSql . $whereSql,
        $params,
        $types
    );
    $stmtCnt->bind_result($total);
    $stmtCnt->fetch();
    $stmtCnt->close();

    $pages = max(1, (int)ceil((int)$total / $per));
    $page = min($page, $pages);
    $offset = ($page - 1) * $per;

    /* ===== DATA ===== */
    $selectSql =
        qcol('u', 'id_user')   . ' AS ' . bt('id_user') . ', ' .
        qcol('u', 'prijmeni')  . ' AS ' . bt('prijmeni') . ', ' .
        qcol('u', 'jmeno')     . ' AS ' . bt('jmeno') . ', ' .
        'COALESCE(' . qcol('u', 'telefon') . ", '') AS " . bt('telefon') . ', ' .
        'COALESCE(' . qcol('u', 'email') . ", '') AS " . bt('email') . ', ' .
        qcol('u', 'vytvoren_smeny')  . ' AS ' . bt('reg') . ', ' .
        qcol('u', 'aktivni')   . ' AS ' . bt('aktivni');

    $sql =
        'SELECT ' . $selectSql .
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

    echo '<table class="table table-fixed uzivatele-table"><thead>';

    /* FILTRY */
    echo '<tr class="filter-row">';
    foreach ($cols as $key => $c) {
        echo '<th class="c-' . h($key) . '">';

        if ($key === 'reg') {
            echo '<button type="submit" class="icon-btn icon-enter">⏎</button>';
        } elseif ($key === 'aktivni') {
            $href = build_url(['page' => 'hr_uzivatele'] + ($TRACE ? ['trace' => '1'] : []));
            echo '<a class="icon-btn icon-x" href="' . h($href) . '">×</a>';
        } elseif ($c['filter']) {
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
                $val = '';

                if ($key === 'id')       $val = $r['id_user'] ?? '';
                if ($key === 'prijmeni') $val = $r['prijmeni'] ?? '';
                if ($key === 'jmeno')    $val = $r['jmeno'] ?? '';
                if ($key === 'telefon')  $val = $r['telefon'] ?? '';
                if ($key === 'email')    $val = $r['email'] ?? '';
                if ($key === 'reg')      $val = $r['reg'] ?? '';
                if ($key === 'aktivni')  $val = $r['aktivni'] ?? '';

                if ($key === 'telefon') {
                    $sv = trim((string)$val);
                    $val = ($sv === '') ? '-' : fmt_tel((string)$val);
                }
                if ($key === 'aktivni') {
                    $val = ((string)$val === '1') ? 'Ano' : 'Ne';
                }

                echo '<td class="c-' . h($key) . '">' . h((string)$val) . '</td>';
            }

            echo '</tr>';
        }
    }

    echo '</tbody></table>';

    /* ===== SPODNÍ LIŠTA ===== */
    echo '<div class="list-bottom">';

    // vlevo řádkování
    echo '<div class="per-form">';
    echo '<span>Zobrazuji</span>';
    echo '<select name="per" class="filter-input per-select" onchange="this.form.p.value=1; this.form.submit();">';
    foreach ([20, 50, 100] as $opt) {
        $sel = ($per === $opt) ? ' selected' : '';
        echo '<option value="' . (int)$opt . '"' . $sel . '>' . (int)$opt . ' řádků</option>';
    }
    echo '</select>';
    echo '</div>';

    // střed stránkování (ikonové, pevné 7-slotové)
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

    // vpravo aktivní/neaktivní/vše
    echo '<div class="per-form right">';
    echo '<select name="akt" class="filter-input akt-select" onchange="this.form.p.value=1; this.form.submit();">';
    echo '<option value="1"' . ($akt === '1' ? ' selected' : '') . '>Aktivní</option>';
    echo '<option value="0"' . ($akt === '0' ? ' selected' : '') . '>Neaktivní</option>';
    echo '<option value="all"' . ($akt === 'all' ? ' selected' : '') . '>Vše</option>';
    echo '</select>';
    echo '</div>';

    echo '</div>'; // list-bottom

    echo '</form></div></div>';

} catch (Throwable $e) {
    show_error($TRACE, $e, $_GET);
}

/* pages/hr_uzivatele.php V5 – počet řádků: 445 – aktuální čas v ČR: 19.1.2026 15:00 */
