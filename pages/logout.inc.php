<?php defined('INCLUDED') or die(); ?>
<?php
// credit: https://stackoverflow.com/a/5081453/118153
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
);
session_destroy();

header('Location: /');
exit;
