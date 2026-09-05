<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/db/db_prava.php';

const CB_AI_ANALYTIK_EXPORT_PLATNOST_SEKUND = 1800;

function cb_ai_analytik_export_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function cb_ai_analytik_export_base64url_decode(string $value): string
{
    if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
        throw new RuntimeException('Exportní data nemají platný formát.');
    }
    $padding = (4 - (strlen($value) % 4)) % 4;
    $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
    if (!is_string($decoded)) {
        throw new RuntimeException('Exportní data nelze načíst.');
    }
    return $decoded;
}

function cb_ai_analytik_export_podepsat(array $data, string $secret): array
{
    if ($secret === '') {
        throw new RuntimeException('Exportní podpis není dostupný.');
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $payload = cb_ai_analytik_export_base64url_encode($json);
    return ['payload' => $payload, 'signature' => hash_hmac('sha256', $payload, $secret)];
}

function cb_ai_analytik_export_overit(string $payload, string $signature, string $secret, int $idUser): array
{
    if ($secret === '' || preg_match('/^[a-f0-9]{64}$/', $signature) !== 1
        || !hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
        throw new RuntimeException('Platnost exportu vypršela. Spusťte dotaz znovu.');
    }
    $data = json_decode(cb_ai_analytik_export_base64url_decode($payload), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($data)
        || (int)($data['version'] ?? 0) !== 1
        || (int)($data['id_user'] ?? 0) !== $idUser
        || (int)($data['audit_id'] ?? 0) <= 0
        || (int)($data['expires_at'] ?? 0) < time()) {
        throw new RuntimeException('Platnost exportu vypršela. Spusťte dotaz znovu.');
    }
    return $data;
}

function cb_ai_analytik_export_prijemci(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT DISTINCT u.id_user, u.jmeno, u.prijmeni, u.email
         FROM user AS u
         INNER JOIN user_role AS ur ON ur.id_user = u.id_user AND ur.id_role < 4
         WHERE TRIM(u.email) <> ''
         ORDER BY u.prijmeni ASC, u.jmeno ASC, u.id_user ASC"
    );
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $email = trim((string)($row['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            continue;
        }
        $rows[] = [
            'id_user' => (int)$row['id_user'],
            'name' => trim((string)$row['jmeno'] . ' ' . (string)$row['prijmeni']),
            'email' => $email,
        ];
    }
    $result->free();
    return $rows;
}

function cb_ai_analytik_export_prijemce(mysqli $conn, int $idUser): ?array
{
    $stmt = $conn->prepare(
        "SELECT u.id_user, u.jmeno, u.prijmeni, u.email
         FROM user AS u
         WHERE u.id_user = ?
           AND TRIM(u.email) <> ''
           AND EXISTS (
               SELECT 1 FROM user_role ur WHERE ur.id_user = u.id_user AND ur.id_role < 4
           )
         LIMIT 1"
    );
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $result->free();
    $stmt->close();
    if (!is_array($row)) {
        return null;
    }
    $email = trim((string)($row['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return null;
    }
    return [
        'id_user' => (int)$row['id_user'],
        'name' => trim((string)$row['jmeno'] . ' ' . (string)$row['prijmeni']),
        'email' => $email,
    ];
}

function cb_ai_analytik_export_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cb_ai_analytik_export_cislo(float $value, int $decimals = 0): string
{
    return number_format($value, $decimals, ',', ' ');
}

function cb_ai_analytik_export_hodnota(float $value, string $unit): string
{
    $decimals = fmod(abs($value), 1.0) > 0.000001 ? 2 : 0;
    $formatted = cb_ai_analytik_export_cislo($value, $decimals);
    return $unit === '' ? $formatted : $formatted . ' ' . $unit;
}

function cb_ai_analytik_export_graf_html(?array $chart): string
{
    if (!is_array($chart)) {
        return '';
    }
    $labels = is_array($chart['labels'] ?? null) ? array_values($chart['labels']) : [];
    $series = is_array($chart['series'] ?? null) ? array_values($chart['series']) : [];
    if ($labels === [] || $series === []) {
        return '';
    }

    $maxBySeries = [];
    foreach ($series as $seriesIndex => $item) {
        $values = is_array($item['data'] ?? null) ? $item['data'] : [];
        $max = 0.0;
        foreach ($values as $value) {
            $max = max($max, abs((float)$value));
        }
        $maxBySeries[$seriesIndex] = $max > 0 ? $max : 1.0;
    }

    $rows = '';
    foreach ($labels as $labelIndex => $label) {
        $rows .= '<div class="chart-row"><div class="chart-label">'
            . cb_ai_analytik_export_h($label) . '</div>';
        foreach ($series as $seriesIndex => $item) {
            if (!is_array($item)) {
                continue;
            }
            $values = is_array($item['data'] ?? null) ? $item['data'] : [];
            $value = (float)($values[$labelIndex] ?? 0);
            $width = max(0.5, min(100.0, (abs($value) / $maxBySeries[$seriesIndex]) * 100));
            $rows .= '<div class="chart-line"><span>'
                . cb_ai_analytik_export_h((string)($item['name'] ?? 'Hodnota'))
                . '</span><div class="bar"><i style="width:' . number_format($width, 2, '.', '')
                . '%"></i></div><strong>'
                . cb_ai_analytik_export_h(cb_ai_analytik_export_hodnota($value, (string)($item['unit'] ?? '')))
                . '</strong></div>';
        }
        $rows .= '</div>';
    }
    return '<section><h2>' . cb_ai_analytik_export_h((string)($chart['title'] ?? 'Graf'))
        . '</h2><div class="chart">' . $rows . '</div></section>';
}

function cb_ai_analytik_export_tabulka_html(array $columns, array $rows): string
{
    if ($columns === [] || $rows === []) {
        return '';
    }
    $header = '';
    foreach ($columns as $column) {
        if (is_array($column)) {
            $header .= '<th>' . cb_ai_analytik_export_h((string)($column['label'] ?? '')) . '</th>';
        }
    }
    $body = '';
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $body .= '<tr>';
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $key = (string)($column['key'] ?? '');
            $type = (string)($column['type'] ?? 'text');
            $value = $row[$key] ?? '';
            if ($type === 'number') {
                $value = cb_ai_analytik_export_hodnota((float)$value, '');
            } elseif ($type === 'currency') {
                $value = cb_ai_analytik_export_hodnota((float)$value, 'Kč');
            }
            $body .= '<td' . ($type === 'number' || $type === 'currency' ? ' class="num"' : '') . '>'
                . cb_ai_analytik_export_h($value) . '</td>';
        }
        $body .= '</tr>';
    }
    return '<section><h2>Tabulka</h2><table><thead><tr>' . $header . '</tr></thead><tbody>' . $body
        . '</tbody></table></section>';
}

function cb_ai_analytik_export_roky_text(array $data): string
{
    $years = [];
    foreach (is_array($data['years'] ?? null) ? $data['years'] : [] as $rawYear) {
        $year = (int)$rawYear;
        if ($year > 0) {
            $years[] = (string)$year;
        }
    }
    return implode(', ', $years);
}

function cb_ai_analytik_export_pdf(array $data): array
{
    $text = trim((string)($data['text'] ?? ''));
    $summary = $text !== '' ? '<section><h2>Shrnutí</h2><p class="summary">'
        . nl2br(cb_ai_analytik_export_h($text)) . '</p></section>' : '';
    $chart = cb_ai_analytik_export_graf_html(is_array($data['chart'] ?? null) ? $data['chart'] : null);
    $table = cb_ai_analytik_export_tabulka_html(
        is_array($data['columns'] ?? null) ? $data['columns'] : [],
        is_array($data['rows'] ?? null) ? $data['rows'] : []
    );
    $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
    $duration = ((int)($data['duration_ms'] ?? 0)) / 1000;
    $cost = (float)($usage['cost_usd'] ?? 0);
    $yearsText = cb_ai_analytik_export_roky_text($data);
    $yearsHtml = $yearsText !== ''
        ? '<p><span class="label">Zpracované roky:</span> ' . cb_ai_analytik_export_h($yearsText) . '</p>'
        : '';
    $footer = 'Zpracoval model: ' . (string)($data['model'] ?? '') . ' za '
        . number_format($duration, 1, ',', ' ') . ' s. Spotřeba: '
        . cb_ai_analytik_export_cislo((float)($usage['total_tokens'] ?? 0)) . ' tokenů cena $'
        . number_format($cost, $cost < 0.01 ? 6 : 4, '.', '');

    $html = '<!doctype html><html lang="cs"><head><meta charset="utf-8"><style>'
        . '@page{margin:18mm 14mm 17mm;}body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#172033;}'
        . 'h1{font-size:21px;margin:0 0 12px;color:#123a70;}h2{font-size:14px;margin:16px 0 7px;color:#123a70;}'
        . 'p{margin:0 0 8px;line-height:1.45}.label{font-weight:bold}.summary{font-size:11px;}'
        . 'section{page-break-inside:auto}table{width:100%;border-collapse:collapse;font-size:8px;}'
        . 'th,td{border:1px solid #cbd5e1;padding:4px 5px;text-align:left;vertical-align:top;}'
        . 'th{background:#e8f0fa;font-weight:bold}.num{text-align:right;white-space:nowrap}'
        . '.footer{margin-top:14px;padding-top:8px;border-top:1px solid #cbd5e1;color:#526174;font-size:9px;}'
        . '.chart{border:1px solid #cbd5e1;padding:9px}.chart-row{margin-bottom:10px;page-break-inside:avoid;}'
        . '.chart-label{font-weight:bold;margin-bottom:3px}.chart-line{display:table;width:100%;margin:2px 0;}'
        . '.chart-line span,.chart-line strong,.chart-line .bar{display:table-cell;vertical-align:middle;}'
        . '.chart-line span{width:22%}.chart-line .bar{width:53%;height:9px;background:#edf1f5;}'
        . '.chart-line strong{width:25%;padding-left:7px;text-align:right;white-space:nowrap;font-size:8px;}'
        . '.bar i{display:block;height:9px;background:#2563eb}'
        . '</style></head><body><h1>AI analytik</h1>'
        . '<p><span class="label">Dotaz:</span> ' . cb_ai_analytik_export_h((string)($data['prompt'] ?? '')) . '</p>'
        . $yearsHtml
        . $summary . $chart . $table
        . '<p class="footer">' . cb_ai_analytik_export_h($footer) . '</p>'
        . '</body></html>';

    $options = new Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return [
        'content' => $dompdf->output(),
        'filename' => 'ai_analytik_' . (int)$data['audit_id'] . '_' . date('Y-m-d_H-i') . '.pdf',
    ];
}

function cb_ai_analytik_export_chyba(Throwable $error): string
{
    return cb_user_ma_roli(1)
        ? get_class($error) . ': ' . $error->getMessage() . ' v ' . $error->getFile() . ':' . $error->getLine()
        : 'Export se nepodařilo vytvořit.';
}
