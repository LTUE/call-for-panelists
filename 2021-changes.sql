DROP TABLE IF EXISTS panelist_suggestions;

ALTER TABLE panelists CHANGE intersectionalities info TEXT;

ALTER TABLE panelists CHANGE website website VARCHAR(191) AFTER info;
ALTER TABLE panelists ADD other_social VARCHAR(191) AFTER website;
ALTER TABLE panelists ADD instagram VARCHAR(191) AFTER website;
ALTER TABLE panelists ADD twitter VARCHAR(191) AFTER website;
ALTER TABLE panelists ADD facebook VARCHAR(191) AFTER website;
