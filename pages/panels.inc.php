<?php defined('INCLUDED') or die(); ?>
<?php $title = 'Panel Selection' ?>
<?php
if (empty($_SESSION['panelist_id'])) {
    header('Location: /profile');
    exit;
}
$getPanelist = $db->prepare('SELECT * FROM panelists WHERE id = :id');
// TODO panelist id in session
$getPanelist->execute(array(':id' => $_SESSION['panelist_id']));
$panelist = $getPanelist->fetch(PDO::FETCH_ASSOC);
// TODO: flash message for forced navigation, or don't show the links, or something
if (empty($panelist)) {
    header('Location: /profile');
    exit;
}

$topics = array_column($db->query(
    'SELECT id, name FROM topics ORDER BY name ASC'
)->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');
$roles = array_column($db->query(
    'SELECT id, name FROM panel_roles ORDER BY name ASC'
)->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');
$experiences = array_column($db->query(
    'SELECT id, name FROM panel_experience ORDER BY id ASC'
)->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');


// TODO: this is really bad - we need to fix the table to have hour ranges
$getMyAvailability = $db->prepare('SELECT * FROM panelist_availability WHERE panelist_id = :id');
$getMyAvailability->execute(array(':id' => $panelist['id']));
$myAvailability = $getMyAvailability->fetch(PDO::FETCH_ASSOC);

// TODO: query got messier when I realized I wasn't showing all topics - clean it up later
$queryRelevantPanels = <<<SQL
    SELECT
        p.*, GROUP_CONCAT(DISTINCT t2.topic_id SEPARATOR ',') AS topic_ids,
        panel_roles_id, panel_experience_id, qualifications
    FROM panelists_topics AS pt
    INNER JOIN panels_topics AS t USING (topic_id)
    INNER JOIN panels AS p ON t.panel_id = p.id
    INNER JOIN panels_topics AS t2 ON p.id = t2.panel_id
    LEFT JOIN panelists_panels AS pp ON pp.panel_id = p.id AND pp.panelist_id = :panelist_id
    WHERE pt.panelist_id = :panelist_id
    GROUP BY p.id
SQL;
$getRelevantPanels = $db->prepare($queryRelevantPanels);
$getRelevantPanels->execute(array(':panelist_id' => $panelist['id']));
$panelData = $getRelevantPanels->fetchAll(PDO::FETCH_ASSOC);
$panels = array_combine(array_column($panelData, 'id'), $panelData);

// TODO: see, really bad!
$panels = array_filter($panels, function($panel) use ($myAvailability) {
    $day = date('l', strtotime($panel['day']));
    $prefix = '';
    switch ($day) {
    case 'Thursday':
        $prefix = 'thu';
        break;
    case 'Friday':
        $prefix = 'fri';
        break;
    case 'Saturday':
        $prefix = 'sat';
        break;
    default:
        trigger_error('Unknown day ' . $day, E_USER_ERROR);
        return false;
    }

    $hour = ltrim(explode(':', $panel['time'])[0], '0') + 0;
    $suffix = '';
    if ($hour < 12) // 9, 10, 11
        $suffix = 'morn';
    else if ($hour < 16) // 12, 13, 14, 15
        $suffix = 'day';
    else // 16, 17, 18, 19, 20
        $suffix = 'even';

    return $myAvailability[$prefix . '_' . $suffix];
});

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['panels']) && is_array($_POST['panels'])) {
    $saveValues = array();
    foreach ($_POST['panels'] as $id => $data) {
        // Just ignore unknown values/corrupt data
        if (!is_array($data) || !array_key_exists('role', $data) || !array_key_exists('experience', $data))
            continue; // data is corrupted
        if (empty($panels[$id]))
            continue; // panel was removed or something fishy
        if (strlen($data['qualifications']) > 200)
            continue; // TODO: error message; most clients _should_ handle the maxlength though, so less urgent

        if (empty($data['interested'])) {
            $data['role'] = '';
            $data['experience'] = '';
            $data['qualifications'] = '';
        }

        // we use weak compare to match '' and NULL
        $needToSave = $data['role'] != $panels[$id]['panel_roles_id'] ||
            $data['experience'] != $panels[$id]['panel_experience_id'] ||
            $data['qualifications'] != $panels[$id]['qualifications'];
        // TODO: delete if NULL and NULL - equivalent. Later
        if ($needToSave) {
            array_push(
                $saveValues, $panelist['id'], $id,
                array_key_exists($data['role'], $roles) ? $data['role'] : NULL,
                array_key_exists($data['experience'], $experiences) ? $data['experience'] : NULL,
                $data['qualifications']
            );
        }
    }

    if (count($saveValues)) {
        $saveQuery = 'INSERT INTO panelists_panels (panelist_id, panel_id, panel_roles_id, panel_experience_id, qualifications)';
        $saveQuery .= ' VALUES ' . implode(', ', array_fill(0, count($saveValues) / 5, '(?, ?, ?, ?, ?)'));
        $saveQuery .= ' ON DUPLICATE KEY UPDATE panel_roles_id = VALUES(panel_roles_id), panel_experience_id = VALUES(panel_experience_id), qualifications = VALUES(qualifications)';

        $save = $db->prepare($saveQuery);
        $save->execute($saveValues);

        // TODO: can I meaningfully checked changed rows, with the on duplicate thing doubling?
        // Redirect to self to refresh page
        header('Location: /panels');
        exit;
    }
}
?>
<?php
function prettyTime($time) {
    $hour = ltrim(explode(':', $time)[0], '0');
    return $hour . ':00 (' .
        ($hour > 12 ? $hour - 12 : $hour) . ':00 ' .
        (($hour < 12) ? 'am' : 'pm') .
        ')';
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

<p>On this page you will see lists of panels with their days, times, and descriptions matching those times and subjects for which you expressed interest. If you do not see anything, then there are not any panels scheduled during those chosen times and on those chosen subjects.</p>
<p>If you are <em>not interested</em> in a panel, please leave it blank.</p>
<!-- TODO: language is extremely rude here! -->
<p><strong>If you are interested in a panel</strong>, please tell us your experience and the number of years of experience in that role. If you do not fill out both questions for a panel, then we will assume that you are not interested.</p>
<p>Expressing interest in a panel is not a guarantee of being chosen.</p>
<p><strong>You must click Update Panel Selection</strong> at the bottom of the page in order to save this form.</p>
<form method="POST">
<?php if (!empty($error)): ?>
    <output class="error"><?= $error ?></output>
<?php endif; ?>
<?php foreach ($panels as $panel): ?>
    <section class="panel">
        <h2><?= htmlspecialchars($panel['title'], ENT_QUOTES) ?><?= $panel['minutes'] == 105 ? ' (2 hours)': ''?></h2>
        <h3><?= prettyTime($panel['time']) ?> - <?= date('l, M j', strtotime($panel['day'])) ?></h3>
        <ul class="tags">
        <?php foreach(explode(',', $panel['topic_ids']) as $id): ?>
            <li><?= htmlspecialchars($topics[$id], ENT_QUOTES); ?></li>
        <?php endforeach; ?>
        </ul>
        <p><?= htmlspecialchars($panel['description'], ENT_QUOTES) ?></p>

        <input type="checkbox" id="panel-<?= $panel['id'] ?>-interested" name="panels[<?= $panel['id'] ?>][interested]"<?= $panel['panel_roles_id'] || $panel['panel_experience_id'] ? ' checked' : '' ?>>
        <label for="panel-<?= $panel['id'] ?>-interested">I am interested in this panel</label>

        <label for="panel-<?= $panel['id'] ?>-role">What is your main role with this subject?</label>
        <select id="panel-<?= $panel['id'] ?>-role" name="panels[<?= $panel['id'] ?>][role]">
            <option value=''<?= empty($panel['panel_roles_id']) ? ' selected' : '' ?>></option>
        <?php foreach ($roles as $id => $name): ?>
            <option value="<?= $id ?>"<?= $id == $panel['panel_roles_id'] ? ' selected' : '' ?>><?= htmlspecialchars($name, ENT_QUOTES) ?></option>
        <?php endforeach; ?>
        </select>

        <label for="panel-<?= $panel['id'] ?>-experience">How knowledgeable are you on the topic of this panel?</label>
        <select id="panel-<?= $panel['id'] ?>-experience" name="panels[<?= $panel['id'] ?>][experience]">
            <option value=''<?= empty($panel['panel_experience_id']) ? ' selected' : '' ?>></option>
        <?php foreach ($experiences as $id => $name): ?>
            <option value="<?= $id ?>"<?= $id == $panel['panel_experience_id'] ? ' selected' : '' ?>><?= htmlspecialchars($name, ENT_QUOTES) ?></option>
        <?php endforeach; ?>
        </select>

        <label for="panel-<?= $panel['id'] ?>-qualifications">What context/content/experience would you bring to this panel?</label>
        <textarea id="panel-<?= $panel['id'] ?>-qualifications" name="panels[<?= $panel['id'] ?>][qualifications]" maxlength=200><?= $_POST['panels'][$panel['id']]['qualifications'] ?? $panel['qualifications'] ?? '' ?></textarea>
        <p class="explanation">This is optional, and limited to 200 characters</p>

        <button type="submit">Save Panel</button>
    </section>
<?php endforeach; ?>
    <input type="submit" value="Update Panel Selection">
<?php
    $count = array_reduce($panels, function($carry, $panel) {
        if ($panel['panel_roles_id'] !== null && $panel['panel_experience_id'] !== null)
            $carry++;
        return $carry;
    }, 0);
?>
    <p>You will be considered for <?= $count ?> panel<?= $count <> 1 ? 's' : '' ?>.</p>
</form>
