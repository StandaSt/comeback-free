<?php
// includes/select_card_ikon.php * Verze: V2 * Aktualizace: 07.04.2026
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../db/db_connect.php';
require_once __DIR__ . '/../lib/app.php';

$idUser = (int)(($_SESSION['cb_user']['id_user'] ?? 0));
$idKarta = (int)($_REQUEST['id_karta'] ?? 0);
$selectedIkon = (int)($_REQUEST['ikon'] ?? 0);
$selectedTab = trim((string)($_REQUEST['tab'] ?? ''));
$lastAction = trim((string)($_POST['cb_action'] ?? ''));
$ok = 0;
$msg = '';

$conn = db_connect();

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string)($_POST['cb_action'] ?? '') === 'save'
    && $idUser > 0
    && $idKarta > 0
    && $selectedIkon > 0
) {
    $stmt = $conn->prepare(
        'INSERT INTO user_card_set (id_user, id_karta, ikon)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE ikon = VALUES(ikon)'
    );
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('iii', $idUser, $idKarta, $selectedIkon);
        if ($stmt->execute()) {
            $ok = 1;
            $msg = 'Uloženo.';
        } else {
            $msg = 'Uložení selhalo.';
        }
        $stmt->close();
    } else {
        $msg = 'DB chyba.';
    }
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string)($_POST['cb_action'] ?? '') === 'reset'
    && $idUser > 0
    && $idKarta > 0
) {
    $stmt = $conn->prepare('UPDATE user_card_set SET ikon = NULL WHERE id_user = ? AND id_karta = ?');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('ii', $idUser, $idKarta);
        if ($stmt->execute()) {
            $ok = 1;
            $msg = 'Resetováno.';
            $selectedIkon = 0;
        } else {
            $msg = 'Reset selhal.';
        }
        $stmt->close();
    } else {
        $msg = 'DB chyba.';
    }
}

if ($selectedIkon <= 0 && $idUser > 0 && $idKarta > 0) {
    $stmt = $conn->prepare('SELECT ikon FROM user_card_set WHERE id_user = ? AND id_karta = ? LIMIT 1');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('ii', $idUser, $idKarta);
        $stmt->execute();
        $stmt->bind_result($dbIkon);
        if ($stmt->fetch()) {
            $selectedIkon = (int)$dbIkon;
        }
        $stmt->close();
    }
}

$icons = [];
$tabs = [];
$res = $conn->query('
    SELECT id_ikon, nazev, soubor, poradi
    FROM card_icons
    WHERE aktivni = 1
    ORDER BY poradi ASC, id_ikon ASC
');
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $idIkon = (int)($row['id_ikon'] ?? 0);
        $nazev = trim((string)($row['nazev'] ?? ''));
        $soubor = trim((string)($row['soubor'] ?? ''));
        if ($idIkon <= 0 || $soubor === '') {
            continue;
        }
        $parts = explode('/', str_replace('\\', '/', $soubor));
        $tab = trim((string)($parts[0] ?? 'misc'));
        if ($tab === '') {
            $tab = 'misc';
        }
        if (!isset($tabs[$tab])) {
            $tabs[$tab] = true;
        }
        $icons[] = [
            'id_ikon' => $idIkon,
            'nazev' => $nazev,
            'soubor' => $soubor,
            'tab' => $tab,
        ];
    }
    $res->free();
}

$tabNames = array_keys($tabs);
if ($selectedTab === '' || !in_array($selectedTab, $tabNames, true)) {
    $selectedTab = $tabNames[0] ?? '';
}

