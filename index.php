<?php

// checked in in-webroot files to prevent direct access in case the .htaccess is corrupted…
// TODO: don't put included files in the webroot :P.
define('INCLUDED', true);
require('setup.php');

// prevent fixation attacks
ini_set('session.use_trans_sid', false);
ini_set('session.use_only_cookies', true);

// Start session

session_name('LTUE_PANELIST');
session_start();

// Single file routing system
$page = 'register';
if ($_SERVER['REQUEST_URI'] === '/login') {
    $page = 'login';
}
if (!empty($_SESSION['account'])) {
    $page = 'profile';

    switch($_SERVER['REQUEST_URI']) {
    case '/':
    case '/profile':
        break;
    case '/panels':
        $page = 'panels';
        break;
    case '/logout':
        $page = $_SERVER['REQUEST_URI'];
        break;
    default:
        echo $_SERVER['REQUEST_URI']; // XXX
        break;
    }
}

// Run the page
ob_start();
require('pages/'. $page . '.inc.php');
$content = ob_get_clean();

require('template.inc.php');
