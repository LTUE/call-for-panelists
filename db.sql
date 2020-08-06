/* change db name as needed */
ALTER DATABASE ucevent1_LTUE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET default_storage_engine=INNODB;
SET sql_notes = 0; /* Turn off "table exists" warnings during script */

CREATE TABLE IF NOT EXISTS account_types (
    type VARCHAR(20) NOT NULL PRIMARY KEY
);
INSERT IGNORE INTO account_types (type) VALUES ('committee'), ('panelist');

/* TODO - type could be a pair of flags instead, to search on these other tables if set… */
CREATE TABLE IF NOT EXISTS accounts (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(191) UNIQUE NOT NULL,
    password TINYTEXT CHARACTER SET latin1 NOT NULL,
    type VARCHAR(20) NOT NULL,
    reset_counter SMALLINT NOT NULL DEFAULT 0,

    CONSTRAINT FOREIGN KEY (type) REFERENCES account_types(type)
);

/* Has own surrogate key to link tables; to ensure other account types aren't linked */
CREATE TABLE IF NOT EXISTS panelists (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    account_id INT UNSIGNED UNIQUE REFERENCES accounts(id),
    name VARCHAR(191) NOT NULL,
    badge_name VARCHAR(191) NOT NULL,
    contact_email VARCHAR(191) NOT NULL,
    website VARCHAR(191),
    biography TEXT,
    intersectionalities TEXT,
    photo_file TINYTEXT CHARACTER SET latin1,
    /* TODO: books? */
    signing BOOLEAN NOT NULL,
    reading TINYTEXT,
    moderator BOOLEAN NOT NULL,
    recording BOOLEAN NOT NULL,
    share_email BOOLEAN NOT NULL,
    /* TODO: presentations/workshops */
    /* TODO: times available; what exactly are they? */

    CONSTRAINT FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS topics (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(191) UNIQUE NOT NULL
);
INSERT IGNORE INTO topics (name) VALUES
('Art'),
('Biology/Medicine'),
('Business'),
('Character Development'),
('Culture'),
('Editing'),
('Fantasy'),
('Film/Theater'),
('Gaming'),
('Geography/History'),
('Horror'),
('Media'),
('Military'),
('Politics/Economics'),
('Prewriting'),
('Professional Development'),
('Romance'),
('Science Fiction'),
('Story Development'),
('Technology/Physics'),
('Wellness Psychology'),
('Writing Technique');

/* Many-to-many join table */
CREATE TABLE IF NOT EXISTS panelists_topics (
    panelist_id INT UNSIGNED NOT NULL,
    topic_id INT UNSIGNED NOT NULL,

    CONSTRAINT FOREIGN KEY (panelist_id) REFERENCES panelists(id) ON DELETE CASCADE,
    CONSTRAINT FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,

    PRIMARY KEY (panelist_id, topic_id)
);

CREATE TABLE IF NOT EXISTS tracks (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(191) UNIQUE NOT NULL
    /* TODO: track heads?, etc */
);
INSERT IGNORE INTO tracks (name) VALUES
('Art'),
('Books'),
('CYOW'),
('Gaming'),
('ProDev'),
('TMA'),
('Writing');

CREATE TABLE IF NOT EXISTS panel_types (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(191) UNIQUE NOT NULL
);
INSERT IGNORE INTO panel_types (name) VALUES
('Panel'),
('Presentation'),
('Workshop');

/* TODO: something for distance calculation, AV probably not tied to room(?) */
/* Pick registration as "0", distances are roughly linear, in travel minutes? */
CREATE TABLE IF NOT EXISTS rooms (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(191) UNIQUE NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL,
    hasAV2020 BOOLEAN NOT NULL DEFAULT FALSE
);
INSERT IGNORE INTO rooms (name, capacity, hasAV2020) VALUES
('Amphitheater', 117, true),
('Arches', 200, false),
('Boardroom', 18, false),
('Bryce', 200, false),
('Canyon', 230, true),
('Cedar', 158, false),
('Elm', 100, false),
('Exhibit Hall C', 828, true),
('Juniper', 121, false),
('Maple', 80, true),
('Oak', 53, true),
('Sycamore', 53, false),
('Zion', 400, true);

/* TODO: add track, then allow showing by track for committee members */
CREATE TABLE IF NOT EXISTS panels (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    /* TODO: has a room slot - which itself should have the time+day instead. Doing this for speed today */
    day DATE NOT NULL,
    time TIME NOT NULL,
    minutes SMALLINT UNSIGNED NOT NULL DEFAULT 45,
    title VARCHAR(191) UNIQUE NOT NULL, /* TODO: unique key across this + year */
    description TEXT NOT NULL,
    seed_questions TEXT,
    track_id INT UNSIGNED NOT NULL,
    type_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NOT NULL,

    CONSTRAINT FOREIGN KEY (track_id) REFERENCES tracks(id),
    CONSTRAINT FOREIGN KEY (type_id) REFERENCES panel_types(id),
    CONSTRAINT FOREIGN KEY (room_id) REFERENCES rooms(id)
);

CREATE TABLE IF NOT EXISTS panels_topics (
    panel_id INT UNSIGNED NOT NULL,
    topic_id INT UNSIGNED NOT NULL,

    CONSTRAINT FOREIGN KEY (panel_id) REFERENCES panels(id) ON DELETE CASCADE,
    CONSTRAINT FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    PRIMARY KEY (panel_id, topic_id)
);

CREATE TABLE IF NOT EXISTS panel_roles (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(191) UNIQUE NOT NULL
);
INSERT IGNORE INTO panel_roles (name) VALUES ('Creator'), ('Critic'), ('Fan'), ('Educator');

CREATE TABLE IF NOT EXISTS panel_experience (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(191) UNIQUE NOT NULL
);
INSERT IGNORE INTO panel_experience (name) VALUES
('A Little Knowledgeable'),
('Moderately Knowledgeable'),
('Very Knowledgeable'),
('Extremely Knowledgeable'),
('One of the top experts in the world');

/* TODO - assigned flag, description for later performance; unless we want to split assigned from interest; maybe */
CREATE TABLE IF NOT EXISTS panelists_panels (
    panelist_id INT UNSIGNED NOT NULL,
    panel_id INT UNSIGNED NOT NULL,
    panel_roles_id INT UNSIGNED,
    panel_experience_id INT UNSIGNED,

    qualifications TINYTEXT,

    CONSTRAINT FOREIGN KEY (panelist_id) REFERENCES panelists(id) ON DELETE CASCADE,
    CONSTRAINT FOREIGN KEY (panel_id) REFERENCES panels(id) ON DELETE CASCADE,
    CONSTRAINT FOREIGN KEY (panel_roles_id) REFERENCES panel_roles(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT FOREIGN KEY (panel_experience_id) REFERENCES panel_experience(id) ON DELETE SET NULL ON UPDATE CASCADE,
    PRIMARY KEY (panelist_id, panel_id)
);

CREATE TABLE IF NOT EXISTS books_to_stock (
    panelist_id INT UNSIGNED NOT NULL,
    position TINYINT UNSIGNED NOT NULL,
    title TINYTEXT,
    author TINYTEXT,
    isbn TINYTEXT,

    CONSTRAINT FOREIGN KEY (panelist_id) REFERENCES panelists(id) ON DELETE CASCADE,
    PRIMARY KEY (panelist_id, position)
);
/*
CREATE TABLE IF NOT EXISTS panelist_suggestions (
    panelist_id INT UNSIGNED NOT NULL,
    position TINYINT UNSIGNED NOT NULL,
    title TINYTEXT,
    description TINYTEXT,
    pitch TEXT,

    CONSTRAINT FOREIGN KEY (panelist_id) REFERENCES panelists(id) ON DELETE CASCADE,
    PRIMARY KEY (panelist_id, position)
);
*/

/* TODO: this is not good - only virtue is that I put in minimal thought */
CREATE TABLE IF NOT EXISTS panelist_availability (
    panelist_id INT UNSIGNED NOT NULL PRIMARY KEY,

    thu_morn BOOL NOT NULL,
    thu_day BOOL NOT NULL,
    thu_even BOOL NOT NULL,

    fri_morn BOOL NOT NULL,
    fri_day BOOL NOT NULL,
    fri_even BOOL NOT NULL,

    sat_morn BOOL NOT NULL,
    sat_day BOOL NOT NULL,
    sat_even BOOL NOT NULL,

    CONSTRAINT FOREIGN KEY (panelist_id) REFERENCES panelists(id) ON DELETE CASCADE
);

SET sql_notes = 1;
