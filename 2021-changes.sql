DROP TABLE IF EXISTS panelist_suggestions;

ALTER TABLE panelists CHANGE intersectionalities info TEXT;

ALTER TABLE panelists CHANGE website website VARCHAR(191) AFTER info;
ALTER TABLE panelists ADD other_social VARCHAR(191) AFTER website;
ALTER TABLE panelists ADD instagram VARCHAR(191) AFTER website;
ALTER TABLE panelists ADD twitter VARCHAR(191) AFTER website;
ALTER TABLE panelists ADD facebook VARCHAR(191) AFTER website;

/* Inclusion stuff */
ALTER TABLE panelists ADD disability BOOLEAN AFTER other_social; /* defaults to null, nullable */

CREATE TABLE IF NOT EXISTS ethnicities (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    label VARCHAR(191) UNIQUE NOT NULL
);
INSERT IGNORE INTO ethnicities (label) VALUES
('White'),
('Black'),
('Asian'),
('Hispanic'),
('Indigenous / Native American'),
('Other ethnicity'),
('Prefer not to say');
CREATE TABLE IF NOT EXISTS panelists_ethnicities (
    panelist_id INT UNSIGNED NOT NULL,
    ethnicity_id INT UNSIGNED NOT NULL,

    CONSTRAINT FOREIGN KEY (panelist_id) REFERENCES panelists(id) ON DELETE CASCADE,
    CONSTRAINT FOREIGN KEY (ethnicity_id) REFERENCES ethnicities(id) ON DELETE CASCADE,
    PRIMARY KEY (panelist_id, ethnicity_id)
);

CREATE TABLE IF NOT EXISTS genders (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    label VARCHAR(191) UNIQUE NOT NULL
);
INSERT IGNORE INTO genders (label) VALUES
('Male'),
('Female'),
('Nonbinary'),
('Other'),
('Prefer not to say');
CREATE TABLE IF NOT EXISTS panelists_genders (
    panelist_id INT UNSIGNED NOT NULL,
    gender_id INT UNSIGNED NOT NULL,

    CONSTRAINT FOREIGN KEY (panelist_id) REFERENCES panelists(id) ON DELETE CASCADE,
    CONSTRAINT FOREIGN KEY (gender_id) REFERENCES genders(id) ON DELETE CASCADE,
    PRIMARY KEY (panelist_id, gender_id)
);

CREATE TABLE IF NOT EXISTS sexualities (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    label VARCHAR(191) UNIQUE NOT NULL
);
INSERT IGNORE INTO sexualities (label) VALUES
('Straight / Heterosexual'),
('Lesbian'),
('Gay'),
('Bisexual'),
('Transgender'),
('Queer'),
('Intersex'),
('Asexual'),
('Other'),
('Prefer not to say');
CREATE TABLE IF NOT EXISTS panelists_sexualities (
    panelist_id INT UNSIGNED NOT NULL,
    sexuality_id INT UNSIGNED NOT NULL,

    CONSTRAINT FOREIGN KEY (panelist_id) REFERENCES panelists(id) ON DELETE CASCADE,
    CONSTRAINT FOREIGN KEY (sexuality_id) REFERENCES sexualities(id) ON DELETE CASCADE,
    PRIMARY KEY (panelist_id, sexuality_id)
);

CREATE TABLE IF NOT EXISTS religions (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(191) UNIQUE NOT NULL
);
INSERT IGNORE INTO religions (name) VALUES
('Agnostic'),
('Atheist'),
('Buddhist'),
('Catholic'),
('Church of Jesus Christ of Latter-day Saints'),
('General Christian'),
('Hindu'),
('Islamic'),
('Jewish'),
('Other'),
('Prefer not to say');
CREATE TABLE IF NOT EXISTS panelists_religions (
    panelist_id INT UNSIGNED NOT NULL,
    religion_id INT UNSIGNED NOT NULL,

    CONSTRAINT FOREIGN KEY (panelist_id) REFERENCES panelists(id) ON DELETE CASCADE,
    CONSTRAINT FOREIGN KEY (religion_id) REFERENCES religions(id) ON DELETE CASCADE,
    PRIMARY KEY (panelist_id, religion_id)
);
/* End of Inclusion stuff */

ALTER TABLE panelists ADD registered DATETIME NOT NULL AFTER account_id;

/* 2021 we don't really have rooms if we go online */
ALTER TABLE panels CHANGE room_id room_id INT UNSIGNED;

DELETE FROM tracks WHERE name = 'CYOW';
INSERT IGNORE INTO tracks (name) VALUES ('Editing'), ('Worldbuilding');

UPDATE topics SET name = "Wellness/Psychology" WHERE name = "Wellness Psychology";
