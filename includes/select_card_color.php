<?php
// includes/select_card_color.php * Verze: V2 * Aktualizace: 07.04.2026
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../db/db_connect.php';

$idUser = (int)(($_SESSION['cb_user']['id_user'] ?? 0));
$idRole = (int)(($_SESSION['cb_user']['id_role'] ?? 3));
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

$usedColors = [];
if ($idUser > 0) {
    $conn = db_connect();
    $stmt = $conn->prepare(
        'SELECT DISTINCT color
         FROM user_card_set
         WHERE id_user = ?
           AND color IS NOT NULL
           AND color <> ""
         ORDER BY color ASC'
    );
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $idUser);
        if ($stmt->execute()) {
            $stmt->bind_result($dbColor);
            while ($stmt->fetch()) {
                $c = trim((string)$dbColor);
                if ($c !== '') {
                    $usedColors[] = $c;
                }
            }
        }
        $stmt->close();
    }
}

if (!in_array($idRole, [1, 2, 3], true)) {
    $idRole = 3;
}

$baseRoleColors = [
    1 => '#fef2f2', // role 1
    2 => '#f0fdf4', // role 2
    3 => '#f6f8fb', // role 3
];

$visibleRoleColors = [];
if ($idRole === 1) {
    $visibleRoleColors = [$baseRoleColors[1], $baseRoleColors[2], $baseRoleColors[3]];
} elseif ($idRole === 2) {
    $visibleRoleColors = [$baseRoleColors[2], $baseRoleColors[3]];
} else {
    $visibleRoleColors = [$baseRoleColors[3]];
}

$finalUsedColors = [];
$seenColors = [];
$pushColor = static function (string $color) use (&$finalUsedColors, &$seenColors): void {
    $c = trim($color);
    if ($c === '') {
        return;
    }
    $key = strtolower($c);
    if (isset($seenColors[$key])) {
        return;
    }
    $seenColors[$key] = true;
    $finalUsedColors[] = $c;
};

foreach ($usedColors as $uc) {
    $pushColor((string)$uc);
}
foreach ($visibleRoleColors as $rc) {
    $pushColor((string)$rc);
}

$usedColors = $finalUsedColors;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function cb_make_palette(): array
{
    $rows = 8;
    $cols = 18;
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
    .picker-wrap{width:max-content;}
    .grid{display:grid;grid-template-columns:repeat(18,14px);gap:0;}
    .used-grid{display:grid;grid-template-columns:repeat(9,28px);gap:0;}
    .swatch{width:14px;height:14px;border:0;display:block;cursor:pointer;padding:0;margin:0;}
    .swatch.used{width:28px;height:28px;}
    .swatch.is-active{outline:2px solid #0f172a;outline-offset:-2px;}
    .swatch:hover{outline:1px solid #334155;outline-offset:-1px;}
    .title{font-size:12px;color:#475569;margin:0 0 8px 0;}
    .bar{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;margin-top:8px;column-gap:8px;}
    .bar-left{text-align:left;}
    .bar-center{text-align:center;}
    .bar-right{text-align:right;}
    .btn{height:18px;line-height:16px;padding:0 8px;border:1px solid #94a3b8;border-radius:6px;background:#f8fafc;cursor:pointer;font-size:12px;}
    .is-hidden{display:none;}
    .used-empty{font-size:12px;color:#64748b;margin-top:6px;}
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

  <div class="picker-wrap">
    <div class="grid" id="cardColorPaletteGrid">
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

    <div class="used-grid is-hidden" id="cardColorUsedGrid">
      <?php foreach ($usedColors as $color): ?>
        <?php $active = ($selectedColor !== '' && $selectedColor === $color); ?>
        <button
          type="button"
          class="swatch used<?= $active ? ' is-active' : '' ?>"
          style="background:<?= h($color) ?>"
          data-color="<?= h($color) ?>"
          title="<?= h($color) ?>"
        ></button>
      <?php endforeach; ?>
    </div>
    <?php if (count($usedColors) === 0): ?>
      <div class="used-empty is-hidden" id="cardColorUsedEmpty">Zatím nemáš žádné použité barvy.</div>
    <?php endif; ?>

    <div class="bar">
      <div class="bar-left">
        <button type="button" class="btn" id="cardColorToggle">Použité barvy</button>
      </div>
      <div class="bar-center">
        <button type="submit" class="btn" id="cardColorReset">Reset</button>
      </div>
      <div class="bar-right">
        <button type="submit" class="btn">Uložit</button>
      </div>
    </div>
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
  var toggleBtn = document.getElementById('cardColorToggle');
  var paletteGrid = document.getElementById('cardColorPaletteGrid');
  var usedGrid = document.getElementById('cardColorUsedGrid');
  var usedEmpty = document.getElementById('cardColorUsedEmpty');
  var showingUsed = false;
  var cardId = <?= (int)$idKarta ?>;
  if (!form || !actionInput || !colorInput || !resetBtn || !toggleBtn || !paletteGrid || !usedGrid || cardId <= 0) return;

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

  form.addEventListener('click', function (ev) {
    var target = ev.target instanceof Element ? ev.target.closest('.swatch') : null;
    if (!(target instanceof HTMLElement)) return;
    ev.preventDefault();
    var c = String(target.getAttribute('data-color') || '').trim();
    if (c === '') return;
    colorInput.value = c;
    actionInput.value = 'save';
    setActiveSwatch(c);
    paintInParent(c);
  });

  resetBtn.addEventListener('click', function () {
    actionInput.value = 'reset';
  });

  toggleBtn.addEventListener('click', function () {
    showingUsed = !showingUsed;
    if (showingUsed) {
      paletteGrid.classList.add('is-hidden');
      usedGrid.classList.remove('is-hidden');
      if (usedEmpty) usedEmpty.classList.remove('is-hidden');
      toggleBtn.textContent = 'Ukaž paletu';
    } else {
      usedGrid.classList.add('is-hidden');
      if (usedEmpty) usedEmpty.classList.add('is-hidden');
      paletteGrid.classList.remove('is-hidden');
      toggleBtn.textContent = 'Použité barvy';
    }
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
