<?php defined('INCLUDED') or die(); ?>
<?php

// Load environment
foreach (file(__DIR__.'/environment.conf') as $line) {
    $conf = explode('=', $line);
    define($conf[0], trim($conf[1]));
}

// Database
try {
    $db = new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // TODO: hmm… don't like this
} catch(PDOException $e) {
    echo '<p>' . $e->getMessage() . '</p>'; // TODO: in debug mode only
    exit;
}

if (defined('TESTING')) {
    error_reporting(E_ALL);
    ini_set('display_errors', true);
}
