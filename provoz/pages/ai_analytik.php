<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ai_analytik_pravidla.php';
require_once __DIR__ . '/../lib/ai_analytik_export_common.php';

if (!cb_ai_analytik_ma_pravo()) {
    http_response_code(403);
    ?>
    <div class="ai_analytik_error" role="alert">K AI analytikovi nemáte oprávnění.</div>
    <?php
    return;
}

$aiAnalytikCsrf = cb_ai_analytik_csrf_token();
$aiAnalytikEndpoint = cb_root_url('index.php');
$aiAnalytikPdfEndpoint = cb_root_url('provoz/lib/ai_analytik_export_pdf.php');
$aiAnalytikEmailEndpoint = cb_root_url('provoz/lib/ai_analytik_export_email.php');
try {
    $aiAnalytikPrijemci = cb_ai_analytik_export_prijemci(db());
} catch (Throwable $error) {
    $aiAnalytikPrijemci = [];
}
?>
<div
    class="ai_analytik"
    data-ai-analytik
    data-endpoint="<?= h($aiAnalytikEndpoint) ?>"
    data-pdf-endpoint="<?= h($aiAnalytikPdfEndpoint) ?>"
    data-email-endpoint="<?= h($aiAnalytikEmailEndpoint) ?>"
    data-csrf="<?= h($aiAnalytikCsrf) ?>"
>
    <script type="application/json" data-ai-analytik-prijemci><?= json_encode(
        $aiAnalytikPrijemci,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
    ) ?></script>
    <p class="ai_analytik_intro">
        Zeptejte se na data v informačním systému.
    </p>

    <form class="ai_analytik_form" data-ai-analytik-form>
        <div class="ai_analytik_prompt_field">
            <fieldset class="ai_analytik_option_group">
                <legend>Požadovaný výstup</legend>
                <div class="ai_analytik_options">
                    <label><input type="checkbox" name="vystup_text" checked data-ai-analytik-vystup="text"> Text</label>
                    <label><input type="checkbox" name="vystup_tabulka" checked data-ai-analytik-vystup="tabulka"> Tabulka</label>
                    <label><input type="checkbox" name="vystup_graf" data-ai-analytik-vystup="graf"> Graf</label>
                </div>
            </fieldset>

            <label for="ai_analytik_prompt">Dotaz</label>
            <textarea
                id="ai_analytik_prompt"
                name="prompt"
                rows="3"
                placeholder="Například: Porovnej tržby a průměrnou hodnotu objednávky po měsících za poslední tři roky."
                required
                data-ai-analytik-prompt
            ></textarea>
        </div>

        <div class="ai_analytik_controls">
            <div class="ai_analytik_model_row">
                <label for="ai_analytik_model">Model:</label>
                <select id="ai_analytik_model" name="model" data-ai-analytik-model>
                    <?php foreach (cb_ai_analytik_povolene_modely() as $model): ?>
                        <option value="<?= h($model) ?>"<?= $model === CB_AI_ANALYTIK_VYCHOZI_MODEL ? ' selected' : '' ?>>
                            <?= h($model) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="head_task_btn" data-ai-analytik-submit>Odeslat</button>
        </div>
    </form>

    <div class="ai_analytik_status" role="status" aria-live="polite" data-ai-analytik-status hidden></div>

    <div class="ai_analytik_results" aria-live="polite" data-ai-analytik-results></div>
</div>
