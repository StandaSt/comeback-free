<?php
declare(strict_types=1);

require_once __DIR__ . '/../www/lib/session_boot.php';
require_once __DIR__ . '/../www/lib/app.php';

if (empty($_SESSION['login_ok'])) {
    header('Location: ' . cb_login_url());
    exit;
}
?><!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>PizzaComeback - HR</title>

<style>
* {
    box-sizing: border-box;
}

html,
body {
    width: 100%;
    height: 100%;
    margin: 0;
}

body {
    display: flex;
    flex-direction: column;
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f5f5;
    overflow: hidden;
}

.menu {
    flex: 0 0 auto;
    padding: 15px;
    text-align: center;
    background: #ffffff;
    border-bottom: 1px solid #dcdcdc;
}

.menu a {
    display: inline-block;
    margin: 0 10px;
    padding: 10px 20px;
    text-decoration: none;
    font-weight: bold;
    color: #ffffff;
    background: #2f6fed;
    border-radius: 6px;
}

.menu a:hover {
    background: #1f56c5;
}

.container {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 0;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.container img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
}
</style>
</head>

<body>

<div class="menu">
    <a href="<?= h(cb_module_url('is')) ?>">IS</a>
    <a href="<?= h(cb_module_url('hr')) ?>">HR</a>
</div>

<div class="container">
    <img src="pripravujeme_smeny.png" alt="Směny se připravují">
</div>

</body>
</html>
