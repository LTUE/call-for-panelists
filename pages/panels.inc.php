<?php defined('INCLUDED') or die(); ?>
<?php $title = 'Panel Selection' ?>
<?php
$getPanelist = $db->prepare('SELECT * FROM panelists WHERE account_id = :id');
$getPanelist->execute(array(':id' => $_SESSION['account']['id']));
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

$queryRelevantPanels = <<<SQL
    SELECT p.*, GROUP_CONCAT(topic_id SEPARATOR ',') AS topic_ids, panel_roles_id, panel_experience_id
    FROM panelists_topics AS pt
    INNER JOIN panels_topics AS t USING (topic_id)
    INNER JOIN panels AS p ON t.panel_id = p.id
    LEFT JOIN panelists_panels AS pp ON pp.panel_id = p.id AND pp.panelist_id = :panelist_id
    WHERE pt.panelist_id = :panelist_id
    GROUP BY p.id
SQL;
$getRelevantPanels = $db->prepare($queryRelevantPanels);
$getRelevantPanels->execute(array(':panelist_id' => $panelist['id']));
$panelData = $getRelevantPanels->fetchAll(PDO::FETCH_ASSOC);
$panels = array_combine(array_column($panelData, 'id'), $panelData);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['panels']) && is_array($_POST['panels'])) {
    $saveValues = array();
    foreach ($_POST['panels'] as $id => $data) {
        // Just ignore unknown values/corrupt data
        if (!is_array($data) || !array_key_exists('role', $data) || !array_key_exists('experience', $data))
            continue; // data is corrupted
        if (empty($panels[$id]))
            continue; // panel was removed or something fishy

        // we use weak compare to match '' and NULL
        $needToSave = $data['role'] != $panels[$id]['panel_roles_id'] ||
            $data['experience'] != $panels[$id]['panel_experience_id'];
        if ($needToSave) {
            array_push(
                $saveValues, $panelist['id'], $id,
                array_key_exists($data['role'], $roles) ? $data['role'] : NULL,
                array_key_exists($data['experience'], $experiences) ? $data['experience'] : NULL,
            );
        }
    }

    if (count($saveValues)) {
        $saveQuery = 'INSERT INTO panelists_panels (panelist_id, panel_id, panel_roles_id, panel_experience_id)';
        $saveQuery .= ' VALUES ' . implode(', ', array_fill(0, count($saveValues) / 4, '(?, ?, ?, ?)'));
        $saveQuery .= ' ON DUPLICATE KEY UPDATE panel_roles_id = VALUES(panel_roles_id), panel_experience_id = VALUES(panel_experience_id)';

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
// TODO: replace with a real date system
function dayOfWeek($day) {
    switch ($day) {
    case 1: return 'Thursday';
    case 2: return 'Friday';
    case 3: return 'Saturday';
    default: return 'Bug';
    }
}
function prettyTime($hour) {
    return $hour . ':00 (' .
        ($hour > 12 ? $hour - 12 : $hour) . ':00 ' .
        (($hour >= 12 && $hour < 24) ? 'am' : 'pm') .
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
<form method="POST">
<?php if (!empty($error)): ?>
    <output class="error"><?= $error ?></output>
<?php endif; ?>
<?php foreach ($panels as $panel): ?>
    <section class="panel">
        <h2><?= dayOfWeek($panel['day']) ?> - <?= prettyTime($panel['hour']) ?> - <?= htmlspecialchars($panel['title'], ENT_QUOTES) ?></h2>
        <dl><dt>Tags</dt><dd><?= implode(', ', array_map(function($id) {
            global $topics;
            return htmlspecialchars($topics[$id], ENT_QUOTES);
        }, explode(',', $panel['topic_ids']))) ?></dd></dt></dl>
        <p><?= htmlspecialchars($panel['description'], ENT_QUOTES) ?></p>

        <label for="panel[<?= $panel['id'] ?>][role]">What is your main role with this subject?</label>
        <select id="panel-<?= $panel['id'] ?>-role" name="panels[<?= $panel['id'] ?>][role]">
            <option value=''<?= empty($panel['panel_roles_id']) ? ' selected' : '' ?>></option>
        <?php foreach ($roles as $id => $name): ?>
            <option value="<?= $id ?>"<?= $id == $panel['panel_roles_id'] ? ' selected' : '' ?>><?= htmlspecialchars($name, ENT_QUOTES) ?></option>
        <?php endforeach; ?>
        </select>

        <label for="panel[<?= $panel['id'] ?>][experience]">How many years of experience do you have in that role?</label>
        <select id="panel-<?= $panel['id'] ?>-experience" name="panels[<?= $panel['id'] ?>][experience]">
            <option value=''<?= empty($panel['panel_experience_id']) ? ' selected' : '' ?>></option>
        <?php foreach ($experiences as $id => $name): ?>
            <option value="<?= $id ?>"<?= $id == $panel['panel_experience_id'] ? ' selected' : '' ?>><?= htmlspecialchars($name, ENT_QUOTES) ?></option>
        <?php endforeach; ?>
        </select>
    </section>
<?php endforeach; ?>
    <input type="submit" value="Update Panel Selection">
</form>
