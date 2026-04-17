<?php
// K2
// karty/admin_priprava_init.php * Verze: V2 * Aktualizace: 17.04.2026
declare(strict_types=1);

$summaryKeys = ['restia', 'smeny', 'reporty'];
$summary = [];
$initError = '';

try {
    $conn = db();
    $conn->set_charset('utf8mb4');
    $summary = cb_db_scope_summary($conn, $summaryKeys);
} catch (Throwable $e) {
    $summary = [];
    $initError = 'Načtení přehledu databáze selhalo.';
}

if ($initError !== '') {
    $card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0 card_text_muted">' . h($initError) . '</p>';
} else {
    $card_min_html = ''
        . '<div class="table-wrap ram_normal bg_bila zaobleni_12">'
        . '  <table class="table ram_normal bg_bila radek_1_35">'
        . '    <thead>'
        . '      <tr>'
        . '        <th class="txt_l">Skupina</th>'
        . '        <th class="txt_r">Záznamů</th>'
        . '        <th class="txt_r">Objem</th>'
        . '      </tr>'
        . '    </thead>'
        . '    <tbody>';

    foreach ($summaryKeys as $key) {
        if (!isset($summary[$key])) {
            continue;
        }

        $item = $summary[$key];
        $label = (string)($item['label'] ?? $key);
        $count = (int)($item['count'] ?? 0);
        $bytes = (int)($item['bytes'] ?? 0);

        $card_min_html .= ''
            . '      <tr>'
            . '        <td>' . h($label) . '</td>'
            . '        <td class="txt_r" style="' . h(cb_db_count_style($count)) . '"><strong>' . h(cb_db_fmt_rows_approx($count)) . '</strong></td>'
            . '        <td class="txt_r"><strong>' . h(cb_db_fmt_bytes($bytes)) . '</strong></td>'
            . '      </tr>';
    }

    $card_min_html .= ''
        . '    </tbody>'
        . '  </table>'
        . '</div>';
}

$card_max_html = '';

// Konec souboru