<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= !empty($title) ? $title . ' - ' : '' ?>LTUE Panelists Website</title>
    <link rel="stylesheet" href="/site.css">
    <link rel="stylesheet" href="/small-form.css">
</head>
<body class="small-form">
    <header>
        <img src="/img/LTUELogo.png" />
        <h1>Welcome to the 2020 LTUE Call for Panelists!</h1>
        <h2>Sign up here to be considered for a panelist or presenter at LTUE Feb 13-15, 2020, in Provo, Utah.</h2>
    </header>
    <hr />

    <main>
        <?= $content ?>

    </main>
</body>
</html>
