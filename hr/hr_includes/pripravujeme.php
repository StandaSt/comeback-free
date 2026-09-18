<?php
declare(strict_types=1);

/**
 * Zobrazí pravdivou informaci uživateli bez oprávnění k požadované části HR.
 */
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

.access-denied {
    max-width: 560px;
    padding: 28px;
    text-align: center;
    background: #ffffff;
    border: 1px solid #dcdcdc;
    border-radius: 10px;
}

.access-denied h1 {
    margin-top: 0;
    color: #b42318;
}
</style>
</head>

<body>

<div class="menu">
    <a href="<?= h(cb_module_entry_url('provoz')) ?>">Provoz</a>
    <a href="<?= h(cb_module_entry_url('smeny')) ?>">Směny</a>
</div>

<div class="container">
    <section class="access-denied" role="alert">
        <h1>Přístup zamítnut</h1>
        <p>K této části HR nemáte oprávnění.</p>
        <p>Pokud ji potřebujete pro svou práci, obraťte se na administrátora IS.</p>
    </section>
</div>

</body>
</html>
