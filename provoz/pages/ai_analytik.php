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
$aiAnalytikModelTooltips = [
    'gpt-5.6-luna' => "rychlý\nlevný\npro jednoduché dotazy",
    'gpt-5.6-terra' => "ideální pro většinu analýz\nekonomicky vyvážená volba",
    'gpt-5.6-sol' => "pro složité kombinované analýzy\npomalejší ale důkladnější\nnejdražší model",
];
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
    <details class="ai_analytik_guide">
        <summary>Jak Frantu používat</summary>
        <div class="ai_analytik_guide_content">
            <p>Napište běžnou češtinou, co chcete nad daty informačního systému zjistit. Čím přesnější bude zadání, tím přesnější může být výsledek.</p>
            <p>Oblasti analýzy pomáhají Frantovi rychleji najít správná data. Můžete vybrat více oblastí současně. Pokud si nejste jistí nebo se dotaz týká celého systému, zvolte Dotaz nad celou databází; takové zpracování může trvat déle a stát více.</p>
            <p>Model ovlivňuje rychlost, cenu a schopnost řešit složité zadání. Terra je doporučená běžná volba. Požadovaný výstup určuje, zda má Franta připravit text, tabulku nebo graf.</p>
        </div>
    </details>

    <form class="ai_analytik_form" data-ai-analytik-form>
        <div class="ai_analytik_option_grid">
            <fieldset class="ai_analytik_option_group">
                <legend>Co budeme analyzovat</legend>
                <div class="ai_analytik_options ai_analytik_area_options">
                    <?php foreach (cb_ai_analytik_povolene_oblasti() as $area => $label): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="oblasti[]"
                                value="<?= h($area) ?>"
                                <?= $area === CB_AI_ANALYTIK_VYCHOZI_OBLAST ? ' checked' : '' ?>
                                data-ai-analytik-oblast="<?= h($area) ?>"
                            >
                            <?= h($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="ai_analytik_option_group ai_analytik_model_group">
                <legend>Model</legend>
                <div class="ai_analytik_options ai_analytik_model_options">
                    <?php foreach (cb_ai_analytik_povolene_modely() as $model): ?>
                        <label
                            title="<?= h($aiAnalytikModelTooltips[$model]) ?>"
                            data-cb-tooltip-multiline="1"
                        >
                            <input
                                type="radio"
                                name="model"
                                value="<?= h($model) ?>"
                                <?= $model === CB_AI_ANALYTIK_VYCHOZI_MODEL ? ' checked' : '' ?>
                                data-ai-analytik-model
                            >
                            <?= h($model) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="ai_analytik_option_group">
                <legend>Požadovaný výstup</legend>
                <div class="ai_analytik_options ai_analytik_output_options">
                    <label><input type="checkbox" name="vystup_text" checked data-ai-analytik-vystup="text"> Text</label>
                    <label><input type="checkbox" name="vystup_tabulka" checked data-ai-analytik-vystup="tabulka"> Tabulka</label>
                    <label><input type="checkbox" name="vystup_graf" data-ai-analytik-vystup="graf"> Graf</label>
                </div>
            </fieldset>
        </div>

        <div class="ai_analytik_prompt_row">
            <div class="ai_analytik_prompt_field">
                <label for="ai_analytik_prompt">Dotaz</label>
                <textarea
                    id="ai_analytik_prompt"
                    name="prompt"
                    rows="3"
                    placeholder="Napište, co chcete zjistit."
                    required
                    data-ai-analytik-prompt
                ></textarea>
            </div>
            <button type="submit" class="head_task_btn" data-ai-analytik-submit>Odeslat</button>
        </div>
    </form>

    <div class="ai_analytik_status" role="status" aria-live="polite" data-ai-analytik-status hidden></div>

    <div class="ai_analytik_results" aria-live="polite" data-ai-analytik-results></div>
</div>
