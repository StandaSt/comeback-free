<?php
// includes/select_card_color.php * Verze: V2 * Aktualizace: 07.04.2026
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../db/db_connect.php';

$idUser = (int)(($_SESSION['cb_user']['id_user'] ?? 0));
$idKarta = (int)($_REQUEST['id_karta'] ?? 0);
$selectedColor = trim((string)($_REQUEST['color'] ?? ''));
$msg = '';
$ok = 0;

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string)($_POST['cb_action'] ?? '') === 'save'
    && $idUser > 0
    && $idKarta > 0
    && $selectedColor !== ''
) {
    $conn = db_connect();
    $stmt = $conn->prepare(
        'INSERT INTO user_card_set (id_user, id_karta, color)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE color = VALUES(color)'
    );

    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('iis', $idUser, $idKarta, $selectedColor);
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
    $conn = db_connect();
    $stmt = $conn->prepare('UPDATE user_card_set SET color = NULL WHERE id_user = ? AND id_karta = ?');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('ii', $idUser, $idKarta);
        if ($stmt->execute()) {
            $ok = 1;
            $msg = 'Resetováno.';
            $selectedColor = '';
        } else {
            $msg = 'Reset selhal.';
        }
        $stmt->close();
    } else {
        $msg = 'DB chyba.';
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function cb_make_palette(): array
{
    $rows = 8;
    $cols = 21;
    $palette = [];
    $lightnessSteps = [98, 93, 86, 78, 68, 58, 48, 40];

    for ($r = 0; $r < $rows; $r++) {
        $lightness = (int)($lightnessSteps[$r] ?? 50);
        $line = [];
        for ($c = 0; $c < $cols; $c++) {
            if ($c === 0) {
                $gray = (int)round(255 - (($r / ($rows - 1)) * 223)); // 255 -> 32 (bez černé)
                $hex = sprintf('#%02x%02x%02x', $gray, $gray, $gray);
                $line[] = $hex;
                continue;
            }
            $hue = (int)round((($c - 1) / ($cols - 2)) * 330);
            $line[] = cb_hsl_to_hex($hue, 0.88, $lightness / 100);
        }
        $palette[] = $line;
    }

    $palette[0][0] = '#ffffff';

    return $palette;
}

function cb_hsl_to_hex(int $hDeg, float $s, float $l): string
{
    $h = ($hDeg % 360) / 360;
    $s = max(0.0, min(1.0, $s));
    $l = max(0.0, min(1.0, $l));

    if ($s == 0.0) {
        $v = (int)round($l * 255);
        return sprintf('#%02x%02x%02x', $v, $v, $v);
    }

    $q = $l < 0.5 ? $l * (1 + $s) : ($l + $s - $l * $s);
    $p = 2 * $l - $q;

    $r = cb_hue_to_rgb($p, $q, $h + (1 / 3));
    $g = cb_hue_to_rgb($p, $q, $h);
    $b = cb_hue_to_rgb($p, $q, $h - (1 / 3));

    return sprintf('#%02x%02x%02x', (int)round($r * 255), (int)round($g * 255), (int)round($b * 255));
}

function cb_hue_to_rgb(float $p, float $q, float $t): float
{
    if ($t < 0) {
        $t += 1;
    }
    if ($t > 1) {
        $t -= 1;
    }
    if ($t < 1 / 6) {
        return $p + ($q - $p) * 6 * $t;
    }
    if ($t < 1 / 2) {
        return $q;
    }
    if ($t < 2 / 3) {
        return $p + ($q - $p) * (2 / 3 - $t) * 6;
    }
    return $p;
}

$palette = cb_make_palette();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;padding:6px;font-family:Arial,sans-serif;background:#fff;color:#334155;}
    .grid{display:grid;grid-template-columns:repeat(21,14px);gap:0;}
    .swatch{width:14px;height:14px;border:0;display:block;cursor:pointer;padding:0;margin:0;}
    .swatch.is-active{outline:2px solid #0f172a;outline-offset:-2px;}
    .title{font-size:12px;color:#475569;margin:0 0 8px 0;}
    .bar{display:flex;align-items:center;justify-content:flex-end;margin-top:8px;gap:8px;}
    .btn{height:18px;line-height:16px;padding:0 8px;border:1px solid #94a3b8;border-radius:6px;background:#f8fafc;cursor:pointer;font-size:12px;}
    .ok{font-size:12px;color:#15803d;margin-top:6px;}
    .err{font-size:12px;color:#b91c1c;margin-top:6px;}
  </style>
</head>
<body>

<form method="post" id="cardColorForm">
  <input type="hidden" name="cb_action" value="save" id="cardColorAction">
  <input type="hidden" name="id_karta" value="<?= h((string)$idKarta) ?>">
  <input type="hidden" name="color" id="cardColorValue" value="<?= h($selectedColor) ?>">

  <p class="title">Vyber barvu pro tuto kartu:</p>

  <div class="grid">
    <?php foreach ($palette as $row): ?>
      <?php foreach ($row as $color): ?>
        <?php $active = ($selectedColor !== '' && $selectedColor === $color); ?>
        <button
          type="button"
          class="swatch<?= $active ? ' is-active' : '' ?>"
          style="background:<?= h($color) ?>"
          data-color="<?= h($color) ?>"
          title="<?= h($color) ?>"
        ></button>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <div class="bar">
    <button type="submit" class="btn" id="cardColorReset">Reset</button>
    <button type="submit" class="btn">Uložit</button>
  </div>

  <?php if ($msg !== ''): ?>
    <div class="<?= $ok === 1 ? 'ok' : 'err' ?>"><?= h($msg) ?></div>
  <?php endif; ?>
</form>

<script>
(function () {
  var form = document.getElementById('cardColorForm');
  var actionInput = document.getElementById('cardColorAction');
  var colorInput = document.getElementById('cardColorValue');
  var resetBtn = document.getElementById('cardColorReset');
  var cardId = <?= (int)$idKarta ?>;
  if (!form || !actionInput || !colorInput || !resetBtn || cardId <= 0) return;

  function paintInParent(color) {
    try {
      if (!window.parent || !window.parent.document) return;
      var root = window.parent.document.querySelector('[data-card-id="' + String(cardId) + '"]');
      if (!root) return;
      var head = root.querySelector('.card_top');
      if (!head) return;
      if (!head.hasAttribute('data-preview-backup')) {
        head.setAttribute('data-preview-backup', String(head.getAttribute('style') || ''));
      }
      head.setAttribute('data-preview-dirty', '1');
      head.style.background = color;
    } catch (e) {}
  }

  function setActiveSwatch(color) {
    document.querySelectorAll('.swatch').forEach(function (el) {
      if (!(el instanceof HTMLElement)) return;
      if (String(el.getAttribute('data-color') || '') === color) {
        el.classList.add('is-active');
      } else {
        el.classList.remove('is-active');
      }
    });
  }

  document.querySelectorAll('.swatch').forEach(function (el) {
    el.addEventListener('click', function () {
      var c = String(el.getAttribute('data-color') || '').trim();
      if (c === '') return;
      colorInput.value = c;
      actionInput.value = 'save';
      setActiveSwatch(c);
      paintInParent(c);
    });
  });

  resetBtn.addEventListener('click', function () {
    actionInput.value = 'reset';
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
    if (toggle) toggle.setAttribute('aria-expanded', 'false');

    <?php if ($selectedColor === ''): ?>
    var head = root.querySelector('.card_top');
    if (head) {
      head.style.background = '';
      head.setAttribute('data-preview-backup', '');
      head.removeAttribute('data-preview-dirty');
    }
    <?php else: ?>
    var head2 = root.querySelector('.card_top');
    if (head2) {
      head2.setAttribute('data-preview-backup', String(head2.getAttribute('style') || ''));
      head2.removeAttribute('data-preview-dirty');
    }
    <?php endif; ?>
  } catch (e) {}
}());
</script>
<?php endif; ?>

</body>
</html>
