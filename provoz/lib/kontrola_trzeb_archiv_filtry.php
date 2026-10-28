<?php
// Výběr poboček archivu začíná všemi povolenými pobočkami; výslovný výběr se zachová v detailu.
declare(strict_types=1);
const CB_KT_ARCHIV_PER = [20, 50, 100, 500];

// Vsechny vzdy znamena jen aktualne povolene pobocky, nikoli neomezeny dotaz.
function cb_kt_archiv_filtry(array $input, array $branches): array
{
    // Pobočka otevřené kontroly není filtrem seznamu; rozhodují pouze checkboxy archivu.
    $raw = $input['kt_pobocky'] ?? (isset($input['kt_vyber']) ? [] : array_keys($branches));
    if (!is_array($raw)) { throw new CbUserVisibleException('Neplatný výběr poboček.'); }
    $ids = [];
    foreach ($raw as $id) {
        if (!is_scalar($id) || !ctype_digit((string)$id) || !isset($branches[(int)$id])) {
            throw new CbUserVisibleException('K vybrané pobočce nemáte přístup.');
        }
        $ids[(int)$id] = (int)$id;
    }
    $per = (int)($input['kt_per'] ?? 20);
    return ['ids'=>array_values($ids), 'per'=>in_array($per, CB_KT_ARCHIV_PER, true) ? $per : 20,
        'page'=>max(1,(int)($input['kt_strana'] ?? 1)), 'invalid'=>($input['kt_neplatne'] ?? '') === '1'];
}

// Detail ma vlastni pobocku, zatimco navrat a strankovani pamatuji vyber celeho seznamu.
function cb_kt_archiv_url(array $filters, array $archive, array $extra = []): string
{
    return cb_kt_url($filters, 'archiv_kontrol_trzeb', array_merge([
        'kt_vyber'=>1, 'kt_pobocky'=>$archive['ids'], 'kt_per'=>$archive['per'],
        'kt_strana'=>$archive['page'], 'kt_neplatne'=>$archive['invalid'] ? 1 : 0,
    ], $extra));
}
