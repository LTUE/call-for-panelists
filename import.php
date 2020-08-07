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

// Case insensitive comparison...
$topics = array_map(function ($label) { return strtolower($label); }, $topics);

$stdin = fopen('php://stdin', 'r');
/*
2020
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
2021
array(8) {
  [0]=> string(4) "5500"
  [1]=> string(3) "TMA"
  [2]=> string(12) "Presentation"
  [3]=> string(64) "Walden Media (On a Film and a Prayer): Lessons from the Founders"
  [4]=> string(255) "Michael Flaherty says Hollywood is no longer the center of the entertainment industry. The exclusive production and marketing of great films in Hollywood has ended. Some of them will be produced and marketed from Provo! Come learn how you can participate."
  [5]=> string(29) "Film/Theater, Media, Business"
  [6]=> string(2) "TH"
  [7]=> string(3) "3PM"
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

    switch ($line[6]) {
    case 'TH':
        $day = '2021-02-11';
        break;
    case 'FR':
        $day = '2021-02-12';
        break;
    case 'SA':
        $day = '2021-02-13';
        break;
    default:
        trigger_error('Invalid day for panel: ' . $line[6] . ', skipping line ' . $line_no, E_USER_WARNING);
        continue 2;
    }

    $time = substr($line[7], 0, -2);
    $meridian = substr($line[7], -2);
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
        trigger_error('Invalid time string for panel: ' + $line[7] + ', skipping line ' + $line_no, E_USER_WARNING);
        continue 2;
    }
    $time .= ':00';

    $track_id = array_search($line[1], $tracks);
    if ($track_id === false) {
        trigger_error('Invalid track: ' . $line[1] . ', skipping line ' . $line_no, E_USER_WARNING);
        continue;
    }

    $type_id = array_search($line[2], $types);
    if ($type_id === false) {
        trigger_error('Invalid type: ' . $line[2] . ', skipping line ' . $line_no, E_USER_WARNING);
        continue;
    }

    $room_id = null;
    /*
    $room_id = array_search($line[7], $rooms);
    if ($room_id  === false) {
        trigger_error('Invalid room: ' . $line[7] . ', skipping line ' . $line_no, E_USER_WARNING);
        continue;
    }
    */

    $panel_topics = explode(',', strtolower($line[5]));
    $topic_ids = [];
    foreach ($panel_topics as $topic) {
        $topic = trim($topic);
        if ($topic === '')
            continue;

        // GRRR.... NO MORE XLS INPUT! I AM FURIOUS!! THIS IS A RECIPE FOR A _NIGHTMARE_!
        switch ($topic) {
        case 'prodev':
        case 'pro-dev':
            $topic = 'professional development';
            break;
        case 'wellness':
        case 'wellness psychology':
            $topic = 'wellness/psychology';
            break;
        case 'technology':
            $topic = 'technology/physics';
            break;
        case 'history':
            $topic = 'geography/history';
            break;
        }

        $topic_id = array_search($topic, $topics);
        if ($topic_id === false) {
            trigger_error('Invalid topic/tag: ' . $topic . ', skipping line ' . $line_no, E_USER_WARNING);
            continue;
        }
        array_push($topic_ids, $line[0], $topic_id);
    }
    if (count($panel_topics) !== (count($topic_ids) / 2))
        continue;

    // slightly slower to not batch - but avoids max packet size issues, and we
    // have few enough records that the slowdown should be fine
    $data = [$line[0], $day, $time, trim($line[3]), trim($line[4]), $track_id, $type_id, $room_id];
    $insert = $db->prepare($insertPrefix . '(' . implode(', ', array_fill(0, count($data), '?')) . ') ON DUPLICATE KEY UPDATE minutes = minutes + 60');
    $insert->execute($data);
    if ($insert->rowCount() !== 1) {
        trigger_error('Something went wrong inserting ' . $line_no, E_USER_WARNING);
        continue;
    }

    $insertPanelTopics = $db->prepare(
        'INSERT INTO panels_topics (panel_id, topic_id) VALUES ' . implode(', ', array_fill(
            0, count($topic_ids) / 2, '(?, ?)'
        ))
    );
    $insertPanelTopics->execute($topic_ids);
}
