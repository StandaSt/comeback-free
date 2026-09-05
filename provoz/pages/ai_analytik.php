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
$aiAnalytikEndpoint = cb_root_url('ai_analytik_stream.php');
$aiAnalytikPdfEndpoint = cb_root_url('provoz/lib/ai_analytik_export_pdf.php');
$aiAnalytikEmailEndpoint = cb_root_url('provoz/lib/ai_analytik_export_email.php');
$aiAnalytikModelTooltips = [
    'gpt-5.6-luna' => "rychlý\nlevný\npro jednoduché dotazy",
    'gpt-5.6-terra' => "ideální pro většinu analýz\nekonomicky vyvážená volba",
    'gpt-5.6-sol' => "pro složité kombinované analýzy\npomalejší ale důkladnější\ndražší model",
    'gpt-6-astra' => "nejvýkonnější model\npro nejnáročnější analýzy\nnejdražší model",
];
$aiAnalytikModely = cb_ai_analytik_modely_uzivatele();
if ($aiAnalytikModely === []) {
    ?>
    <div class="ai_analytik_error" role="alert">Nemáte povolený žádný model AI analytika.</div>
    <?php
    return;
}
$aiAnalytikNazvyModelu = cb_ai_analytik_nazvy_modelu(db());
$aiAnalytikVychoziModel = in_array(CB_AI_ANALYTIK_VYCHOZI_MODEL, $aiAnalytikModely, true)
    ? CB_AI_ANALYTIK_VYCHOZI_MODEL
    : $aiAnalytikModely[0];
