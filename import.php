<?php

// only run from shell
defined('STDIN') or die();

define('INCLUDED', true);
require('setup.php');

$tracks = array_column($db->query(
    'SELECT id, name FROM tracks'
)->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');
$types = array_column($db->query(
    'SELECT id, name FROM panel_types'
)->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');
$rooms = array_column($db->query(
    'SELECT id, name FROM rooms'
)->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');
$topics = array_column($db->query(
    'SELECT id, name FROM topics'
)->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');

$stdin = fopen('php://stdin', 'r');
/*
array(10) {
  [0]=> string(4) "5000"
  [1]=> string(8) "Thursday"
  [2]=> string(3) "9AM"
  [3]=> string(3) "Art"
  [4]=> string(12) "Presentation"
  [5]=> string(19) "Beginning Photoshop"
  [6]=> string(109) "Learn the essential tools of Adobe Photoshop to jump right in and design beautiful graphics for illustration."
  [7]=> string(12) "Amphitheater"
  [8]=> string(6) "AV 117"
  [9]=> string(3) "Art"
}
CREATE TABLE IF NOT EXISTS panels (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    day DATE NOT NULL,
    time TIME NOT NULL,
    minutes SMALLINT UNSIGNED NOT NULL DEFAULT 45,
    title VARCHAR(191) UNIQUE NOT NULL,
    description TEXT NOT NULL,
    seed_questions TEXT NOT NULL,
    track_id INT UNSIGNED NOT NULL REFERENCES tracks(id),
    type_id INT UNSIGNED NOT NULL REFERENCES panel_types(id),
    room_id INT UNSIGNED NOT NULL REFERENCES rooms(id)
);
*/

// Wipe on each import for now
$db->prepare('DELETE FROM panels')->execute();

$line_no = 0;
$insertPrefix = 'INSERT INTO panels (id, day, time, title, description, track_id, type_id, room_id) VALUES ';
while ($line = fgetcsv($stdin, 0, "\t")) {
    $line_no++;

    switch ($line[1]) {
    case 'Thursday':
        $day = '2020-02-13';
        break;
    case 'Friday':
        $day = '2020-02-14';
        break;
    case 'Saturday':
        $day = '2020-02-15';
        break;
    default:
        trigger_error('Invalid day for panel: ' . $line[1] . ', skipping line ' . $line_no, E_USER_WARNING);
        continue 2;
    }

    $time = substr($line[2], 0, -2);
    $meridian = substr($line[2], -2);
    switch($meridian) {
    case 'AM':
        if ($time == 12)
            $time = 0;
        break;
    case 'PM':
        if ($time != 12)
            $time += 12;
        break;
    default:
        trigger_error('Invalid time string for panel: ' + $line[2] + ', skipping line ' + $line_no, E_USER_WARNING);
        continue 2;
    }
    $time .= ':00';

    $track_id = array_search($line[3], $tracks);
    if ($track_id === false) {
        trigger_error('Invalid track: ' . $line[3] . ', skipping line ' . $line_no, E_USER_WARNING);
        continue;
    }

    $type_id = array_search($line[4], $types);
    if ($type_id === false) {
        trigger_error('Invalid type: ' . $line[4] . ', skipping line ' . $line_no, E_USER_WARNING);
        continue;
    }

    $room_id = array_search($line[7], $rooms);
    if ($room_id  === false) {
        trigger_error('Invalid room: ' . $line[7] . ', skipping line ' . $line_no, E_USER_WARNING);
        continue;
    }

    $panel_topics = explode(', ', $line[9]);
    $topic_ids = [];
    foreach ($panel_topics as $topic) {
        $topic_id = array_search($topic, $topics);
        if ($topic_id === false) {
            trigger_error('Invalid topic: ' . $topic . ', skipping line ' . $line_no, E_USER_WARNING);
            continue;
        }
        array_push($topic_ids, $line[0], $topic_id);
    }
    if (count($panel_topics) !== (count($topic_ids) / 2))
        continue;

    // slightly slower to not batch - but avoids max packet size issues, and we
    // have few enough records that the slowdown should be fine
    $data = [$line[0], $day, $time, $line[5], $line[6], $track_id, $type_id, $room_id];
    $insert = $db->prepare($insertPrefix . '(' . implode(', ', array_fill(0, count($data), '?')) . ')');
    $insert->execute($data);
    if ($insert->rowCount() !== 1) {
        trigger_error('Something went wrong inserting ' . $line_no, E_USER_WARNING);
        continue;
    }

    $data = [$line[0], $day, $time, trim($line[5]), trim($line[6]), $track_id, $type_id, $room_id];
    $insertPanelTopics = $db->prepare(
        'INSERT INTO panels_topics (panel_id, topic_id) VALUES ' . implode(', ', array_fill(
            0, count($topic_ids) / 2, '(?, ?)'
        ))
    );
    $insertPanelTopics->execute($topic_ids);
}