$selectedIconSrc = '';
foreach ($icons as $iconRow) {
    if ((int)($iconRow['id_ikon'] ?? 0) === $selectedIkon) {
        $selectedIconSrc = (string)cb_url('/img/card_icons/' . ltrim((string)($iconRow['soubor'] ?? ''), '/'));
        break;
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;padding:8px;font-family:Arial,sans-serif;background:#fff;color:#334155;}
    .title{font-size:12px;color:#475569;margin:0 0 8px 0;}
    .tabs{display:flex;gap:4px;flex-wrap:wrap;margin:0 0 8px 0;}
    .tab{height:18px;line-height:16px;padding:0 6px;border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc;cursor:pointer;font-size:11px;}
    .tab.is-active{border-color:#64748b;background:#e2e8f0;}
    .grid{display:grid;grid-template-columns:repeat(8, 30px);gap:4px;}
    .icon-btn{width:30px;height:30px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0;}
    .icon-btn:hover{background:#f1f5f9;}
    .icon-btn.is-active{border-color:#0f172a;outline:1px solid #0f172a;}
    .icon-btn img{width:20px;height:20px;object-fit:contain;display:block;}
    .bar{display:flex;align-items:center;justify-content:flex-end;margin-top:8px;gap:8px;}
    .btn{height:18px;line-height:16px;padding:0 8px;border:1px solid #94a3b8;border-radius:6px;background:#f8fafc;cursor:pointer;font-size:12px;}
    .ok{font-size:12px;color:#15803d;margin-top:6px;}
    .err{font-size:12px;color:#b91c1c;margin-top:6px;}
  </style>
</head>
<body>

<form method="post" id="cardIconForm">
  <input type="hidden" name="cb_action" value="save" id="cardIconAction">
  <input type="hidden" name="id_karta" value="<?= h((string)$idKarta) ?>">
  <input type="hidden" name="ikon" id="cardIconValue" value="<?= h((string)$selectedIkon) ?>">
  <input type="hidden" name="tab" id="cardIconTab" value="<?= h($selectedTab) ?>">

  <p class="title">Vyber ikonu pro tuto kartu:</p>

  <div class="tabs">
    <?php foreach ($tabNames as $tabName): ?>
      <button type="button" class="tab<?= $selectedTab === $tabName ? ' is-active' : '' ?>" data-tab="<?= h($tabName) ?>"><?= h($tabName) ?></button>
    <?php endforeach; ?>
  </div>

  <?php foreach ($tabNames as $tabName): ?>
    <div class="grid" data-tab-panel="<?= h($tabName) ?>"<?= $selectedTab === $tabName ? '' : ' style="display:none;"' ?>>
      <?php foreach ($icons as $icon): ?>
        <?php if (($icon['tab'] ?? '') !== $tabName) { continue; } ?>
        <?php
          $idIkon = (int)($icon['id_ikon'] ?? 0);
          $soubor = (string)($icon['soubor'] ?? '');
          $nazev = (string)($icon['nazev'] ?? '');
          $src = cb_url('/img/card_icons/' . ltrim($soubor, '/'));
          $active = ($selectedIkon === $idIkon);
        ?>
        <button
          type="button"
          class="icon-btn<?= $active ? ' is-active' : '' ?>"
          data-ikon="<?= h((string)$idIkon) ?>"
          data-src="<?= h((string)$src) ?>"
          data-nazev="<?= h($nazev) ?>"
          title="<?= h($nazev) ?>"
        ><img src="<?= h((string)$src) ?>" alt=""></button>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="bar">
    <button type="submit" class="btn" id="cardIconReset">Reset</button>
    <button type="submit" class="btn">Uložit</button>
  </div>

  <?php if ($msg !== ''): ?>
    <div class="<?= $ok === 1 ? 'ok' : 'err' ?>"><?= h($msg) ?></div>
  <?php endif; ?>
</form>

<script>
(function () {
  var form = document.getElementById('cardIconForm');
  var actionInput = document.getElementById('cardIconAction');
  var iconInput = document.getElementById('cardIconValue');
  var tabInput = document.getElementById('cardIconTab');
  var resetBtn = document.getElementById('cardIconReset');
  var cardId = <?= (int)$idKarta ?>;
  if (!form || !actionInput || !iconInput || !tabInput || !resetBtn || cardId <= 0) return;

  function setActiveIcon(idIkon) {
    document.querySelectorAll('.icon-btn').forEach(function (el) {
      if (!(el instanceof HTMLElement)) return;
      if (String(el.getAttribute('data-ikon') || '') === idIkon) {
        el.classList.add('is-active');
      } else {
        el.classList.remove('is-active');
      }
    });
  }

  function showTab(tab) {
    tabInput.value = tab;
    document.querySelectorAll('.tab').forEach(function (el) {
      if (!(el instanceof HTMLElement)) return;
      if (String(el.getAttribute('data-tab') || '') === tab) {
        el.classList.add('is-active');
      } else {
        el.classList.remove('is-active');
      }
    });
    document.querySelectorAll('[data-tab-panel]').forEach(function (el) {
      if (!(el instanceof HTMLElement)) return;
      if (String(el.getAttribute('data-tab-panel') || '') === tab) {
        el.style.display = 'grid';
      } else {
        el.style.display = 'none';
      }
    });
  }

  function previewInParent(src) {
    try {
      if (!window.parent) return;
      if (typeof window.parent.cbSetCardIconPreview === 'function') {
        window.parent.cbSetCardIconPreview(cardId, src);
        return;
      }
    } catch (e) {}
  }

  function previewResetInParent() {
    try {
      if (!window.parent) return;
      if (typeof window.parent.cbSetCardIconDotsPreview === 'function') {
        window.parent.cbSetCardIconDotsPreview(cardId);
        return;
      }
    } catch (e) {}
  }

  document.querySelectorAll('.tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var t = String(btn.getAttribute('data-tab') || '').trim();
      if (t !== '') {
        showTab(t);
      }
    });
  });

  document.querySelectorAll('.icon-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var idIkon = String(btn.getAttribute('data-ikon') || '').trim();
      var src = String(btn.getAttribute('data-src') || '').trim();
      if (idIkon === '' || src === '') return;
      iconInput.value = idIkon;
      actionInput.value = 'save';
      setActiveIcon(idIkon);
      previewInParent(src);
    });
  });

  resetBtn.addEventListener('click', function () {
    actionInput.value = 'reset';
    iconInput.value = '';
    previewResetInParent();
  });
}());
</script>

<?php if ($ok === 1): ?>
<script>
(function () {
  try {
    if (!window.parent || !window.parent.document) return;
    var cardId = <?= (int)$idKarta ?>;
    var root = window.parent.document.querySelector('[data-card-id="' + String(cardId) + '"]');
    if (!root) return;
    var wrap = root.querySelector('[data-card-pref-wrap]');
    if (!wrap) return;
    var menu = wrap.querySelector('[data-card-pref-menu]');
    var frame = wrap.querySelector('[data-card-pref-frame]');
    var toggle = wrap.querySelector('[data-card-pref-toggle]');
    if (menu) menu.classList.add('is-hidden');
    if (frame) {
      frame.classList.add('is-hidden');
      frame.removeAttribute('src');
    }
    if (toggle instanceof HTMLElement) {
      toggle.setAttribute('aria-expanded', 'false');
      <?php if ($lastAction === 'reset'): ?>
      if (window.parent && typeof window.parent.cbSetCardIconDotsPreview === 'function') {
        window.parent.cbSetCardIconDotsPreview(cardId);
      }
      if (window.parent && typeof window.parent.cbCommitCardIconPreview === 'function') {
        window.parent.cbCommitCardIconPreview(cardId);
      }
      <?php else: ?>
      if (window.parent && typeof window.parent.cbSetCardIconPreview === 'function' && '<?= h($selectedIconSrc) ?>' !== '') {
        window.parent.cbSetCardIconPreview(cardId, '<?= h($selectedIconSrc) ?>');
      }
      if (window.parent && typeof window.parent.cbCommitCardIconPreview === 'function') {
        window.parent.cbCommitCardIconPreview(cardId);
      }
      <?php endif; ?>
    }
  } catch (e) {}
}());
</script>
<?php endif; ?>

</body>
</html>
