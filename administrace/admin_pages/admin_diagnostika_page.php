<?php
/* Administrace: pripravi casovy stav mereni, filtry a sparovane podrobnosti pro kompaktni prehled diagnostiky. */
declare(strict_types=1);
require_once __DIR__.'/../admin_lib/admin_diagnostika.php';
require_once __DIR__.'/../../common/lib/ochrana_crf.php';
if (!cb_diag_admin_allowed()) {http_response_code(403);echo '<p>Diagnostika vyžaduje oprávnění Administrace a Export DB.</p>';return;}
$diagConfig=cb_diag_config();
$diagDay=is_string($_GET['diag_day']??null)?$_GET['diag_day']:date('Y-m-d');
$diagUser=max(0,(int)($_GET['diag_user']??0));
$diagModule=in_array($_GET['diag_module']??'',['email','co_link'],true)?$_GET['diag_module']:'';
$diagSlow=max(0,min(3600000,(float)($_GET['diag_slow']??0)));
$diagKind=in_array($_GET['diag_kind']??'action',['','action','request','server'],true)?($_GET['diag_kind']??'action'):'action';
$diagRows=cb_admin_diag_rows($diagDay,$diagUser,$diagModule,$diagSlow,$diagKind);
$diagNotice=(string)($_SESSION['cb_diag_notice']??'');unset($_SESSION['cb_diag_notice']);
require __DIR__.'/../admin_includes/admin_diagnostika_prehled.php';
