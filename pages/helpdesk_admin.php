<?php
// pages/helpdesk_admin.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

require_once __DIR__ . '/../lib/session_boot.php';
require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/helpdesk_prava.php';

if (empty($_SESSION['login_ok'])) {
    echo '<section class="card_box ram_normal bg_bila zaobleni_12 odstup_vnitrni_14"><p>Nutné přihlášení.</p></section>';
    return;
}

if (!cb_helpdesk_is_admin()) {
    echo '<section class="card_box ram_normal bg_bila zaobleni_12 odstup_vnitrni_14"><p>Jen pro admina.</p></section>';
    return;
}

$conn = db();
$items = [];
$stavFiltr = trim((string)($_GET['helpdesk_stav'] ?? ''));
$sqlWhere = '';
if ($stavFiltr !== '' && in_array($stavFiltr, ['novy', 'resi_se', 'vyreseno', 'zamitnuto'], true)) {
    $sqlWhere = 'WHERE h.stav = ?';
}

$sql = '
    SELECT h.id_helpdesk, h.id_user_zalozil, h.typ, h.stav, h.verejny, h.predmet, h.vytvoreno, h.upraveno, h.uzavreno,
           u.jmeno, u.prijmeni,
           (SELECT COUNT(*) FROM helpdesk_zprava z WHERE z.id_helpdesk = h.id_helpdesk) AS pocet_zprav,
           (SELECT COUNT(*) FROM helpdesk_sledujici s WHERE s.id_helpdesk = h.id_helpdesk) AS pocet_sledujicich,
           (SELECT COUNT(*) FROM helpdesk_snapshot sn WHERE sn.id_helpdesk = h.id_helpdesk) AS pocet_snapshotu
    FROM helpdesk h
    LEFT JOIN `user` u ON u.id_user = h.id_user_zalozil
    ' . $sqlWhere . '
    ORDER BY FIELD(h.stav, \'novy\', \'resi_se\', \'vyreseno\', \'zamitnuto\'), h.upraveno DESC, h.vytvoreno DESC
    LIMIT 300
';
$stmt = $conn->prepare($sql);
if ($stmt instanceof mysqli_stmt) {
    if ($sqlWhere !== '') {
        $stmt->bind_param('s', $stavFiltr);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
        $res->free();
    }
    $stmt->close();
}

$ajaxDetail = cb_url('ajax/helpdesk_detail.php');
$ajaxMessage = cb_url('ajax/helpdesk_zprava_pridat.php');
$ajaxStav = cb_url('ajax/helpdesk_stav_zmenit.php');
?>
<section class="card_box ram_normal bg_bila zaobleni_12 odstup_vnitrni_14" id="helpdesk-admin-page">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;">
    <h2 style="margin:0;">HelpDesk</h2>
    <form method="get" style="display:flex;gap:8px;align-items:center;">
      <label for="helpdesk_stav">Stav</label>
      <select name="helpdesk_stav" id="helpdesk_stav">
        <option value="">Vše</option>
        <option value="novy" <?php if ($stavFiltr === 'novy') { echo 'selected'; } ?>>Nový</option>
        <option value="resi_se" <?php if ($stavFiltr === 'resi_se') { echo 'selected'; } ?>>Řeší se</option>
        <option value="vyreseno" <?php if ($stavFiltr === 'vyreseno') { echo 'selected'; } ?>>Vyřešeno</option>
        <option value="zamitnuto" <?php if ($stavFiltr === 'zamitnuto') { echo 'selected'; } ?>>Zamítnuto</option>
      </select>
      <button type="submit" class="btn">Filtrovat</button>
    </form>
  </div>

  <div style="overflow:auto;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">ID</th>
          <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Stav</th>
          <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Předmět</th>
          <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Autor</th>
          <th style="text-align:right;border-bottom:1px solid #ddd;padding:6px;">Zprávy</th>
          <th style="text-align:right;border-bottom:1px solid #ddd;padding:6px;">Snapshoty</th>
          <th style="text-align:right;border-bottom:1px solid #ddd;padding:6px;">Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <?php
          $autor = trim((string)($item['jmeno'] ?? '') . ' ' . (string)($item['prijmeni'] ?? ''));
          if ($autor === '') {
              $autor = 'ID ' . (string)(int)($item['id_user_zalozil'] ?? 0);
          }
          ?>
          <tr>
            <td style="border-bottom:1px solid #eee;padding:6px;">#<?= h((string)$item['id_helpdesk']) ?></td>
            <td style="border-bottom:1px solid #eee;padding:6px;"><?= h((string)$item['stav']) ?></td>
            <td style="border-bottom:1px solid #eee;padding:6px;"><?= h((string)$item['predmet']) ?></td>
            <td style="border-bottom:1px solid #eee;padding:6px;"><?= h($autor) ?></td>
            <td style="border-bottom:1px solid #eee;padding:6px;text-align:right;"><?= h((string)$item['pocet_zprav']) ?></td>
            <td style="border-bottom:1px solid #eee;padding:6px;text-align:right;"><?= h((string)$item['pocet_snapshotu']) ?></td>
            <td style="border-bottom:1px solid #eee;padding:6px;text-align:right;"><button type="button" class="btn" data-hd-detail="<?= h((string)$item['id_helpdesk']) ?>">Otevřít</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div id="hd-admin-detail" style="display:none;border:1px solid #bbb;border-radius:10px;padding:12px;margin-top:14px;"></div>
</section>

<script>
(function(){
  var root = document.getElementById('helpdesk-admin-page');
  if (!root) { return; }
  var detailBox = document.getElementById('hd-admin-detail');

  function text(v) {
    if (v === null || v === undefined) { return ''; }
    return String(v);
  }

  function esc(v) {
    return text(v).replace(/[&<>'"]/g, function(ch){
      return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch];
    });
  }

  function postJson(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(data)
    }).then(function(r){ return r.json(); });
  }

  function loadDetail(id) {
    fetch('<?= h($ajaxDetail) ?>?id_helpdesk=' + encodeURIComponent(id))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok) { alert(data.err || 'Chyba'); return; }
        var html = '<h3 style="margin-top:0;">#' + esc(data.ticket.id_helpdesk) + ' ' + esc(data.ticket.predmet) + '</h3>';
        html += '<p><strong>Stav:</strong> ' + esc(data.ticket.stav) + ' · <strong>Typ:</strong> ' + esc(data.ticket.typ) + ' · <strong>Veřejný:</strong> ' + esc(data.ticket.verejny) + '</p>';
        html += '<div style="display:flex;gap:8px;margin:8px 0;align-items:center;">';
        html += '<select id="hd-admin-stav"><option value="novy">novy</option><option value="resi_se">resi_se</option><option value="vyreseno">vyreseno</option><option value="zamitnuto">zamitnuto</option></select>';
        html += '<button type="button" class="btn" data-hd-change-state="' + esc(id) + '">Změnit stav</button>';
        html += '</div>';
        html += '<div style="display:grid;gap:8px;margin:10px 0;">';
        data.zpravy.forEach(function(z){
          var autor = esc(text(z.jmeno) + ' ' + text(z.prijmeni));
          if (autor.trim() === '') { autor = 'ID ' + esc(z.id_user); }
          html += '<div style="border-top:1px solid #eee;padding-top:8px;">';
          html += '<strong>' + autor + '</strong> <span style="color:#777;font-size:12px;">' + esc(z.vytvoreno) + '</span>';
          html += '<div style="white-space:pre-wrap;">' + esc(z.zprava) + '</div>';
          html += '</div>';
        });
        html += '</div>';
        html += '<textarea id="hd-admin-reply-text" rows="4" style="width:100%;"></textarea>';
        html += '<div style="margin-top:8px;display:flex;gap:8px;justify-content:flex-end;">';
        html += '<button type="button" class="btn" data-hd-send-reply="' + esc(id) + '">Odeslat odpověď</button>';
        html += '<button type="button" class="btn" data-hd-close-detail="1">Zavřít</button>';
        html += '</div>';
        detailBox.innerHTML = html;
        detailBox.style.display = 'block';
        document.getElementById('hd-admin-stav').value = data.ticket.stav;
      });
  }

  root.addEventListener('click', function(e){
    var target = e.target;
    if (!target) { return; }

    if (target.getAttribute('data-hd-close-detail') === '1') {
      detailBox.style.display = 'none';
    }

    var detailId = target.getAttribute('data-hd-detail');
    if (detailId) {
      loadDetail(detailId);
    }

    var replyId = target.getAttribute('data-hd-send-reply');
    if (replyId) {
      var reply = document.getElementById('hd-admin-reply-text');
      postJson('<?= h($ajaxMessage) ?>', {id_helpdesk: replyId, zprava: reply.value}).then(function(data){
        if (!data.ok) { alert(data.err || 'Chyba'); return; }
        loadDetail(replyId);
      });
    }

    var stateId = target.getAttribute('data-hd-change-state');
    if (stateId) {
      var stav = document.getElementById('hd-admin-stav').value;
      postJson('<?= h($ajaxStav) ?>', {id_helpdesk: stateId, stav: stav}).then(function(data){
        if (!data.ok) { alert(data.err || 'Chyba'); return; }
        loadDetail(stateId);
      });
    }
  });
})();
</script>
<?php
// pages/helpdesk_admin.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
