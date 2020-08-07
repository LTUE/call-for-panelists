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


    <!-- Matomo -->
    <script type="text/javascript">
      var _paq = window._paq = window._paq || [];
      /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
      _paq.push(['trackPageView']);
      _paq.push(['enableLinkTracking']);
      (function() {
        var u="//stats.ltue.org/";
        _paq.push(['setTrackerUrl', u+'matomo.php']);
        _paq.push(['setSiteId', '2']);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
        g.type='text/javascript'; g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
      })();
    </script>
    <noscript><p><img src="//stats.ltue.org/matomo.php?idsite=2&amp;rec=1" style="border:0;" alt="" /></p></noscript>
    <!-- End Matomo Code -->
</head>
<body class="large-form">
<nav>
    <a href="http://ltue.net"><img src="img/LTUELogo-WithText.png" alt="LTUE" /></a>
    <ul>
        <li>
            <?php if ($_SERVER['PATH_INFO'] === '/profile'): ?>
            <span class="active">Profile Form</span>
            <?php else: ?>
            <a href="/profile">Profile Form</a>
            <?php endif; ?>
        </li>
        <li>
            <?php if ($_SERVER['PATH_INFO'] === '/panels'): ?>
            <span class="active">Panel Selection</span>
            <?php else: ?>
            <a href="/panels">Panel Selection</a>
            <?php endif; ?>
        </li>
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
