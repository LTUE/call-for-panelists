<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= !empty($title) ? $title . ' - ' : '' ?>LTUE Panelists Website</title>
    <link rel="stylesheet" href="/site.css">
    <link rel="stylesheet" href="/large-form.css">
</head>
<body class="large-form">
<?php if (!empty($_SESSION['account'])): ?>
<nav>
    <a href="http://ltue.net"><img src="img/LTUELogo-WithText.png" alt="LTUE" /></a>
    <ul>
        <li><a href="/profile">Profile Form</a></li>
        <li><a href="/panels">Panel Selection</a></li>
        <li><a href="/logout">Log out</a></li>
    </ul>
</nav>
<?php endif; ?>
<?php if (!empty($title)): ?>
    <h1><?= $title ?></h1>
<?php endif; ?>
    <main>
        <?= $content ?>

    </main>
</body>
</html>
