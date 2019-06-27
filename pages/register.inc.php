<?php defined('INCLUDED') or die(); ?>
<?php $title = 'Register' ?>
<?php
if (!empty($_POST['email']) && !empty($_POST['password']) && !empty($_POST['name'])) {
    if ($_POST['password'] === $_POST['password-confirm']) {
        $query = $db->prepare('SELECT * FROM accounts WHERE email = :email');
        $query->execute(array(':email' => $_POST['email']));
        $row = $query->fetch(PDO::FETCH_ASSOC);

        // email must be unique or warn the user
        if (empty($row['email'])) {
            $create = $db->prepare('INSERT INTO accounts SET email = :email, password = :password, type = :type');
            $create->execute(array(
                ':email' => $_POST['email'],
                ':name' => $_POST['name'],
                ':type' => 'panelist',
                ':password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            ));
            if ($create->rowCount() === 1) {
                $query->execute(array(':email' => $_POST['email']));
                $row = $query->fetch(PDO::FETCH_ASSOC);
                if (!empty($row['email'])) {
                    unset($row['password']);
                    session_regenerate_id(true); // further prevent fixation attacks
                    $_SESSION['account'] = $row;
                    header('Location: /');
                    exit;
                } else {
                    $error = 'Account created - please log in to continue';
                }
            } else {
                $error = 'We failed to create your account. I don\'t know why. Try again?';
            }
        } else {
            // TODO: password reset
            $error = 'It appears that email may have already registered. Try logging in again, or contact support?';
        }
    } else {
        $error = 'Passwords do not match';
    }
}
?>
<form method="POST">
<?php if (!empty($error)): ?>
    <output class="error"><?= $error ?></output>
<?php endif; ?>
    <label for="name">What would we know you as?</label>
    <input type="text" id="name" name="name" required value="<?= $_POST['name'] ?? '' ?>">

    <label for="pen-name">If different, what should we print on your badge, etc? Your pen name.</label>
    <input type=text" id="pen-name" name="pen-name" value="<?= $_POST['pen-name'] ?? '' ?>">

    <label for="email">Login Email:</label>
    <input type="email" id="email" name="email" required value="<?= $_POST['email'] ?? '' ?>">

    <label for="password">Password:</label>
    <input type="password" id="password" name="password" required>

    <label for="password-confirm">Confirm Password:</label>
    <input type="password" id="password-confirm" name="password-confirm" required>

    <input type="submit" value="Register">
</form>

<p>Already have an account? <a href="/login">Login</a> instead.</p>
