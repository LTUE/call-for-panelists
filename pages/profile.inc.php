<?php defined('INCLUDED') or die(); ?>
<?php $title = 'Profile Form' ?>
<?php

define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2 MiB

if (!empty($_SESSION['account_id'])) {
    $getAccount = $db->prepare('SELECT email FROM accounts WHERE id = :id');
    $getAccount->execute(array(':id' => $_SESSION['account_id']));
    $account = $getAccount->fetch(PDO::FETCH_ASSOC);
}

$getPanelist = $db->prepare('SELECT * FROM panelists WHERE id = :id');
$panelist = NULL;
if (!empty($_SESSION['panelist_id'])) {
    $getPanelist->execute(array(':id' => $_SESSION['panelist_id']));
    $panelist = $getPanelist->fetch(PDO::FETCH_ASSOC);
} else if (!empty($_SESSION['account_id'])) {
    $getPanelistFromAccount = $db->prepare('SELECT * FROM panelists WHERE account_id = :account_id');
    $getPanelistFromAccount->execute(array(':account_id' => $_SESSION['account_id']));
    $panelist = $getPanelistFromAccount->fetch(PDO::FETCH_ASSOC);
    if (!empty($panelist)) {
        $_SESSION['panelist_id'] = $panelist['id'];
    }
}

function loadHABTM($table, $key, $value, $order, $join_key) {
    global $db, $panelist;
    $data = array_column($db->query(
        "SELECT $key, $value FROM $table ORDER BY $order"
    )->fetchAll(PDO::FETCH_ASSOC), $value, $key);

    $myData = array();
    if (!empty($panelist)) {
        $myDataQuery = $db->prepare("SELECT $join_key FROM panelists_$table WHERE panelist_id = :id");
        $myDataQuery->execute(array(':id' => $panelist['id']));
        $myData = $myDataQuery->fetchAll(PDO::FETCH_COLUMN);
    }
    return array('data' => $data, 'mine' => $myData);
}
function saveHABTM($data, $field, $join_key) {
    global $db, $panelist;

    // start with the prior list, remove from remove list as found
    $removed = $data['mine'];
    $added = array();

    foreach (array_keys($_POST[$field]) as $id) {
        if (!array_key_exists($id, $data['data']))
            continue;

        $index = array_search($id, $removed);
        if (false !== $index) {
            array_splice($removed, $index, 1); // nope, not removed
        } else {
            array_push($added, $panelist['id'], $id);
        }
    }

    if (count($added)) {
        $addTopicsQuery = "INSERT INTO panelists_$field (panelist_id, $join_key) VALUES";
        $addTopicsQuery .= implode(', ', array_fill(0, count($added) / 2, '(?, ?)'));
        $addTopics = $db->prepare($addTopicsQuery);
        $addTopics->execute($added);

        if ($addTopics->rowCount() !== (count($added) / 2))
            return "We failed to save your $field. I don't know why. Try again?";
    }

    // we don't need to delete where nothing matches, if we set up the ON
    // DELETE CASCADE in the database correctly
    if (count($removed)) {
        $removeTopics = $db->prepare("DELETE FROM panelists_$field WHERE panelist_id = ? AND $join_key IN (" .
            implode(', ', array_fill(0, count($removed), '?')) .
        ')');
        $removeTopics->execute(array_merge([$panelist['id']], $removed));
        if ($removeTopics->rowCount() !== count($removed))
            return "Something went wrong updating your $field. Try again?";
    }

    return;
}

$topics = loadHABTM('topics', 'id', 'name', 'name', 'topic_id');


$myBooks = [];
$myAvailability = [];
if ($panelist) {
    $getMyBooks = $db->prepare(
        'SELECT position, title, author, isbn FROM books_to_stock WHERE panelist_id = :id'
    );
    $getMyBooks->execute(array(':id' => $panelist['id']));
    foreach ($getMyBooks->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $myBooks[$row['position']] = $row;
    }

    $getMyAvailability = $db->prepare('SELECT * FROM panelist_availability WHERE panelist_id = :id');
    $getMyAvailability->execute(array(':id' => $panelist['id']));
    $myAvailability = $getMyAvailability->fetch(PDO::FETCH_ASSOC);
}

