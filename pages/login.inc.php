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
function handleToken($token) {
    global $db;

    $raw = base64_decode(str_pad(strtr($token, '-_', '+/'), strlen($token) % 4, '=', STR_PAD_RIGHT));
    $data = unpack('Nid/Jtime/H64hmac', $raw);

    if (hash_hmac('sha256', substr($raw, 0, 12), RESET_KEY) !== $data['hmac'])
        return 'Invalid token - please check your email to ensure the link was correctly pasted';
    if (time() - $data['time'] > (60 * 60)) // 60 minutes
        return 'Expired token - please send another magic link to your email';

    $query = $db->prepare('SELECT id FROM accounts WHERE id = :id');
    $query->execute(array(':id' => $data['id']));
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (empty($row['id'])) {
        trigger_error('Corrupt token - no such user: ' . $data['id'], E_USER_ERROR);
        return 'Invalid token - please check your email to ensure the link was correctly pasted';
    }

    session_regenerate_id(true); // further prevent fixation attacks
    $_SESSION['account_id'] = $row['id'];

    header('Location: /profile');
}

function handleForm() {
    global $db;

    if (!empty($_POST['email']) && isset($_POST['magic'])) {
        $query = $db->prepare('SELECT * FROM accounts WHERE email = :email');
        $query->execute(array(':email' => $_POST['email']));
        $row = $query->fetch(PDO::FETCH_ASSOC);

        $id;
        if (!empty($row)) {
            $id = $row['id'];
        } else {
            $pquery = $db->prepare('SELECT * FROM panelists WHERE contact_email = :email');
            $pquery->execute(array(':email' => $_POST['email']));
            $panelist = $pquery->fetch(PDO::FETCH_ASSOC);

            if (empty($panelist))
                return 'We don\'t recognize that email. Try another, or start a new profile.';

            if ($panelist['account_id']) {
                $id = $panelist['account_id'];
            } else {
                // create an account for them
                $create = $db->prepare('INSERT INTO accounts SET email = :email, password = :password, type = :type');
                $create->execute(array(
                    ':email' => $_POST['email'],
                    ':type' => 'panelist',
                    ':password' => '', // no password, but they can reset it later if they want one
                ));
                if ($create->rowCount() !== 1) {
                    return 'Unknown error - please try again';
                }

                $query->execute(array(':email' => $_POST['email']));
                $row = $query->fetch(PDO::FETCH_ASSOC);
                $id = $row['id'];

                // link their login account to their profile
                $update = $db->prepare('UPDATE panelists SET account_id = :account_id WHERE id = :id');
                $update->execute(array(
                    ':id' => $panelist['id'],
                    ':account_id' => $id,
                ));
            }
        }

        // Token needs to be a capability - account, date
        $data = pack('NJ', $id, time());
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
                'to' => $_POST['email'],
                'subject' => 'Login for LTUE Call for Panelists',
                'text' => "Please go to https://panelists.ltue.org/login/$token to login to your account.",
                'html' => <<<HTML
    Please go to <a href="https://panelists.ltue.org/login/$token">https://panelists.ltue.org/login/$token</a> to login to your account.
HTML
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

    } else if (!empty($_POST['email']) && !empty($_POST['password'])) {
        $query = $db->prepare('SELECT * FROM accounts WHERE email = :email');
        $query->execute(array(':email' => $_POST['email']));
        $row = $query->fetch(PDO::FETCH_ASSOC);

        // permit login with contact email as well
        if (empty($row)) {
            $query = $db->prepare('SELECT a.* FROM panelists AS p LEFT JOIN accounts AS a ON p.account_id = a.id WHERE a.email IS NOT NULL AND p.contact_email = :email');
            $query->execute(array(':email' => $_POST['email']));
            $row = $query->fetch(PDO::FETCH_ASSOC);
        }

        if (!empty($row['password']) && password_verify($_POST['password'], $row['password'])) {
            unset($row['password']);
            session_regenerate_id(true); // further prevent fixation attacks
            $_SESSION['account_id'] = $row['id'];

            // TODO: pull panelist id here, or continue to rely on redirecting to profile first?

            header('Location: /profile');
            exit;
        } else {
            // TODO: password reset, send out emails
            return 'Invalid password for that email address. Please try again or register.';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = handleForm();
} else if (!empty($path[2])) {
    $error = handleToken($path[2]);
}
?>
<?php if (isset($_POST['magic'])): ?>
<form>
<?php if (!empty($error)): ?>
    <output class="error"><?= $error ?></output>
<?php else: ?>
    <p>Please check your email at <?= htmlspecialchars($_POST['email']) ?> for the link.</p>
<?php endif; ?>
</form>
<?php else: ?>
<form method="POST">
<?php if (!empty($error)): ?>
    <output class="error"><?= $error ?></output>
<?php endif; ?>
    <label for="email">Login Email</label>
    <input type="email" id="email" name="email">
    <button type="submit" name="magic">Magic Link</button>

    <label for="password">Password</label>
    <input type="password" id="password" name="password">

    <input type="submit" value="Log in">
    <a id="forgot-link" href="/forgot">Forgot Password?</a>
    <p>Haven't made an account? <a href="/register">Register</a></p>
    <p>Or <a href="/profile">continue without an account</a>. You will have to log in with a magic link to change your answers later.</p>
</form>
<?php endif; ?>
