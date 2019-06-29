<?php defined('INCLUDED') or die(); ?>
<?php $title = 'Profile' ?>
<?php
$getPanelist = $db->prepare('SELECT * FROM panelists WHERE account_id = :id');
$getPanelist->execute(array(':id' => $_SESSION['account']['id']));
$panelist = $getPanelist->fetch(PDO::FETCH_ASSOC);

$topics = array_column($db->query(
    'SELECT id, name FROM topics ORDER BY name ASC'
)->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');

$myTopics = array();
if (!empty($panelist)) {
    $myTopicsQuery = $db->prepare('SELECT topic_id FROM panelists_topics WHERE panelist_id = :id');
    $myTopicsQuery->execute(array(':id' => $panelist['id']));
    $myTopics = $myTopicsQuery->fetchAll(PDO::FETCH_COLUMN);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sufficientData = !empty($_POST['name']) && !empty($_POST['badge_name']) &&
        !empty($_POST['contact_email']) && !empty($_POST['biography']) && !empty($_POST['topic']) &&
        !empty($_POST['signing']) && !empty($_POST['moderator']) &&
        !empty($_POST['recording']) && !empty($_POST['share_email']);
    if (!empty($_POST['biography']) && strlen($_POST['biography']) > 500) {
        $error = 'Your biography is too long. Please reduce it.';
    } else if (!empty($_POST['topic']) && !is_array($_POST['topic'])) {
        $error = 'Your data is somehow corrupted - please contact us and show us what you did so we can fix it.';
    } else if ($sufficientData) {
        $profileSet = '
            name = :name, badge_name = :badge_name, contact_email = :contact_email, website = :website,
            biography = :biography, intersectionalities = :intersectionalities,
            signing = :signing, reading = :reading, moderator = :moderator,
            recording = :recording, share_email = :share_email
        ';
        if (empty($panelist))
            $saveQuery = 'INSERT INTO panelists SET account_id = :account_id, ' . $profileSet;
        else
            $saveQuery = 'UPDATE panelists SET ' . $profileSet . ' WHERE account_id = :account_id';
        $saveProfile = $db->prepare($saveQuery);
        $saveProfile->execute(array(
            ':account_id' => $_SESSION['account']['id'],
            ':name' => $_POST['name'],
            ':badge_name' => $_POST['badge_name'],
            ':contact_email' => $_POST['contact_email'],
            ':website' => $_POST['website'] ?? '',
            ':biography' => $_POST['biography'],
            ':intersectionalities' => $_POST['intersectionalities'] ?? '',

            ':signing' => $_POST['signing'] === 'yes',
            ':reading' => !empty($_POST['reading']) && $_POST['reading'] === 'yes',
            ':moderator' => $_POST['moderator'] === 'yes',
            ':recording' => $_POST['recording'] === 'yes',
            ':share_email' => $_POST['share_email'] === 'yes',
        ));
        // no affected rows if no changes, so only INSERT
        if (!empty($panelist) || $saveProfile->rowCount() === 1) {
            if (empty($panelist)) {
                $getPanelist->execute(array(':id' => $_SESSION['account']['id']));
                $panelist = $getPanelist->fetch(PDO::FETCH_ASSOC);
            }

            // TODO: make it work right… Don't wipe and reset, remove the removed ones
            $removeOldTopics = $db->prepare('DELETE FROM panelists_topics WHERE panelist_id = :id');
            $removeOldTopics->execute(array(':id' => $panelist['id']));

            $addedTopics = array();
            foreach (array_keys($_POST['topic']) as $id) {
                array_push($addedTopics, $panelist['id'], $id);
            }
            $addTopicsQuery = 'INSERT INTO panelists_topics (panelist_id, topic_id) VALUES';
            $addTopicsQuery .= implode(', ', array_fill(0, count($addedTopics) / 2, '(?, ?)'));
            $addTopics = $db->prepare($addTopicsQuery);
            $addTopics->execute($addedTopics);
            if ($addTopics->rowCount() === (count($addedTopics) / 2)) {
                header('Location: /panels');
                exit;
            } else {
                $error = 'We failed to save your topics. I don\'t know why. Try again?';
            }
        } else {
            $error = 'We failed to save your profile. I don\'t know why. Try again?';
        }
    } else {
        // client-side validation should catch most missing fields, so simple check and fail
        if (empty($_POST['topic']))
            $error = 'You must select at least one interesting type of panel';
        else
            $error = 'Please complete all required sections of the form, marked with a *';
    }
}
?>
<?php
function value($key) {
    global $panelist;
    return $_POST[$key] ?? $panelist[$key] ?? '';
}
function topicValue($id) {
    global $myTopics;
    return !empty($_POST['topic'][$id]) || in_array($id, $myTopics);
}
function valueIs($key, $check) {
    global $panelist;
    if (!empty($_POST[$key]))
        return $_POST[$key] === ($check ? 'yes' : 'no');
    if (!empty($panelist))
        return $panelist[$key] === ($check ? '1' : '0');
    return false;
}
?>
<?php function booleanForm($name, $required = true) { ?>
    <div class="shrinkwrap">
        <input type="radio" id="<?= $name ?>-yes" name="<?= $name ?>" value="yes"<?= $required ? ' required' : '' ?><?= valueIs($name, true) ? ' checked' : '' ?>>
        <label for="<?= $name ?>-yes">Yes</label>
    </div>
    <div class="shrinkwrap">
        <input type="radio" id="<?= $name ?>-no" name="<?= $name ?>" value="no"<?= $required ? ' required' : '' ?><?= valueIs($name, false) ? ' checked' : '' ?>>
        <label for="<?= $name ?>-no">No</label>
    </div>
<?php } ?>

<?php $year = 2020; /* TODO: auto-calculate */ ?>
<p>Welcome to the <?= $year; ?> LTUE Call for Panelists! Please sign up for panels for which you are interested and qualified. Please note that expressing interest will not automatically put you on a panel. As we create our schedule, we will notify you if/when you have been selected for a panel.</p>
<p><strong>This Call for Panelists will close on September 1<sup>st</sup>.</strong> We will begin contacting those who have been selected for panels in November.</p>
<p><strong>LTUE is Thurday Feb. 13<sup>th</sup> to Saturday Feb. 15<sup>th</sup>, 2020.</strong></p>
<form method="POST">
<?php if (!empty($error)): ?>
    <output class="error"><?= $error ?></output>
<?php endif; ?>
    <label class="required" for="name">What would we know you as?</label>
    <input type="text" id="name" name="name" required value="<?= value('name') ?>">

    <label class="required" for="badge_name">Name as you would like it to appear on badge and table tent (Pen Name)</label>
    <input type=text" id="badge_name" name="badge_name" required value="<?= value('badge_name') ?>">

    <label class="required" for="contact_email">Contact Email:</label>
    <input type="email" id="contact_email" name="contact_email" required value="<?= value('contact_email') ?: $_SESSION['account']['email'] ?>">

    <label for="website">Website</label>
    <input type="url" id="website" name="website" value="<?= value('website') ?>">

    <label class="long required" for="biography">We would like you biography to appear in the program book and online. Please note that we will have extremely limited space, so please keep it to 400 characters or less. This should be a business bio stating your professional credits and the reasons that an attendee would want to come year you speak.</label>
    <textarea id="biography" name="biography" required maxlength=500><?= value('biography') ?></textarea>

    <label class="long" for="intersectionalities">Please describe any <span title="age, race, gender, sexual preferences, etc">intersectionalities</span> you would like us to know about you.</label>
    <textarea id="intersectionalities" name="intersectionalities"><?= value('intersectionalities') ?></textarea>

    <!-- TODO: preview or iframe, as in Mike's example -->
    <label class="long" for="picture">We would like to use your head shot in promotions and social media. Please upload your photo here. This should be a professional style photo that shows your face clearly and has a unobtrusive background.</label>
    <input type="file" name="picture" id="picture">

    <!-- TODO: books - why only 3? -->

    <label class="required">Are you interested in participating in the LTUE Mass Signing on Friday February 14<sup>th</sup> from 6:30 pm to 8:00 pm?</label>
    <?php booleanForm('signing'); ?>

    <label>Are you interested in doing a reading of one of your books?</label>
    <?php booleanForm('reading', false); ?>

    <label class="required">Are you willing to be a moderator?</label>
    <?php booleanForm('moderator'); ?>

    <label class="required">Is it okay if LTUE records your panels/presentations during the event?</label>
    <?php booleanForm('recording'); ?>

    <p class="required">Is it okay if LTUE shares your email with other panelists/moderators who are assigned to the same panels as you? Sharing your email will allow the moderator to contact you and coordinate seed questions.</p>
    <p><strong>Note:</strong> LTUE has a strict confidentiality policy, and will never share your email address without your express permission.</p>
    <?php booleanForm('share_email'); ?>

    <!-- TODO: presentation - 3 again -->

    <!-- TODO: available hours… -->

    <label class="long required">Which types of panels are you interested in? (mark all that apply) We will only show panels related to your selections and time frame in the next section</label>
    <?php foreach ($topics as $id => $name): ?>
    <div class="shrinkwrap">
        <input type="checkbox" id="topic-<?= $id ?>" name="topic[<?= $id ?>]"<?= topicValue($id) ? ' checked' : '' ?>>
        <label for="topic-<?= $id ?>"><?= htmlspecialchars($name, ENT_QUOTES) ?></label>
    </div>
    <?php endforeach; ?>

    <input type="submit" value="Update Profile">
</form>