function handleForm() {
    global $db, $panelist;

    if (!empty($_POST['biography']) && strlen($_POST['biography']) > 500)
        return 'Your biography is too long. Please reduce it.';
    if (!empty($_POST['topics']) && !is_array($_POST['topics']))
        return 'Your data is somehow corrupted - please contact us and show us what you did so we can fix it.';

    if (!empty($_POST['reading']) && $_POST['reading'] === 'yes' && empty($_POST['reading_topic']))
        return 'You must provide a title, topic, or genera which you intend to read';
    else if (empty($_POST['reading']) || $_POST['reading'] === 'no')
        $_POST['reading_topic'] = '';

    if (!empty($_POST['reading_topic']) && $_POST['reading_topic'] > 100)
        return 'Your reading book/style/genera description is too long';

    for ($i = 1; $i <= 3; $i++) {
        if (empty($_POST['books'][$i]))
            continue;
        $data = $_POST['books'][$i];
        if (!empty($data['title']) && strlen($data['title']) > 50)
            return 'Validation failed - no book title may exceed 50 characters';
        if (!empty($data['author']) && strlen($data['author']) > 50)
            return 'Validation failed - no book author may exceed 50 characters';
        if (!empty($data['isbn']) && strlen($data['isbn']) > 50)
            return 'Validation failed - no book ISBN may exceed 20 characters';
    }

    $sufficientData = !empty($_POST['name']) && !empty($_POST['badge_name']) &&
        !empty($_POST['contact_email']) && !empty($_POST['biography']) &&
        !empty($_POST['topics']) && !empty($_POST['available']) &&
        !empty($_POST['signing']) &&
        !empty($_POST['moderator']) &&
        !empty($_POST['recording']) && !empty($_POST['share_email']);
    if (!$sufficientData) {
        // client-side validation should catch most missing fields, so simple check and fail
        if (empty($_POST['topics']))
            return 'You must select at least one interesting type of panel';
        else if (empty($_POST['available']))
            return 'You must select at least one availability group';
        else
            return 'Please complete all required sections of the form, marked with a *';
    }

    // optional fields - sanitize
    function threeState($field) {
        if (isset($_POST[$field])) {
            switch ($_POST[$field]) {
            case 'yes':
                $_POST[$field] = true;
                break;
            case 'no':
                $_POST[$field] = false;
                break;
            default:
                $_POST[$field] = null;
                break;
            }
        }
    }
    threeState('person_of_color');
    threeState('disability');
    threeState('lgbtqia_plus');
    if (!empty($_POST['gender_type'])) {
        if ($_POST['gender_type'] === 'null' || $_POST['gender_type'] < -1 || $_POST['gender_type'] > 1)
            $_POST['gender_type'] = null;
    }

    // Validation done - good to save

    $fields = array(
        'name', 'badge_name', 'contact_email', 'biography', 'info',
        'website', 'facebook', 'twitter', 'instagram', 'other_social',
        'person_of_color', 'disability', 'gender_type', 'lgbtqia_plus',
        'signing', 'reading', 'moderator', 'judge', 'recording', 'share_email',
        'updated',
    );
    $profileSet = implode(', ', array_map(function($field) {
        return "$field = :$field";
    }, $fields));
    $data = [
        ':name' => $_POST['name'],
        ':badge_name' => $_POST['badge_name'],
        ':contact_email' => $_POST['contact_email'],
        ':biography' => $_POST['biography'],
        ':info' => $_POST['info'] ?? '',

        ':website' => $_POST['website'] ?? '',
        ':facebook' => $_POST['facebook'] ?? '',
        ':twitter' => $_POST['twitter'] ?? '',
        ':instagram' => $_POST['instagram'] ?? '',
        ':other_social' => $_POST['other_social'] ?? '',

        ':person_of_color' => $_POST['person_of_color'] ?? null,
        ':disability' => $_POST['disability'] ?? null,
        ':gender_type' => $_POST['gender_type'] ?? null,
        ':lgbtqia_plus' => $_POST['lgbtqia_plus'] ?? null,

        ':signing' => $_POST['signing'] === 'yes',
        ':reading' => $_POST['reading_topic'],
        ':moderator' => $_POST['moderator'] === 'yes',
        ':judge' => !empty($_POST['judge']) ? $_POST['judge'] === 'yes' : null,
        ':recording' => $_POST['recording'] === 'yes',
        ':share_email' => $_POST['share_email'] === 'yes',

        ':updated' => date('Y-m-d H:i:s'),
    ];

    if (empty($panelist)) {
        $saveQuery = 'INSERT INTO panelists SET account_id = :account_id, registered = :registered, ' . $profileSet;
        $data[':account_id'] = $_SESSION['account_id'] ?? NULL;
        $data[':registered'] = $data[':updated'];
    } else {
        $saveQuery = 'UPDATE panelists SET ' . $profileSet . ' WHERE id = :id';
        $data[':id'] = $_SESSION['panelist_id'];
    }
    $saveProfile = $db->prepare($saveQuery);
    $saveProfile->execute($data);

    // no affected rows if no changes, so only INSERT
    if (empty($panelist) && $saveProfile->rowCount() !== 1)
        return 'We failed to save your profile. I don\'t know why. Try again?';

    if (empty($panelist)) {
        global $getPanelist;
        session_regenerate_id(true); // further prevent fixation attacks
        $_SESSION['panelist_id'] = $db->lastInsertId();
        $getPanelist->execute(array(':id' => $_SESSION['panelist_id']));
        $panelist = $getPanelist->fetch(PDO::FETCH_ASSOC);
    }

    if (empty($_FILES['picture']))
        return 'Error - picture missing';
    // TODO: needs a way to clear the picture as well, not just replace
    if ($_FILES['picture']['tmp_name']) {
        $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
        switch ($ext) {
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            break;
        default:
            return 'File extension unknown - please upload a .jpeg, .jpg, .gif, or .png';
        }

        if ($_FILES['picture']['size'] > MAX_UPLOAD_SIZE)
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

        $savePhoto = $db->prepare('UPDATE panelists SET photo_file = ? WHERE id = ?');
        $savePhoto->execute([$photoFile, $panelist['id']]);
        if ($savePhoto->rowCount() !== 1)
            return 'Failed to save your photo - please contact support';

        $panelist['photo_file'] = $photoFile;
    }

    global $topics;
    $error = saveHABTM($topics, 'topics', 'topic_id');
    if ($error)
        return $error;


    // TODO: slightly less lazy books
    // TODO: field limits!
    $removeBooks = $db->prepare('DELETE FROM books_to_stock WHERE panelist_id = :id');
    $removeBooks->execute(array(':id' => $panelist['id']));
    $books = [];
    for ($i = 1; $i <= 3; $i++) {
        if (empty($_POST['books'][$i]))
            continue;
        $data = $_POST['books'][$i];
        if (empty($data['title']) && empty($data['author']) && empty($data['isbn']))
            continue;
        array_push(
            $books, $panelist['id'], $i,
            $data['title'] ?? '', $data['author'] ?? '', $data['isbn'] ?? ''
        );
    }
    if (count($books)) {
        $addBooks = $db->prepare(
            'INSERT INTO books_to_stock (panelist_id, position, title, author, isbn) VALUES ' .
            implode(', ', array_fill(0, count($books) / 5, '(?, ?, ?, ?, ?)'))
        );
        $addBooks->execute($books);
        if ($addBooks->rowCount() !== (count($books) / 5)) {
            return 'Failed to save book suggestions - please try again or contact support';
        }
    }

    // TODO: bad architecture; should do something w/time ranges instead.
    // TODO: remove interested panels when changing availability!
    // TODO: same for categories - remove interest when removing categories
    $setAvailability = $db->prepare(
        'INSERT INTO panelist_availability (' .
            'panelist_id, thu_morn, thu_day, thu_even, fri_morn, fri_day, fri_even, sat_morn, sat_day, sat_even' .
        ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' .
        ' ON DUPLICATE KEY UPDATE ' .
        'thu_morn = VALUES(thu_morn), thu_day = VALUES(thu_day), thu_even = VALUES(thu_even), ' .
        'fri_morn = VALUES(fri_morn), fri_day = VALUES(fri_day), fri_even = VALUES(fri_even), ' .
        'sat_morn = VALUES(sat_morn), sat_day = VALUES(sat_day), sat_even = VALUES(sat_even)'
    );
    $setAvailability->execute(array(
        $panelist['id'],
        !empty($_POST['available']['thu']['morn']),
        !empty($_POST['available']['thu']['day']),
        !empty($_POST['available']['thu']['even']),
        !empty($_POST['available']['fri']['morn']),
        !empty($_POST['available']['fri']['day']),
        !empty($_POST['available']['fri']['even']),
        !empty($_POST['available']['sat']['morn']),
        !empty($_POST['available']['sat']['day']),
        !empty($_POST['available']['sat']['even']),
    ));

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
function habtmChecklist($data, $field) {
    ob_start();
    foreach ($data['data'] as $id => $name):
        $checked = !empty($_POST[$field][$id]) || in_array($id, $data['mine']);
?>
    <div class="shrinkwrap">
        <input type="checkbox" id="<?= $field ?>-<?= $id ?>" name="<?= $field ?>[<?= $id ?>]"<?= $checked ? ' checked' : '' ?>>
        <label for="<?= $field ?>-<?= $id ?>"><?= htmlspecialchars($name, ENT_QUOTES) ?></label>
    </div>
    <?php endforeach;
    return ob_get_clean();
}
function bookValue($id, $field) {
    global $myBooks;
    if (!empty($_POST['books']) && !empty($_POST['books'][$id]) && !empty($_POST['books'][$id][$field]))
        return htmlspecialchars($_POST['books'][$id][$field], ENT_QUOTES);
    if (empty($_POST) && !empty($myBooks[$id]))
        return htmlspecialchars($myBooks[$id][$field], ENT_QUOTES);
}
function availabilityValue($day, $part) {
    global $myAvailability;
    return !empty($_POST['available'][$day][$part]) || (
        empty($_POST) && !empty($myAvailability[$day . '_' . $part])
    );
}
function fieldValue($key) {
    global $panelist;
    if (!empty($_POST[$key]))
        return $_POST[$key];
    if (!empty($panelist))
        return $panelist[$key];
    return null;
}
function valueIs($key, $check) {
    global $panelist;
    if (!empty($_POST[$key]))
        return $_POST[$key] === $check;
    if (!empty($panelist))
        return $panelist[$key] === $check;
    return false;
}
function valueIsBool($key, $check) {
    global $panelist;
    if (!empty($_POST[$key]))
        return $_POST[$key] === ($check ? 'yes' : 'no');
    if (!empty($panelist))
        return $panelist[$key] === ($check ? '1' : '0');
    return false;
}
function readingValue() {
    global $panelist;
    return $_POST['reading_topic'] ?? $panelist['reading'] ?? '';
}
function radioOption($name, $value, $label, $checked, $required = false) {
    ob_start(); ?>
    <div class="shrinkwrap">
        <input type="radio" id="<?= $name ?>-<?= $value ?>" name="<?= $name ?>" value="<?= $value ?>"<?= $required ? ' required' : '' ?><?= $checked ? ' checked' : '' ?>>
        <label for="<?= $name ?>-<?= $value ?>"><?= $label ?></label>
    </div>
<?php
    return ob_get_clean();
}
function booleanForm($name, $required = true) {
    ob_start(); ?>
    <?= radioOption($name, 'yes', 'Yes', valueIsBool($name, true), $required) ?>
    <?= radioOption($name, 'no', 'No', valueIsBool($name, false), $required) ?>
<?php
    return ob_get_clean();
}
?>

<?php $year = 2022; /* TODO: auto-calculate */ ?>
<p>Welcome to the <?= $year; ?> LTUE Call for Panelists! Please sign up for panels for which you are interested and qualified. Please note that expressing interest will not automatically put you on a panel. As we create our schedule, we will notify you if/when you have been selected for a panel.</p>
<p>We highly recommend submitting a presentation/workshop this year. That is available on a separate form here: <a href="https://forms.gle/nVAoC5ExYguXnC2d8" target="_blank">https://forms.gle/nVAoC5ExYguXnC2d8</a>.</p>
<p><strong>This Call for Panelists will close on September 7<sup>th</sup>.</strong> We will begin contacting those who have been selected for panels in November.</p>
<p>LTUE is Thurday Feb. 17<sup>th</sup> to Saturday Feb. 19<sup>th</sup>, 2022.</p>
<p><strong>You must click Update Profile</strong> in order to save this form.</p>
<form id="profile" method="POST" enctype="multipart/form-data">
<?php if (!empty($error)): ?>
    <output class="error"><?= $error ?></output>
<?php endif; ?>
    <section id="personal-info">
        <h2>Personal Info<a href="#personal-info">#</a></h2>
        <label class="required" for="name">What is your name?</label>
        <input type="text" id="name" name="name" required value="<?= value('name') ?>">

        <label class="required" for="badge_name">Badge Name</label>
        <input type=text" id="badge_name" name="badge_name" required value="<?= value('badge_name') ?>">
        <p class="explanation">Name as you would like it to appear on badge and table tent (Pen Name)</p>

        <label class="required" for="contact_email">Contact Email:</label>
        <input type="email" id="contact_email" name="contact_email" required value="<?= value('contact_email') ?: ($account['email'] ?? '') ?>">
        <p class="explanation">You will be notified if you are accepted as a panelist/presenter through this email.</p>

        <label class="required" for="biography">Short bio</label>
        <textarea id="biography" name="biography" required maxlength=500><?= value('biography') ?></textarea>
        <p class="explanation">We would like your biography to appear in the program book and online. Please note that we will have extremely limited space, so please keep it to 400 characters or less. This should be a business bio stating your professional credits and the reasons that an attendee would want to come hear you speak. Bios should be written in <strong>third person</strong> please, or they will be edited. Bios may also be edited for brevity. Your website will be added, so it does not need to be included here.</p>

        <label class="long" for="info">Additional info</label>
        <textarea id="info" name="info"><?= value('info') ?></textarea>
        <p class="explanation">Please provide any additional information that can help us understand your qualifications, the type of work you do, your unique circumstances, etc.</p>
    </section>

    <section id="social-media" class="floated">
        <h2>Social Media<a href="#social-media">#</a></h2>
        <div class="half-width">
            <label for="website">Website</label>
            <input type="url" id="website" name="website" value="<?= value('website') ?>">
        </div>
        <div class="half-width">
            <label for="facebook">Facebook</label>
            <input type="url" id="facebook" name="facebook" value="<?= value('facebook') ?>">
        </div>
        <div class="half-width">
            <label for="twitter">Twitter</label>
            <input type="url" id="twitter" name="twitter" value="<?= value('twitter') ?>">
        </div>
        <div class="half-width">
            <label for="instagram">Instagram</label>
            <input type="url" id="instagram" name="instagram" value="<?= value('instagram') ?>">
        </div>
        <div class="half-width">
            <label for="other_social">Other Social Media</label>
            <input type="url" id="other_social" name="other_social" value="<?= value('other_social') ?>">
        </div>
    </section>

    <section id="demographics">
        <h2>Demographics<a href="#demographics">#</a></h2>
        <p>LTUE remains committed to diversity in our panels and presentations. This information will not be used to determine suitability for a panel &mdash; it will only be used afterwards to select among equally suitable candidates to enhance diversity. All fields are optional.</p>

        <label>Are you a person of color?</label>
        <?= booleanForm('person_of_color', false) ?>
        <div class="shrinkwrap">
            <input type="radio" id="person_of_color-null" name="person_of_color" value="null"<?= valueIs('person_of_color', null) ? ' checked' : '' ?>>
            <label for="person_of_color-null">Prefer not to say</label>
        </div>

        <label>Are you a person living with a disability or a chronic physical/mental illness?</label>
        <?= booleanForm('disability', false) ?>
        <div class="shrinkwrap">
            <input type="radio" id="disability-null" name="disability" value="null"<?= valueIs('disability', null) ? ' checked' : '' ?>>
            <label for="disability-null">Prefer not to say</label>
        </div>

        <label>Does any of the following describe you?</label>
        <?= radioOption('gender_type', 1, 'Cisgender (The sex you were assigned at birth matches your gender identity)', valueIs('gender_type', '1')) ?>
        <?= radioOption('gender_type', 0, 'Non-Binary', valueIs('gender_type', '0')) ?>
        <!-- Trans = "the other side", so -1. Not a value statement. -->
        <?= radioOption('gender_type', -1, 'Transgender', valueIs('gender_type', '-1')) ?>
        <div class="shrinkwrap">
            <input type="radio" id="gender_type-null" name="gender_type" value="null"<?= valueIs('gender_type', null) ? ' checked' : '' ?>>
            <label for="gender_type-null">Prefer not to say</label>
        </div>

        <label>Would you consider yourself LGBTQIA+?</label>
        <?= booleanForm('lgbtqia_plus', false) ?>
        <div class="shrinkwrap">
            <input type="radio" id="lgbtqia_plus-null" name="lgbtqia_plus" value="null"<?= valueIs('lgbtqia_plus', null) ? ' checked' : '' ?>>
            <label for="lgbtqia_plus-null">Prefer not to say</label>
        </div>
    </section>

    <section id="photo">
        <!-- TODO: preview or iframe, as in Mike's example -->
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_SIZE ?>" />
        <label for="picture">Profile Photo</label>
        <?php if ($panelist && $panelist['photo_file']): ?>
        <figure id="current-picture">
            <img src="/uploads/<?= $panelist['photo_file'] ?>" />
            <figcaption>Our current photo of you.</figcaption>
        </figure>
        <?php endif; ?>
        <input type="file" name="picture" id="picture">
        <p class="explanation">We would like to use your head shot in promotions and social media. Please upload your photo here. This should be a professional style photo that shows your face clearly and has a unobtrusive background.</p>
    </section>

    <section id="books">
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
                <td><input type="text" name="books[<?= $i ?>][title]" maxlength=50 value="<?= bookValue($i, 'title') ?>" /></td>
                <td><input type="text" name="books[<?= $i ?>][author]" maxlength=50 value="<?= bookValue($i, 'author') ?>" /></td>
                <td><input type="text" name="books[<?= $i ?>][isbn]" maxlength=20 value="<?= bookValue($i, 'isbn') ?>" /></td>
            </tr>
            <?php endfor; ?>
        </table>

        <label class="required">Are you interested in participating in the LTUE Mass Signing on Friday February 12<sup>th</sup> from 6:30 pm to 8:00 pm?</label>
        <?= booleanForm('signing') ?>
    </section>

    <section id="reading">
        <label>Would you be interested in doing a reading from one of your books or stories?</label>
        <div class="shrinkwrap">
            <input type="radio" id="reading-yes" name="reading" value="yes"<?= (!empty($_POST['reading']) ? $_POST['reading'] === 'yes' : !!readingValue()) ? ' checked' : '' ?>>
            <label for="reading-yes">Yes</label>

            <p class="explanation wide">During a reading, you will be performing one of your own works. You will be paired with another author also reading their work. Note that being given time to perform a reading is by invitation only.</p>
            <label for="reading-topic" class="required">What book/genre/style would you like to highlight?</LABel>
            <input id="reading-topic" name="reading_topic" maxlength=100 value="<?= readingValue() ?>" />
        </div>
        <div class="shrinkwrap">
            <input type="radio" id="reading-no" name="reading" value="no"<?= (!empty($_POST['reading']) ? $_POST['reading'] === 'no' : !readingValue() && !empty($panelist)) ? ' checked' : '' ?>>
            <label for="reading-no">No</label>
        </div>
    </section>

    <section id="privacy">
        <label class="required">Is it okay if LTUE records your panels/presentations during the event?</label>
        <?= booleanForm('recording') ?>

        <label class="required">Is it okay if LTUE shares your email with other panelists/moderators who are assigned to the same panels as you?</label>
        <?= booleanForm('share_email') ?>
        <p class="explanation wide"> Sharing your email will allow the moderator to contact you and coordinate seed questions. <strong>Note:</strong> LTUE has a strict confidentiality policy, and will never share your email address without your express permission.</p>
    </section>

    <section id="moderator">
        <h2>Moderator Interest<a href="#moderator">#</a></h2>
        <p class="label">If you are interested in being a moderator, please review the <a href="https://drive.google.com/file/d/1IPo7povWSiXCtzFIY6kGhIvDmrpHVVt9/view">moderator tips document</a>. You will be expected to attend a moderator training and follow those guidelines, which include:</p>
        <ol class="explanation">
            <li>Starting and ending the panel on time</li>
            <li>Proper Panel Preparation</li>
            <li>Asking open ended questions</li>
            <li>Ensuring that all panelists are given equal opportunity to speak</li>
            <li>Keeping the panel interesting and on-topic</li>
        </ol>
        <label class="required">Are you interested in being a Moderator?</label>
        <?= booleanForm('moderator') ?>
    </section>

    <section id="judge">
        <h2>Fiction Contest Judge</h2>
        <label>Are you interested in being a judge for an LTUE fiction contest this year? (We would email more specifics before finalizing the group of judges.)</label>
        <?= booleanForm('judge', false) ?>
    </section>

    <section id="availability">
        <h2>Timeframe Availability<a href="#availability">#</a></h2>
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
                <th>Thursday Feb. 17, 2022</th>
                <td><label><input type="checkbox" name="available[thu][morn]" <?= availabilityValue('thu', 'morn') ? 'checked ' : '' ?>/></label></td>
                <td><label><input type="checkbox" name="available[thu][day]" <?= availabilityValue('thu', 'day') ? 'checked ' : '' ?>/></label></td>
                <td><label><input type="checkbox" name="available[thu][even]" <?= availabilityValue('thu', 'even') ? 'checked ' : '' ?>/></label></td>
            </tr>
            <tr>
                <th>Friday Feb. 18, 2022</th>
                <td><label><input type="checkbox" name="available[fri][morn]"<?= availabilityValue('fri', 'morn') ? 'checked ' : '' ?>/></label></td>
                <td><label><input type="checkbox" name="available[fri][day]"<?= availabilityValue('fri', 'day') ? 'checked ' : '' ?>/></label></td>
                <td><label><input type="checkbox" name="available[fri][even]"<?= availabilityValue('fri', 'even') ? 'checked ' : '' ?>/></label></td>
            </tr>
            <tr>
                <th>Saturday Feb. 19, 2022</th>
                <td><label><input type="checkbox" name="available[sat][morn]"<?= availabilityValue('sat', 'morn') ? 'checked ' : '' ?>/></label></td>
                <td><label><input type="checkbox" name="available[sat][day]"<?= availabilityValue('sat', 'day') ? 'checked ' : '' ?>/></label></td>
                <td><label><input type="checkbox" name="available[sat][even]"<?= availabilityValue('sat', 'even') ? 'checked ' : '' ?>/></label></td>
            </tr>
        </table>
    </section>

    <section id="interests">
        <h2>Panel Category Interest<a href="#interests">#</a></h2>
        <label class="long required">Which types of panels could you confidently present? (mark all that apply) We will only show panels related to your selections and time frame in the next section</label>
        <?= habtmChecklist($topics, 'topics') ?>
    </section>

    <input type="submit" value="Update Profile">
</form>
