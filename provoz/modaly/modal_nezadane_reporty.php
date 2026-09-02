<?php
// modaly/modal_nezadane_reporty.php * Upozorneni na chybejici denni reporty
declare(strict_types=1);

if (!function_exists('cb_nezadane_reporty_h')) {
    http_response_code(404);
    exit;
}

$contentLines = preg_split('/\R/u', trim((string)$notificationContent));
if (!is_array($contentLines)) {
    $contentLines = [(string)$notificationContent];
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= cb_nezadane_reporty_h($notificationTitle) ?></title>
  <link rel="stylesheet" href="<?= cb_nezadane_reporty_h(cb_public_url('style/modal_alert.css')) ?>">
  <style>
    .modal{
      width:min(335px, 100%);
    }
    .modal-logo{
      display:flex;
      align-items:center;
      justify-content:center;
    }
    .modal-logo img{
      width:100%;
      height:100%;
      object-fit:contain;
      object-position:center center;
      padding:7px;
      display:block;
    }
    .report-alert-title{
      color:#c00;
      font-size:26px;
      font-weight:900;
      line-height:1.1;
      white-space:nowrap;
    }
    .report-alert-box{
      margin-top:12px;
      padding:14px;
      border-radius:14px;
      background:rgba(220,38,38,.08);
      border:1px solid rgba(220,38,38,.20);
      color:#0f172a;
      font-size:15px;
      font-weight:700;
      line-height:1.5;
      word-break:break-word;
      font-family:"Segoe UI", "Trebuchet MS", Arial, sans-serif;
    }
    .report-alert-line + .report-alert-line{
      margin-top:0;
    }
    .report-alert-gap{
      height:10px;
    }
    .report-alert-branch{
      font-weight:900;
    }
    .report-alert-heading{
      color:#c00;
      font-weight:900;
    }
    .report-alert-footer{
      margin-top:14px;
    }
    .report-alert-acknowledged{
      margin-top:14px;
      color:#166534;
      font-weight:800;
      text-align:center;
    }
  </style>
</head>
<body class="modal-page">

  <div class="modal" role="dialog" aria-modal="true" aria-label="Upozornění na chybějící reporty">
    <button type="button" class="modal-x" id="btnClose" aria-label="Zavřít">×</button>

    <div class="modal-head">
      <div class="modal-logo">
        <img src="<?= cb_nezadane_reporty_h(cb_public_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title report-alert-title"><?= cb_nezadane_reporty_h($notificationTitle) ?></p>
        <p class="modal-sub">Kontrola denních reportů</p>
      </div>
    </div>

    <div class="report-alert-box">
      <?php if (is_array($notificationData)) { ?>
        <?php $notificationBranches = (array)($notificationData['branches'] ?? []); ?>
        <?php $notificationReportCount = 0; ?>
        <?php foreach ($notificationBranches as $branchData) { $notificationReportCount += count((array)($branchData['dates'] ?? [])); } ?>
        <div class="report-alert-line report-alert-heading"><?= $notificationReportCount === 1 ? 'Zjištěn chybějící denní report:' : 'Zjištěny chybějící denní reporty:' ?></div>
        <div class="report-alert-gap"></div>
        <?php foreach ($notificationBranches as $branchIndex => $branchData) { ?>
          <?php if ($branchIndex > 0) { ?><div class="report-alert-gap"></div><?php } ?>
          <div class="report-alert-line report-alert-branch"><?= cb_nezadane_reporty_h((string)($branchData['branch'] ?? '')) ?></div>
          <?php foreach ((array)($branchData['dates'] ?? []) as $date) { ?>
            <?php $dateValue = DateTimeImmutable::createFromFormat('Y-m-d', (string)$date, new DateTimeZone('Europe/Prague')); ?>
            <div class="report-alert-line"><?= cb_nezadane_reporty_h($dateValue instanceof DateTimeImmutable ? $dateValue->format('j.n.Y') : (string)$date) ?></div>
          <?php } ?>
        <?php } ?>

        <div class="report-alert-footer">
          <div class="report-alert-line">Tuto informaci jste dostal/a, protože:</div>
          <div class="report-alert-line">- jste měl/a daný den směnu</div>
          <div class="report-alert-line">- jste vedoucí pobočky</div>
          <div class="report-alert-line">- jste odpovědný manager</div>
          <div class="report-alert-gap"></div>
          <div class="report-alert-line">Zajistěte, aby se reporty opravdu zadávaly každý den.</div>
        </div>
      <?php } else { ?>
        <?php foreach ($contentLines as $line) { ?>
          <?php if (trim((string)$line) === '') { ?>
            <div class="report-alert-gap"></div>
          <?php } else { ?>
            <div class="report-alert-line"><?= cb_nezadane_reporty_h(trim((string)$line)) ?></div>
          <?php } ?>
        <?php } ?>
      <?php } ?>
    </div>

    <?php if (!$notificationAcknowledged && is_array($notification)) { ?>
      <div class="modal-spacer"></div>
      <form method="post">
        <input type="hidden" name="action" value="acknowledge">
        <button class="modal-btn primary" type="submit">Beru na vědomí</button>
      </form>
    <?php } elseif ($notificationAcknowledged) { ?>
      <div class="report-alert-acknowledged">Vzato na vědomí</div>
    <?php } ?>
  </div>

<script>
(function(){
  var btnClose = document.getElementById('btnClose');
  if (btnClose) {
    btnClose.addEventListener('click', function(){ location.replace('about:blank'); });
  }
})();
</script>

</body>
</html>
<?php
// modaly/modal_nezadane_reporty.php * Konec souboru
