<?php defined('INCLUDED') or die(); ?>
<?php
$template = 'small-form';
$title = 'Register';
if (!empty($_SESSION['panelist_id']) || !empty($_SESSION['account_id'])) {
    header('Location: /profile');
    exit;
}
?>
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
    $_SESSION['account_id'] = $row['id'];

    $email_text = <<<TEXT
Thank you for your willingness to support LTUE!

If you would like to update your contact information, schedule, or panel
interests, you can login at panelists.ltue.org using your password and this
email address.
TEXT;
    $email_html = <<<HTML
<p>Thank you for your willingness to support LTUE!</p>
<p>If you would like to update your contact information, schedule, or panel
interests, you can login at <a
href="https://panelists.ltue.org/">panelists.ltue.org</a> using your password
and this email address.</p>
HTML;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mailgun.net/v3/panelists.ltue.org/messages',
        CURLOPT_USERPWD => 'api:' . MAILGUN_API_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'from' => 'LTUE Call For Panelists <mailgun@panelists.ltue.org>',
            'to' => $row['email'],
            'subject' => 'Your LTUE Panelist Account',
            'text' => $email_text,
            'html' => $email_html,
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    // TODO: check response code for success

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
    <label for="email">Login Email</label>
    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <label for="password-confirm">Confirm Password</label>
    <input type="password" id="password-confirm" name="password-confirm" required>

    <input type="submit" value="Register">

    <p>Already signed up? <a href="/login">Login</a></p>
    <p>Or <a href="/profile">continue without an account</a>. You will not be able to change your answers later.</p>
</form>
