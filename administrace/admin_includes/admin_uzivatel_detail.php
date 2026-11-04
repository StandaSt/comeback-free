<?php
declare(strict_types=1);
// Udaje a zobrazovane jmeno osoby vcetne titulu sdili centralni cteci zdroj.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';

/* Přehled přístupu, registrace zařízení, přihlášení a obnovy hesla; společný pro stránku i AJAX detail. */
require_once __DIR__ . '/../../common/lib/format.php';

function cb_admin_uzivatele_navrat_url(array $source): string
{
    $sortKeys = ['id', 'uzivatel', 'kontakt', 'firma', 'role', 'slot', 'pobocky', 'stav'];
    $perOptions = [20, 50, 100, 500];
    $perPage = (int)($source['usr_per'] ?? 50);
    $params = [
        'm' => 'administrace',
        'page' => 'uzivatele',
        'usr_f' => cb_admin_uzivatele_filtry($source),
        'usr_per' => in_array($perPage, $perOptions, true) ? $perPage : 50,
        'usr_p' => max(1, (int)($source['usr_p'] ?? 1)),
        'usr_sort' => in_array((string)($source['usr_sort'] ?? ''), $sortKeys, true) ? (string)$source['usr_sort'] : 'uzivatel',
        'usr_dir' => strtolower((string)($source['usr_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
    ];
    $detailId = (int)($source['usr_id'] ?? 0);
    if ($detailId > 0) {
        $params['usr_id'] = $detailId;
    }
    return cb_root_url('index.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
}

/** Vykreslí provozní přehled bez opakování kontaktních údajů ze seznamu. */
function cb_admin_uzivatel_detail_html(array $detail, array $lists): string
{
    $info = $detail['pristup_info'];
    $device = $detail['zarizeni_info'];
    $reset = $detail['obnova_info'];
    $date = static fn ($value, string $empty = 'Dosud neevidováno'): string => $value ? cb_format('dcs', $value) : $empty;
    $resetState = 'Bez žádosti';
    if ($reset) {
        $resetMeta = json_decode((string)$reset['detail_json'], true);
        $resetState = match (true) {
            !empty($reset['pouzito']) => 'Změna hesla dokončena ' . $date($reset['pouzito']),
            ($resetMeta['stav'] ?? '') === 'chyba_smtp' => 'E-mail se nepodařilo odeslat',
            empty($reset['vysledek']) => 'Odeslání e-mailu nepotvrzeno',
            !empty($reset['zruseno']) => 'Odkaz zneplatněn',
            !empty($reset['vyprselo']) => 'Platnost odkazu vypršela',
            empty($reset['platnost_do']) => 'Stav dokončení není evidován',
            default => 'E-mail předán k odeslání, změna hesla nedokončena',
        };
    }
    ob_start();
    ?>
    <div class="admin_user_detail_transition"><div class="admin_user_summary">
        <div class="admin_user_summary_grid">
            <section><h3>Přístup do IS</h3><dl>
                <dt>Osoba v HR</dt><dd><strong><?= !empty($detail['aktivni']) ? 'Aktivní' : 'Neaktivní – přístup zakázán' ?></strong></dd>
                <dt>Heslo do IS</dt><dd><?= !empty($detail['ma_heslo']) ? 'Nastaveno' : 'Dosud nenastaveno' ?></dd>
                <dt>Chybné pokusy za 15 minut</dt><dd><?= h((string)$info['chyby_15_min']) ?> / limit 5</dd>
                <dt>Blokace přihlášení</dt><dd<?= !empty($info['blokace_do']) ? ' class="txt_cervena"' : '' ?>><?= !empty($info['blokace_do']) ? 'Do ' . h($date($info['blokace_do'])) : 'Bez blokace' ?></dd>
            </dl></section>
            <section><h3>Zařízení pro 2FA</h3><dl>
                <dt>Registrace</dt><dd><strong><?= !$device ? 'Dosud bez zařízení' : (!empty($device['aktivni']) ? 'Aktivní' : 'Bez aktivního zařízení') ?></strong></dd>
                <?php if ($device): ?>
                <dt>Název zařízení</dt><dd><?= h(trim((string)$device['nazev']) ?: 'Bez názvu') ?></dd>
                <dt><?= !empty($device['aktivni']) ? 'Registrováno' : 'Poslední registrace' ?></dt><dd><?= h($date($device['vytvoreno'])) ?></dd>
                <dt>Poslední evidované použití</dt><dd><?= h($date($device['naposledy'])) ?></dd>
                <?php endif; ?>
                <?php if ((int)$info['aktivnich_zarizeni'] > 1): ?><dt>Aktivních zařízení</dt><dd><?= h((string)$info['aktivnich_zarizeni']) ?></dd><?php endif; ?>
            </dl></section>
            <section><h3>Přihlášení a heslo</h3><dl>
                <dt>Poslední vstup do IS</dt><dd><?= h($date($info['posledni_vstup'], 'Dosud bez přihlášení')) ?></dd>
                <dt>Poslední neúspěšný pokus</dt><dd><?= h($date($info['posledni_chyba'], 'Bez záznamu')) ?></dd>
                <dt>Poslední žádost o nové heslo</dt><dd><?= h($date($reset['cas'] ?? null, 'Bez žádosti')) ?></dd>
                <dt>Výsledek obnovy</dt><dd><?= h($resetState) ?></dd>
            </dl></section>
        </div>
        <div class="admin_user_summary_links">
            <?php if (isset($_SESSION['prava_stav'][111]) && function_exists('cb_pravo_ma') && cb_pravo_ma(111)): ?>
            <a href="<?= h(cb_root_url('index.php?' . http_build_query(['m'=>'administrace','page'=>'historie_cinnosti','hc_autor'=>(int)$detail['id_user'],'hc_druh'=>'vse'], '', '&', PHP_QUERY_RFC3986))) ?>">Přehled akcí uživatele</a>
            <?php endif; ?>
            <a href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$detail['id_person']) . '&upravit=1')) ?>">Upravit v HR</a>
        </div>
    </div></div>
    <?php
    return (string)ob_get_clean();
}
