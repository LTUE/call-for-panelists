<?php defined('INCLUDED') or die(); ?>
<?php $title = 'Register' ?>
<?php
function handleForm() {
    global $db;

    if ($_POST['password'] !== $_POST['password-confirm'])
        return 'Passwords do not match';
    if (strlen($_POST['password']) < 12)
        return 'Password must be at least 12 characters';
    if (strlen($_POST['password']) > 128)
        return 'Password is too long to be useful - you only need 256 bits at <em>most</em>.';

    $query = $db->prepare('SELECT * FROM accounts WHERE email = :email');
    $query->execute(array(':email' => $_POST['email']));
    $row = $query->fetch(PDO::FETCH_ASSOC);

    // email must be unique or warn the user
    if (!empty($row['email'])) {
        // TODO: password reset
        return 'It appears that email may have already registered. Try logging in again, or contact support?';
    }

    $create = $db->prepare('INSERT INTO accounts SET email = :email, password = :password, type = :type');
    $create->execute(array(
        ':email' => $_POST['email'],
        ':type' => 'panelist',
        ':password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
    ));
    if ($create->rowCount() !== 1) {
        return 'We failed to create your account. I don\'t know why. Try again?';
    }

    $query->execute(array(':email' => $_POST['email']));
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (empty($row['email'])) {
        return 'Account created - please log in to continue';
    }

    unset($row['password']);
    session_regenerate_id(true); // further prevent fixation attacks
    $_SESSION['account'] = $row;
    header('Location: /');
    exit;
}
if (!empty($_POST['email']) && !empty($_POST['password'])) {
    $error = handleForm();
}
?>
<form method="POST">
<?php if (!empty($error)): ?>
    <output class="error"><?= $error ?></output>
<?php endif; ?>
    <label for="email">Login Email:</label>
    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">

    <label for="password">Password:</label>
    <input type="password" id="password" name="password" required>

    <label for="password-confirm">Confirm Password:</label>
    <input type="password" id="password-confirm" name="password-confirm" required>

    <input type="submit" value="Register">
</form>

<p>Already have an account? <a href="/login">Login</a> instead.</p>
