<?php defined('INCLUDED') or die(); ?>
<?php
$template = 'small-form';
$title = 'Login';
if (!empty($_SESSION['panelist_id']) || !empty($_SESSION['account_id'])) {
    header('Location: /profile');
    exit;
}
?>
<?php
if (!empty($_POST['email']) && !empty($_POST['password'])) {
    $query = $db->prepare('SELECT * FROM accounts WHERE email = :email');
    $query->execute(array(':email' => $_POST['email']));
    $row = $query->fetch(PDO::FETCH_ASSOC);

    if (!empty($row['password']) && password_verify($_POST['password'], $row['password'])) {
        unset($row['password']);
        session_regenerate_id(true); // further prevent fixation attacks
        $_SESSION['account_id'] = $row['id'];

        // TODO: pull panelist id here, or continue to rely on redirecting to profile first?

        header('Location: /profile');
        exit;
    } else {
        // TODO: password reset, send out emails
        $error = 'Invalid password for that email address. Please try again or register.';
    }
}
?>
<form method="POST">
<?php if (!empty($error)): ?>
    <output class="error"><?= $error ?></output>
<?php endif; ?>
    <label for="email">Login Email</label>
    <input type="email" id="email" name="email">

    <label for="password">Password</label>
    <input type="password" id="password" name="password">

    <input type="submit" value="Log in">
    <a id="forgot-link" href="/forgot">Forgot Password?</a>
    <p>Haven't made an account? <a href="/register">Register</a></p>
    <p>Or <a href="/profile">continue without an account</a>. You will not be able to change your answers later.</p>
</form>
