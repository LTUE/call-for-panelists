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
    type VARCHAR(20) NOT NULL REFERENCES account_types(type)
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
    reading BOOLEAN NOT NULL,
    moderator BOOLEAN NOT NULL,
    recording BOOLEAN NOT NULL,
    share_email BOOLEAN NOT NULL
    /* TODO: presentations/workshops */
    /* TODO: times available; what exactly are they? */
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
    panelist_id INT UNSIGNED NOT NULL REFERENCES panelists(id),
    topic_id INT UNSIGNED NOT NULL REFERENCES topics(id),
    PRIMARY KEY (panelist_id, topic_id)
);

/* TODO: add track, then allow showing by track for committee members */
CREATE TABLE IF NOT EXISTS panels (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    /* TODO: has a room slot - which itself should have the time+day instead. Doing this for speed today */
    day TINYINT NOT NULL, /* 1 = Thursday -> 3 = Saturday, for now */
    hour TINYINT NOT NULL, /* 24 hours - no meridian */
    title VARCHAR(191) UNIQUE NOT NULL, /* TODO: unique key across this + year */
    description TEXT NOT NULL,
    seed_questions TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS panels_topics (
    panel_id INT UNSIGNED NOT NULL REFERENCES panels(id),
    topic_id INT UNSIGNED NOT NULL REFERENCES topics(id),
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
INSERT IGNORE INTO panel_experience (name) VALUES ('1-5'), ('6-10'), ('10-20'), ('20+');

/* TODO - assigned flag, description for later performance; unless we want to split assigned from interest; maybe */
CREATE TABLE IF NOT EXISTS panelists_panels (
    panelist_id INT UNSIGNED NOT NULL REFERENCES panelists(id),
    panel_id INT UNSIGNED NOT NULL REFERENCES panels(id),
    panel_roles_id INT UNSIGNED REFERENCES panel_roles(id),
    panel_experience_id INT UNSIGNED REFERENCES panel_experience(id),
    PRIMARY KEY (panelist_id, panel_id)
);

SET sql_notes = 1;
