<?php
// modaly/modal_prehled_smen_export.php * Verze: V6 * Aktualizace: 04.06.2026
declare(strict_types=1);

$psExportPdfUrl = (string)($psExportPdfUrl ?? '');
$psExportTxtUrl = (string)($psExportTxtUrl ?? '');
$psExportXlsxUrl = (string)($psExportXlsxUrl ?? '');
?>
<div id="psExportModal" class="cb_cardmode_modal is-hidden" data-ps-export-modal="1" data-pdf-url="<?= h($psExportPdfUrl) ?>" data-txt-url="<?= h($psExportTxtUrl) ?>" data-xlsx-url="<?= h($psExportXlsxUrl) ?>" aria-hidden="true">
  <div class="cb_cardmode_dialog" role="dialog" aria-modal="true" aria-labelledby="psExportTitle" style="width:min(365px,96vw);padding:10px 12px 10px;">
    <div class="cb_cardmode_head">
      <div class="cb_cardmode_head_text" style="padding-top:0;">
        <h4 id="psExportTitle" class="cb_cardmode_title" style="color:var(--clr_modra_5);margin-bottom:8px;">Export přehledu směn</h4>
        <div class="cb_cardmode_text" style="min-height:0;">
          <div style="display:grid;gap:6px;">
            <div style="display:flex;align-items:center;gap:12px;border:1px solid var(--clr_seda_2);border-radius:8px;padding:5px 9px;white-space:nowrap;">
              <strong style="min-width:54px;color:var(--clr_seda_4);">Formát</strong>
              <label style="display:inline-flex;align-items:center;gap:5px;"><input type="radio" name="ps_export_format" value="pdf" checked> PDF</label>
              <label style="display:inline-flex;align-items:center;gap:5px;"><input type="radio" name="ps_export_format" value="xlsx"> XLSX</label>
              <label style="display:inline-flex;align-items:center;gap:5px;"><input type="radio" name="ps_export_format" value="txt"> TXT</label>
            </div>
            <div style="display:flex;align-items:center;gap:12px;border:1px solid var(--clr_seda_2);border-radius:8px;padding:5px 9px;white-space:nowrap;">
              <strong style="min-width:54px;color:var(--clr_seda_4);">Rozsah</strong>
              <label style="display:inline-flex;align-items:center;gap:5px;"><input type="radio" name="ps_export_scope" value="summary" checked> měsíční souhrn</label>
              <label style="display:inline-flex;align-items:center;gap:5px;"><input type="radio" name="ps_export_scope" value="detail"> detailní přehled</label>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="cb_cardmode_actions" style="margin-top:10px;justify-content:space-between;">
      <button type="button" class="btn cb_cardmode_btn" style="min-width:120px;min-height:30px;background:var(--clr_ruzova_4);border-color:var(--clr_ruzova_1);color:var(--clr_cervena);" data-ps-export-close>Zpět</button>
      <button type="button" class="btn cb_cardmode_btn" style="min-height:30px;" data-ps-export-submit>Export</button>
    </div>
    <div class="is-hidden" data-ps-export-status aria-live="polite" style="margin-top:7px;text-align:center;color:var(--clr_modra_5);font-size:var(--fs_13);font-weight:600;">Připravuji export ...</div>
  </div>
</div>
<?php
/* modaly/modal_prehled_smen_export.php * Verze: V6 * Aktualizace: 04.06.2026 */
// Konec souboru
