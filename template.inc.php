<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= !empty($title) ? $title . ' - ' : '' ?>LTUE Panelists Website</title>
    <link rel="stylesheet" href="/site.css">
    <link rel="stylesheet" href="/large-form.css">

    <link rel="icon" href="https://ltue.net/wp-content/uploads/2020/01/cropped-AppLogo_512-32x32.png" sizes="32x32" />
    <link rel="icon" href="https://ltue.net/wp-content/uploads/2020/01/cropped-AppLogo_512-192x192.png" sizes="192x192" />
    <link rel="apple-touch-icon-precomposed" href="https://ltue.net/wp-content/uploads/2020/01/cropped-AppLogo_512-180x180.png" />
</head>
<body class="large-form">
<nav>
    <a href="http://ltue.net"><img src="img/LTUELogo-WithText.png" alt="LTUE" /></a>
    <ul>
        <li><a href="/profile">Profile Form</a></li>
        <li><a href="/panels">Panel Selection</a></li>
<?php if (!empty($_SESSION['account_id'])): ?>
        <li><a href="/logout">Log out</a></li>
<?php else: ?>
        <li><a href="/logout">Forget me</a></li>
<?php endif; ?>
    </ul>
</nav>
<?php if (!empty($title)): ?>
    <h1><?= $title ?></h1>
<?php endif; ?>
    <main>
        <?= $content ?>

    </main>
</body>
</html>
