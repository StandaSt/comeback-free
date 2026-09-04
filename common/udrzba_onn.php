<?php
declare(strict_types=1);

$cbUdrzbaImageUrl = isset($cbUdrzbaImageUrl) && is_string($cbUdrzbaImageUrl)
    ? $cbUdrzbaImageUrl
    : 'img/udrzba.png';
$cbUdrzbaJeDotaznik = !empty($cbUdrzbaJeDotaznik);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Údržba IS Comeback</title>
    <style>
        html,
        body {
            margin: 0;
            width: 100%;
            min-height: 100%;
            background: #111;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        img {
            display: block;
            max-width: 100%;
            max-height: 100vh;
            width: auto;
            height: auto;
        }

        .udrzba_text {
            margin: 24px;
            color: #fff;
            font: 600 28px/1.35 Arial, sans-serif;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php if ($cbUdrzbaJeDotaznik): ?>
        <p class="udrzba_text">Probíhá údržba systému</p>
    <?php else: ?>
        <img src="<?= htmlspecialchars($cbUdrzbaImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Probíhá údržba IS Comeback">
    <?php endif; ?>
</body>
</html>
