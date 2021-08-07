<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= !empty($title) ? $title . ' - ' : '' ?>LTUE Panelists Website</title>
    <link rel="stylesheet" href="/site.css">
    <link rel="stylesheet" href="/small-form.css">

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
<body class="small-form">
    <header>
        <img src="/img/LTUELogo.png" />
        <h1>Welcome to the 2022 LTUE Call for Panelists!</h1>
        <h2>Sign up here to be considered for a panelist or presenter at LTUE February 17-19, 2022, in Provo, Utah.</h2>
    </header>
    <hr />

    <main>
        <nav>
            <ul>
                <li>
                    <?php if ($path[1] === 'register'): ?>
                    <span class="active">Register</span>
                    <?php else: ?>
                    <a href="/register">Register</a>
                    <?php endif; ?>
                </li>
                <li>
                    <?php if ($path[1] === 'login'): ?>
                    <span class="active">Log In</span>
                    <?php else: ?>
                    <a href="/login">Log In</a>
                    <?php endif; ?>
                </li>
        </nav>
        <?= $content ?>
        <p>Or <a href="/profile">continue without an account</a>. You will have to log in with an email link to change your answers later.</p>
    </main>
</body>
</html>
