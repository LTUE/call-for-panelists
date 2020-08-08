DROP TABLE IF EXISTS panelist_suggestions;

ALTER TABLE panelists CHANGE intersectionalities info TEXT;

ALTER TABLE panelists CHANGE website website VARCHAR(191) AFTER info;
ALTER TABLE panelists ADD other_social VARCHAR(191) AFTER website;
ALTER TABLE panelists ADD instagram VARCHAR(191) AFTER website;
ALTER TABLE panelists ADD twitter VARCHAR(191) AFTER website;
ALTER TABLE panelists ADD facebook VARCHAR(191) AFTER website;

/* Inclusion stuff */
ALTER TABLE panelists ADD disability BOOLEAN AFTER other_social; /* defaults to null, nullable */
ALTER TABLE panelists ADD person_of_color BOOLEAN AFTER other_social; /* defaults to null, nullable */
ALTER TABLE panelists ADD gender_type TINYINT AFTER person_of_color; /* defaults to null, nullable */
ALTER TABLE panelists ADD lgbtqia_plus BOOLEAN AFTER gender_type; /* defaults to null, nullable */
/* End of Inclusion stuff */

ALTER TABLE panelists ADD registered DATETIME NOT NULL AFTER account_id;

/* 2021 we don't really have rooms if we go online */
ALTER TABLE panels CHANGE room_id room_id INT UNSIGNED;

/* Should have renamed CYOW instead of deleting/re-adding */
DELETE FROM tracks WHERE name = 'CYOW';
INSERT IGNORE INTO tracks (name) VALUES ('Editing'), ('Worldbuilding');

UPDATE topics SET name = "Wellness/Psychology" WHERE name = "Wellness Psychology";


ALTER TABLE panelists ADD updated DATETIME NOT NULL AFTER registered;

ALTER TABLE panelists ADD ltuestudio BOOLEAN;
ALTER TABLE panelists ADD equipment VARCHAR(191) NOT NULL;