$aiAnalytikRoky = cb_ai_analytik_dostupne_roky(db());
if ($aiAnalytikRoky === []) {
    throw new RuntimeException('V databázi nejsou dostupné žádné roky objednávek.');
}
$aiAnalytikVychoziRok = in_array((int)date('Y'), $aiAnalytikRoky, true)
    ? (int)date('Y')
    : (int)$aiAnalytikRoky[0];
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
    <details id="ai_analytik_guide" class="ai_analytik_guide" data-ai-analytik-guide>
        <summary hidden>Jak Frantu používat</summary>
        <div class="ai_analytik_guide_content">
            <h2>Jak Frantu používat</h2>
            <p>Napište běžnou češtinou, co chcete nad daty informačního systému zjistit. Čím přesnější bude zadání, tím přesnější může být výsledek.</p>
            <p>Franta si podle dotazu sám vybere potřebné databázové katalogy a podle potřeby zkombinuje více částí systému.</p>
            <p>Vyberte roky, se kterými má analytik pracovat. U nejasného zadání může ukázat nejvýše tři relevantní varianty, nebo vás požádat o upřesnění.</p>
            <p>Model ovlivňuje rychlost, cenu a schopnost řešit složité zadání. Terra je doporučená běžná volba. Požadovaný výstup určuje, zda má Franta připravit text, tabulku nebo graf.</p>
        </div>
    </details>

    <?php if (isset($cbAiAnalytikPristup) && $cbAiAnalytikPristup !== []): ?>
        <details id="ai_analytik_access" class="ai_analytik_access" data-ai-analytik-access>
            <summary hidden>Kdo má přístup</summary>
            <div class="ai_analytik_access_panel">
                <h2>Kdo má přístup</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Jméno</th>
                            <th>Počet promptů</th>
                            <th>Využitý čas</th>
                            <th>Spotřeba tokenů</th>
                            <th>Cena</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cbAiAnalytikPristup as $row): ?>
                            <?php
                            $seconds = (int)round(((int)$row['duration_ms']) / 1000);
                            $duration = $seconds >= 3600
                                ? intdiv($seconds, 3600) . ' h ' . intdiv($seconds % 3600, 60) . ' min'
                                : ($seconds >= 60 ? intdiv($seconds, 60) . ' min ' . ($seconds % 60) . ' s' : $seconds . ' s');
                            $cost = (float)$row['cost_usd'];
                            ?>
                            <tr>
                                <td><?= h((string)$row['jmeno']) ?></td>
                                <td><?= number_format((int)$row['prompty'], 0, ',', "\u{00A0}") ?></td>
                                <td><?= h($duration) ?></td>
                                <td><?= number_format((int)$row['total_tokens'], 0, ',', "\u{00A0}") ?></td>
                                <td>$<?= number_format($cost, 4, '.', '') ?> (<?= number_format($cost * 20.8, 2, ',', "\u{00A0}") ?> Kč)</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endif; ?>

    <details id="ai_analytik_model_info" class="ai_analytik_model_info" data-ai-analytik-model-info>
        <summary hidden>Informace o modelech AI které můžete použít</summary>
        <div class="ai_analytik_model_info_content">
            <h2>Informace o modelech AI které můžete použít</h2>
            <table>
                <thead>
                    <tr>
                        <th>Model</th>
                        <th>Použití</th>
                        <th>Cena za 1 mil. tokenů</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Luna</td>
                        <td>Rychlý, levný pro jednoduché úkoly.</td>
                        <td>$0,20 vstup / $1,20 výstup</td>
                    </tr>
                    <tr>
                        <td>Terra</td>
                        <td>Doporučený pro většinu analýz. Vyvážený poměr ceny, rychlosti a kvality.</td>
                        <td>$2 vstup / $12 výstup</td>
                    </tr>
                    <tr>
                        <td>Sol</td>
                        <td>Pro složité kombinované analýzy. Důkladnější, ale pomalejší a dražší.</td>
                        <td>$4 vstup / $20 výstup</td>
                    </tr>
                    <tr>
                        <td>Astra</td>
                        <td>Pro nejnáročnější analýzy a složité zadání. Nejsilnější a nejdražší model.</td>
                        <td>$10 vstup / $50 výstup</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </details>

    <form class="ai_analytik_form" data-ai-analytik-form>
        <div class="ai_analytik_option_grid">
            <fieldset class="ai_analytik_option_group ai_analytik_year_group">
                <legend>Roky</legend>
                <div class="ai_analytik_options ai_analytik_year_options">
                    <?php foreach ($aiAnalytikRoky as $year): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="roky[]"
                                value="<?= $year ?>"
                                <?= $year === $aiAnalytikVychoziRok ? ' checked' : '' ?>
                                data-ai-analytik-year
                            >
                            <?= $year ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="ai_analytik_option_group ai_analytik_model_group">
                <legend>
                    Model
                    <button
                        type="button"
                        class="ai_analytik_model_info_toggle"
                        aria-controls="ai_analytik_model_info"
                        aria-expanded="false"
                        data-ai-analytik-model-info-toggle
                    >(detail)</button>
                </legend>
                <div class="ai_analytik_options ai_analytik_model_options">
                    <?php foreach ($aiAnalytikModely as $model): ?>
                        <label
                            title="<?= h($aiAnalytikModelTooltips[$model]) ?>"
                            data-cb-tooltip-multiline="1"
                        >
                            <input
                                type="radio"
                                name="model"
                                value="<?= h($model) ?>"
                                <?= $model === $aiAnalytikVychoziModel ? ' checked' : '' ?>
                                data-ai-analytik-model
                            >
                            <?= h($aiAnalytikNazvyModelu[$model] ?? $model) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="ai_analytik_option_group ai_analytik_ambiguity_group">
                <legend>Při více možnostech</legend>
                <div class="ai_analytik_options ai_analytik_ambiguity_options">
                    <label>
                        <input
                            type="radio"
                            name="nejistota"
                            value="varianty"
                            checked
                            data-ai-analytik-ambiguity
                        >
                        Zobrazit max. 3 výsledky
                    </label>
                    <label>
                        <input
                            type="radio"
                            name="nejistota"
                            value="upresnit"
                            data-ai-analytik-ambiguity
                        >
                        AI se zeptá
                    </label>
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

    <dialog class="ai_analytik_astra_dialog" data-ai-analytik-astra-dialog>
        <h2>Potvrzení modelu Astra</h2>
        <p>
            Je zvolen nejvýkonnější a tedy nejdražší model.<br>
            Zvládne podrobné a rozsáhlé analýzy.<br>
            <span class="ai_analytik_astra_expect">Očekávejte</span><br>
            – velmi podrobný výsledek<br>
            – delší čas zpracování požadavku<br>
            – <span class="ai_analytik_astra_cost">podstatně vyšší cenu</span> než ostatní modely
        </p>
        <div class="ai_analytik_astra_actions">
            <button type="button" class="head_task_btn" data-ai-analytik-astra-action="confirm">Ano, chci Astru</button>
            <button type="button" class="head_task_btn" data-ai-analytik-astra-action="sol">Použij Sol</button>
            <button type="button" class="head_task_btn" data-ai-analytik-astra-action="terra">Použij Terra</button>
            <button type="button" class="head_task_btn" data-ai-analytik-astra-action="back">Ještě si to rozmyslím</button>
        </div>
    </dialog>

    <div class="ai_analytik_status" role="status" aria-live="polite" data-ai-analytik-status hidden></div>
    <button type="button" class="head_task_btn ai_analytik_cancel" data-ai-analytik-cancel hidden>Zastavit analýzu</button>

    <div class="ai_analytik_results" aria-live="polite" data-ai-analytik-results></div>
</div>
