<?php
/* Účel: Správa ručně psaných změn IS s právem 112, formulářem a stránkovaným přehledem. */
declare(strict_types=1);
require_once __DIR__ . '/../../common/lib/verzovani.php';
if (!cb_verze_sprava_povolena()) {
    http_response_code(403);
    echo '<section class="blok"><p>Nemáte právo spravovat změny v IS.</p></section>';
    return;
}
$notice = $_SESSION['cb_verze_notice'] ?? null;
unset($_SESSION['cb_verze_notice']);
$draft = is_array($notice) ? ($notice['draft'] ?? []) : [];
$strana = max(0, min(100000, (int)($_GET['verze_strana'] ?? 0)));
$prehled = ['radky' => [], 'dalsi' => false];
$chyba = '';
try { $prehled = cb_verze_nacist(db(), $strana); }
catch (Throwable $error) { $chyba = $error instanceof CbUserVisibleException ? $error->getMessage() : 'Přehled změn se nepodařilo načíst.'; }
// Každý formulář má vlastní klíč pro rozpoznání opakovaného odeslání.
$_SESSION['cb_verze_formulare'] = array_filter($_SESSION['cb_verze_formulare'] ?? [], static fn(int $cas): bool => $cas > time() - 86400);
$_SESSION['cb_verze_formulare'] = array_slice($_SESSION['cb_verze_formulare'], -20, null, true);
$kod = bin2hex(random_bytes(32));
$_SESSION['cb_verze_formulare'][$kod] = time();
$url = cb_root_url('index.php?m=administrace&page=zmeny_is');
?>
<section class="blok admin_verzovani">
    <?php if (is_array($notice)): ?><p role="status" class="<?= !empty($notice['ok']) ? 'txt_zelena' : 'txt_cervena' ?>"><?= h($notice['text']) ?></p><?php endif; ?>
    <?php if ($chyba !== ''): ?><p class="txt_cervena" role="status"><?= h($chyba) ?></p><?php endif; ?>
    <form method="post" action="<?= h(cb_root_url('index.php?cb_verze=ulozit')) ?>" class="admin_verze_form">
        <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
        <input type="hidden" name="kod_pozadavku" value="<?= h($kod) ?>">
        <label>Typ změny<select name="typ" required><?php foreach (cb_verze_typy() as $id => $nazev): ?><option value="<?= $id ?>"<?= (int)($draft['typ'] ?? 1) === $id ? ' selected' : '' ?>><?= h($nazev) ?></option><?php endforeach; ?></select></label>
        <label>Text změny<textarea name="obsah" rows="4" maxlength="4000" required><?= h((string)($draft['obsah'] ?? '')) ?></textarea></label>
        <div><button type="submit" class="head_task_btn"<?= $chyba !== '' ? ' disabled' : '' ?>>Uložit</button></div>
    </form>
    <h2 class="blok_title">Zadané změny</h2>
    <?php if ($chyba === ''): ?>
        <?= cb_verze_prehled_html($prehled['radky']) ?>
        <nav class="admin_verze_paging" aria-label="Stránky změn">
            <?php if ($strana > 0): ?><a href="<?= h($url . '&verze_strana=' . ($strana - 1)) ?>">Novější změny</a><?php endif; ?>
            <?php if ($prehled['dalsi']): ?><a href="<?= h($url . '&verze_strana=' . ($strana + 1)) ?>">Starší změny</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
