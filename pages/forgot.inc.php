<?php defined('INCLUDED') or die(); ?>
<?php
$template = 'small-form';
$title = 'Forgot Password';
if (!empty($_SESSION['panelist_id']) || !empty($_SESSION['account_id'])) {
    header('Location: /profile');
    exit;
}

function handleForm() {
    global $db;

    $query = $db->prepare('SELECT * FROM accounts WHERE email = :email');
    $query->execute(array(':email' => $_POST['email']));
    $row = $query->fetch(PDO::FETCH_ASSOC);

    if (empty($row['email'])) {
        return 'We don\'t seem to have an account with that login email. Perhaps you entered your contact email?';
    }

    // single use token w/expiration
    $data = pack('NnJ', $row['id'], $row['reset_counter'], time());
    $token = rtrim(strtr(base64_encode(
        $data . pack('H64', hash_hmac('sha256', $data, RESET_KEY))
    ), '+/', '-_'), '=');

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mailgun.net/v3/panelists.ltue.org/messages',
        CURLOPT_USERPWD => 'api:' . MAILGUN_API_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'from' => 'LTUE Call For Panelists <mailgun@panelists.ltue.org>',
            'to' => $row['email'],
            'subject' => 'Your LTUE Panelist Account Password Reset',
            'text' => "Please go to https://panelists.ltue.net/forgot/$token to reset your password.",
            'html' => <<<HTML
Please go to <a href="https://panelists.ltue.org/forgot/$token">https://panelists.ltue.org/forgot/$token</a> to reset your password.
HTML
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
}

function checkToken($token) {
    global $db;

    $raw = base64_decode(str_pad(strtr($token, '-_', '+/'), strlen($token) % 4, '=', STR_PAD_RIGHT));
    $data = unpack('Nid/ncount/Jtime/H64hmac', $raw);

    if (hash_hmac('sha256', substr($raw, 0, 14), RESET_KEY) !== $data['hmac'])
        return 'Invalid token';
    if (time() - $data['time'] > (60 * 60 * 24 * 3)) // 3 days
        return 'Expired token';

    $query = $db->prepare('SELECT * FROM accounts WHERE id = :id');
    $query->execute(array(':id' => $data['id']));
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (empty($row['id'])) {
        trigger_error('Corrupt token - no such user: ' . $data['id'], E_USER_ERROR);
        return 'Invalid token';
    }

    if ($row['reset_counter'] != $data['count'])
        return 'Token already used - please get another';

    return [$row, $data];
}

function handleResetForm() {
    global $db;

    $check = checkToken($_POST['token']);
    if (!is_array($check))
        return $check;

    if ($_POST['password'] !== $_POST['password-confirm'])
        return 'Passwords do not match';
    if (strlen($_POST['password']) < 12)
        return 'Password must be at least 12 characters';
    if (strlen($_POST['password']) > 128)
        return 'Password is too long to be useful - you only need 256 bits at <em>most</em>.';

    $update = $db->prepare('
        UPDATE accounts SET password = :password, reset_counter = :counter + 1
        WHERE id = :id AND reset_counter = :counter
    ');
    $update->execute(array(
        ':password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        ':id' => $check[1]['id'],
        ':counter' => $check[1]['count'],
    ));
    if ($update->rowCount() !== 1) {
        return 'We failed to update your password. I don\'t know why. Try again?';
    }

    // TODO: just directly log them in?
    header('Location: /login');
    exit;
}

if (!empty($_POST['email'])) {
    $error = handleForm();
} else if (!empty($_POST['password'])) {
    $error = handleResetForm();
} else if (!empty($path[2])) {
    $check = checkToken($path[2]);
    if (!is_array($check)) {
        $error = $check;
        $tokenError = true;
    }
}
?>
<form method="POST">
<?php if (!empty($error)): ?>
    <output class="error"><?= $error ?></output>
<?php endif; ?>
<?php if (empty($path[2]) || !empty($tokenError)): ?>
    <label for="email">Login Email</label>
    <input type="email" id="email" name="email">
    <?php if (empty($error) && !empty($_POST['email'])): ?>
    <p>We have sent a password reset email to that address.</p>
    <?php endif; ?>

    <input type="submit" value="Send Reset Link">

    <p>Remember your password? <a href="/login">Login</a></p>
    <p>Haven't made an account? <a href="/register">Register</a></p>
<?php else: ?>
    <input type="hidden" name="token" value=<?= $path[2] ?> />

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <label for="password-confirm">Confirm Password</label>
    <input type="password" id="password-confirm" name="password-confirm" required>

    <input type="submit" value="Reset Password">
<?php endif; ?>
</form>
