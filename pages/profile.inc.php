<?php defined('INCLUDED') or die(); ?>
<?php $title = 'Profile Form' ?>
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

function handleForm() {
    global $db, $panelist, $topics;

    if (!empty($_POST['biography']) && strlen($_POST['biography']) > 500)
        return 'Your biography is too long. Please reduce it.';
    if (!empty($_POST['topic']) && !is_array($_POST['topic']))
        return 'Your data is somehow corrupted - please contact us and show us what you did so we can fix it.';

    $sufficientData = !empty($_POST['name']) && !empty($_POST['badge_name']) &&
        !empty($_POST['contact_email']) && !empty($_POST['biography']) && !empty($_POST['topic']) &&
        !empty($_POST['signing']) && !empty($_POST['moderator']) &&
        !empty($_POST['recording']) && !empty($_POST['share_email']);
    if (!$sufficientData) {
        // client-side validation should catch most missing fields, so simple check and fail
        if (empty($_POST['topic']))
            return 'You must select at least one interesting type of panel';
        else
            return 'Please complete all required sections of the form, marked with a *';
    }

    $profileSet = '
        name = :name, badge_name = :badge_name, contact_email = :contact_email, website = :website,
        biography = :biography, intersectionalities = :intersectionalities,
        signing = :signing, reading = :reading, moderator = :moderator,
        recording = :recording, share_email = :share_email
    ';
    $data = [
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
    ];

    if (empty($panelist))
        $saveQuery = 'INSERT INTO panelists SET account_id = :account_id, ' . $profileSet;
    else
        $saveQuery = 'UPDATE panelists SET ' . $profileSet . ' WHERE account_id = :account_id';
    $saveProfile = $db->prepare($saveQuery);
    $saveProfile->execute($data);

    // no affected rows if no changes, so only INSERT
    if (empty($panelist) && $saveProfile->rowCount() !== 1)
        return 'We failed to save your profile. I don\'t know why. Try again?';

    if (empty($panelist)) {
        $getPanelist->execute(array(':id' => $_SESSION['account']['id']));
        $panelist = $getPanelist->fetch(PDO::FETCH_ASSOC);
    }

    if (empty($_FILES['picture']))
        return 'Error - picture missing';
    // TODO: needs a way to clear the picture as well, not just replace
    if ($_FILES['picture']['tmp_name']) {
        $ext = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
        switch ($ext) {
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            break;
        default:
            return 'File extension unknown - please upload a .jpeg, .jpg, .gif, or .png';
        }

        if ($_FILES['picture']['size'] > 1000000)
            return 'Photo file too large';

        $uploadDir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'uploads';

        // remove prior file
        if ($panelist['photo_file']) {
            if (!unlink($uploadDir . DIRECTORY_SEPARATOR . $panelist['photo_file']))
                return 'Failed to clear prior photo - please contact support';
        }

        $photoFile = 'picture_' . $panelist['id'] . '.' . $ext;
        if (!move_uploaded_file($_FILES['picture']['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $photoFile))
            return 'Failed to store your photo - please contact support';

        $savePhoto = $db->prepare('UPDATE panelists SET photo_file = ? WHERE account_id = ?');
        $savePhoto->execute([$photoFile, $panelist['id']]);
        if ($savePhoto->rowCount() !== 1)
            return 'Failed to save your photo - please contact support';

        $panelist['photo_file'] = $photoFile;
    }

    // TODO: make it work right… Don't wipe and reset, remove the removed ones
    $removeOldTopics = $db->prepare('DELETE FROM panelists_topics WHERE panelist_id = :id');
    $removeOldTopics->execute(array(':id' => $panelist['id']));

    // TODO: check the id exists? No major risk, probably…
    $addedTopics = array();
    foreach (array_keys($_POST['topic']) as $id) {
        array_push($addedTopics, $panelist['id'], $id);
    }
    $addTopicsQuery = 'INSERT INTO panelists_topics (panelist_id, topic_id) VALUES';
    $addTopicsQuery .= implode(', ', array_fill(0, count($addedTopics) / 2, '(?, ?)'));
    $addTopics = $db->prepare($addTopicsQuery);
    $addTopics->execute($addedTopics);

    if ($addTopics->rowCount() !== (count($addedTopics) / 2))
        return 'We failed to save your topics. I don\'t know why. Try again?';

    header('Location: /panels');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = handleForm();
}
?>
<?php
function value($key) {
    global $panelist;
    return htmlspecialchars($_POST[$key] ?? $panelist[$key] ?? '', ENT_QUOTES);
}
function topicValue($id) {
    global $myTopics;
    return !empty($_POST['topic'][$id]) || in_array($id, $myTopics);
}
function bookValue($id, $field) {
    // TODO: my books
    if (!empty($_POST['books']) && !empty($_POST['books'][$id]) && !empty($_POST['books'][$id][$field]))
        return htmlspecialchars($_POST['books'][$id][$field], ENT_QUOTES);
}
function suggestionValue($id, $field) {
    // TODO: my suggestions
    if (!empty($_POST['suggestions']) && !empty($_POST['suggestions'][$id]) && !empty($_POST['suggestions'][$id][$field]))
        return htmlspecialchars($_POST['suggestions'][$id][$field], ENT_QUOTES);
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
<p>LTUE is Thurday Feb. 13<sup>th</sup> to Saturday Feb. 15<sup>th</sup>, 2020.</p>
<p><strong>You must click Update Profile</strong> in order to save this form.</p>
<form method="POST" enctype="multipart/form-data">
<?php if (!empty($error)): ?>
    <output class="error"><?= $error ?></output>
<?php endif; ?>
    <label class="required" for="name">What would we know you as?</label>
    <input type="text" id="name" name="name" required value="<?= value('name') ?>">

    <label class="required" for="badge_name">Badge Name</label>
    <input type=text" id="badge_name" name="badge_name" required value="<?= value('badge_name') ?>">
    <p class="explanation">Name as you would like it to appear on badge and table tent (Pen Name)</p>

    <label class="required" for="contact_email">Contact Email:</label>
    <input type="email" id="contact_email" name="contact_email" required value="<?= value('contact_email') ?: $_SESSION['account']['email'] ?>">
    <p class="explanation">You will be notified if you are accepted as a panelist/presenter through this email.</p>

    <label for="website">Website</label>
    <input type="url" id="website" name="website" value="<?= value('website') ?>">

    <label class="required" for="biography">Short bio</label>
    <textarea id="biography" name="biography" required maxlength=500><?= value('biography') ?></textarea>
    <p class="explanation">We would like your biography to appear in the program book and online. Please note that we will have extremely limited space, so please keep it to 400 characters or less. This should be a business bio stating your professional credits and the reasons that an attendee would want to come hear you speak.</p>

    <label class="long" for="intersectionalities">Additional info</label>
    <textarea id="intersectionalities" name="intersectionalities"><?= value('intersectionalities') ?></textarea>
    <p class="explanation">Please describe any <span title="age, race, gender, sexual preferences, etc">intersectionalities</span> you would like us to know about you.</p>

    <!-- TODO: preview or iframe, as in Mike's example -->
    <input type="hidden" name="MAX_FILE_SIZE" value="1000000" />
    <label for="picture">Profile Photo</label>
    <?php if ($panelist['photo_file']): ?>
    <figure id="current-picture">
        <img src="/uploads/<?= $panelist['photo_file'] ?>" />
        <figcaption>Our current photo of you.</figcaption>
    </figure>
    <?php endif; ?>
    <input type="file" name="picture" id="picture">
    <p class="explanation">We would like to use your head shot in promotions and social media. Please upload your photo here. This should be a professional style photo that shows your face clearly and has a unobtrusive background.</p>

    <p>If you would like one of LTUE's book partners to carry your books on consignment or through traditional sale (note: <span title="our sellers can only carry so many books">3 title limit</span>), please enter the information below.</p>
    <table>
        <tr>
            <th></th>
            <th>Title</th>
            <th>Author (on cover)</th>
            <th>ISBN</th>
        </tr>
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <tr>
            <th><?= $i ?>.</th>
            <td><input type="text" name="books[<?= $i ?>][title]" value="<?= bookValue($i, 'title') ?>" /></td>
            <td><input type="text" name="books[<?= $i ?>][author]" value="<?= bookValue($i, 'author') ?>" /></td>
            <td><input type="text" name="books[<?= $i ?>][isbn]" value="<?= bookValue($i, 'isbn') ?>" /></td>
        </tr>
        <?php endfor; ?>
    </table>

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
    <p>Do you have a presentation or a workshop that you would like to run? If so, please give us a title, description, and a pitch for the programming. We will contact you with possible time slots in November, if your presentation is selected.</p>
    <table>
        <tr>
            <th></th>
            <th>Title</th>
            <th>Description</th>
            <th>Pitch</th>
        </tr>
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <tr>
            <th><?= $i ?>.</th>
            <td><input type="text" name="suggestions[<?= $i ?>][title]" value="<?= suggestionValue($i, 'title') ?>" /></td>
            <td><input type="text" name="suggestions[<?= $i ?>][description]" value="<?= suggestionValue($i, 'description') ?>" /></td>
            <td><input type="text" name="suggestions[<?= $i ?>][pitch]" value="<?= suggestionValue($i, 'pitch') ?>" /></td>
        </tr>
        <?php endfor; ?>
    </table>

    <!-- TODO: available hours… -->
    <p class="required">Which times will you be available and would like to see programming for?</p>
    <table class="availability">
        <tr>
            <th></th>
            <th>9 am - 11:45 am</th>
            <th>noon - 3:45 pm</th>
            <th>4:00 pm - end of day</th>
        </tr>
        <tr>
            <th>Thursday Feb. 13, 2020</th>
            <td><label><input type="checkbox" name="available[thu][morn]" value="TODO" /></label></td>
            <td><label><input type="checkbox" name="available[thu][day]" value="TODO" /></label></td>
            <td><label><input type="checkbox" name="available[thu][even]" value="TODO" /></label></td>
        </tr>
        <tr>
            <th>Friday Feb. 14, 2020</th>
            <td><label><input type="checkbox" name="available[fri][morn]" value="TODO" /></label></td>
            <td><label><input type="checkbox" name="available[fri][day]" value="TODO" /></label></td>
            <td><label><input type="checkbox" name="available[fri][even]" value="TODO" /></label></td>
        </tr>
        <tr>
            <th>Saturday Feb. 15, 2020</th>
            <td><label><input type="checkbox" name="available[sat][morn]" value="TODO" /></label></td>
            <td><label><input type="checkbox" name="available[sat][day]" value="TODO" /></label></td>
            <td><label><input type="checkbox" name="available[sat][even]" value="TODO" /></label></td>
        </tr>
    </table>

    <label class="long required">Which types of panels are you interested in? (mark all that apply) We will only show panels related to your selections and time frame in the next section</label>
    <?php foreach ($topics as $id => $name): ?>
    <div class="shrinkwrap">
        <input type="checkbox" id="topic-<?= $id ?>" name="topic[<?= $id ?>]"<?= topicValue($id) ? ' checked' : '' ?>>
        <label for="topic-<?= $id ?>"><?= htmlspecialchars($name, ENT_QUOTES) ?></label>
    </div>
    <?php endforeach; ?>

    <input type="submit" value="Update Profile">
</form>
