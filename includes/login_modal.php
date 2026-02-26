<?php
// includes/login_modal.php * Verze: V1 * Aktualizace: 25.2.2026
declare(strict_types=1);
?>
<style>
  :root{
    --cb-ovl: rgba(8, 12, 18, .72);
    --cb-card: #ffffff;
    --cb-text: #0f172a;
    --cb-muted: rgba(15, 23, 42, .70);
    --cb-bd: rgba(15, 23, 42, .14);
    --cb-shadow: 0 18px 44px rgba(0,0,0,.22);
    --cb-radius: 18px;
    --cb-gap: 12px;
    --cb-focus: rgba(59,130,246,.35);
    --cb-login-bg: #eaf2fb;
  }

  body.cb-login-locked{
    overflow: hidden;
  }

  .cb-login-fill{
    min-height: calc(100vh - 92px);
    background: var(--cb-login-bg);
  }

  body.cb-login-locked .cb-login-fill{
    filter: blur(20px);
  }

  #cb-login-overlay{
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: var(--cb-ovl);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
  }

  .cb-login-card{
    width: min(420px, 100%);
    background: var(--cb-card);
    color: var(--cb-text);
    border: 1px solid var(--cb-bd);
    border-radius: var(--cb-radius);
    box-shadow: var(--cb-shadow);
    overflow: hidden;
  }

  .cb-login-top{
    padding: 18px 18px 12px 18px;
    display: flex;
    gap: 12px;
    align-items: center;
    border-bottom: 1px solid var(--cb-bd);
    background: linear-gradient(180deg, rgba(2,132,199,.06), rgba(2,132,199,0));
  }

  .cb-logo{
    width: 55px;
    height: 55px;
    border-radius: 12px;
    border: 1px solid var(--cb-bd);
    background: #fff;
    display: grid;
    place-items: center;
    overflow: hidden;
    flex: 0 0 auto;
  }
  .cb-logo img{
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 0px;
  }

  .cb-title{
    display: grid;
    gap: 2px;
    min-width: 0;
  }
  .cb-title strong{
    font-size: 15px;
    letter-spacing: .2px;
    line-height: 1.2;
  }
  .cb-title span{
    font-size: 12px;
    color: var(--cb-muted);
    line-height: 1.25;
  }

  .cb-login-body{
    padding: 14px 18px 18px 18px;
    display: grid;
    gap: var(--cb-gap);
  }

  .cb-info{
    font-size: 12.5px;
    color: var(--cb-muted);
    line-height: 1.35;
    border: 1px dashed rgba(15, 23, 42, .20);
    border-radius: 14px;
    padding: 10px 12px;
    background: rgba(248,250,252,.75);
  }

  .cb-field{
    display: grid;
    gap: 6px;
  }
  .cb-field label{
    font-size: 12px;
    color: var(--cb-muted);
  }
  .cb-input{
    width: 100%;
    padding: 11px 12px;
    border-radius: 14px;
    border: 1px solid var(--cb-bd);
    font-size: 14px;
    outline: none;
    background: #fff;
  }
  .cb-input:focus{
    box-shadow: 0 0 0 4px var(--cb-focus);
    border-color: rgba(59,130,246,.55);
  }

  .cb-actions{
    display: flex;
    gap: 10px;
    margin-top: 4px;
  }
  .cb-btn{
    border: 1px solid var(--cb-bd);
    background: #fff;
    color: var(--cb-text);
    padding: 10px 12px;
    border-radius: 14px;
    font-size: 13px;
    cursor: pointer;
    flex: 1 1 auto;
  }
  .cb-btn-primary{
    background: linear-gradient(180deg, rgba(37,99,235,.96), rgba(37,99,235,.86));
    border-color: rgba(37,99,235,.35);
    color: #fff;
    font-weight: 600;
  }
  .cb-btn:hover{
    filter: brightness(0.985);
  }
</style>

<div id="cb-login-overlay" aria-modal="true" role="dialog">
  <div class="cb-login-card">
    <div class="cb-login-top">
      <div class="cb-logo" aria-hidden="true">
        <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div class="cb-title">
        <strong>Informační systém Comeback</strong>
        <span>Přihlášení uživatele</span>
      </div>
    </div>

    <div class="cb-login-body">
      <div class="cb-info">
        Vstupujete do IS společnosti <strong>…</strong>.<br>
        K přihlášení prosím použijte přihlašovací údaje ze systému pro plánování směn.
      </div>

      <form method="post" action="<?= h(cb_url('lib/login_smeny.php')) ?>">
        <div class="cb-field">
          <label for="cb_email">E-mail</label>
          <input class="cb-input"
                 id="cb_email"
                 name="email"
                 type="email"
                 autocomplete="username"
                 required>
        </div>

        <div class="cb-field">
          <label for="cb_pass">Heslo</label>
          <input class="cb-input"
                 id="cb_pass"
                 name="heslo"
                 type="password"
                 autocomplete="current-password"
                 required>
        </div>

        <div class="cb-actions">
          <button class="cb-btn cb-btn-primary" type="submit">Přihlásit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function(){
    document.body.classList.add('cb-login-locked');

    var email = document.getElementById('cb_email');
    if (email) {
      setTimeout(function(){ email.focus(); }, 60);
    }
  })();
</script>
<?php
/* includes/login_modal.php * Verze: V1 * Aktualizace: 25.2.2026 * Počet řádků: 219 */
// Konec souboru