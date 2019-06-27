<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= !empty($title) ? $title . ' - ' : '' ?>LTUE Panelists Website</title>
    <link rel="stylesheet" href="site.css">
</head>
<body>
<?php if (!empty($_SESSION['account'])): ?>
<nav><ul>
    <li><a href="/profile">Profile</a></li>
    <li><a href="/panels">Panels</a></li>
    <li><a href="/logout">Logout?</a></li>
</ul></nav>
<p>Logged in as <?= htmlspecialchars($_SESSION['account']['email'], ENT_QUOTES) ?>.</p>
<?php endif; ?>
<?php if (!empty($title)): ?>
    <h1><?= $title ?></h1>
<?php endif; ?>
    <?= $content ?>

</body>
</html>
