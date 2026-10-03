<?php
declare(strict_types=1);

/*
 * Ucel souboru: Po vyzadani prihlaseni bez mobilu zobrazit samostatnou
 * jednorazovou informaci o odeslanem e-mailu bez dalsich ovladacich prvku.
 */
unset($_SESSION['cb_login_email_odeslan']);
?>
<div class="modal-overlay" role="status" aria-live="polite" aria-label="Přihlašovací odkaz odeslán">
  <div class="modal modal-login-email-sent">
    <p>Na Váš email byl odeslán přihlašovací odkaz. Zkontrolujte si emailovou schránku a odkaz použijte. Má platnost pouze 5 minut.</p>
  </div>
</div>
