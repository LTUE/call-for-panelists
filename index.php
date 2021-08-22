<?php

// checked in in-webroot files to prevent direct access in case the .htaccess is corrupted…
// TODO: don't put included files in the webroot :P.
define('INCLUDED', true);
require('setup.php');

if (defined('CLOSED')) {
    require('closed.inc.php');
    die;
}

// prevent fixation attacks
ini_set('session.use_trans_sid', false);
ini_set('session.use_only_cookies', true);

// Start session

session_name('LTUE_PANELIST');
session_start();

// Single file routing system
$page = 'login';
if (!empty($_SESSION['panelist_id']) || !empty($_SESSION['account_id'])) {
    $page = 'profile';
}

$path = explode('/', parse_url($_SERVER['REQUEST_URI'])['path']);
switch ($path[1]) {
case '';
    // keep it simple - same url always
    header('Location: /' . $page);
    exit;
    break;
case 'register':
case 'login':
case 'forgot':
case 'profile':
case 'panels':
case 'logout':
    $page = $path[1];
    break;
default:
    echo 'Missing: ' . $_SERVER['REQUEST_URI']; // XXX
    break;
}

// Run the page
ob_start();
require('pages/'. $page . '.inc.php');
$content = ob_get_clean();

require(($template ?? 'template') . '.inc.php');
