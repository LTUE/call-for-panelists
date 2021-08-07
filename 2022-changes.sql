ALTER TABLE panelists ADD judge  BOOLEAN AFTER moderator; /* defaults to null, nullable */

UPDATE topics SET name = "Publishing" WHERE name = "Professional Development";
INSERT INTO topics SET name = "Music/Soundtracks";

UPDATE topics SET name = 'Film' WHERE name = 'Film/Theater'; -- 8
INSERT INTO topics SET name = "Acting/Performance"; -- 24
INSERT INTO panelists_topics (panelist_id, topic_id) (SELECT panelist_id, 24 FROM panelists_topics WHERE topic_id = 8);

UPDATE topics SET name = 'Geography' WHERE name = 'Geography/History'; -- 10
INSERT INTO topics SET name = 'History'; -- 25
INSERT INTO panelists_topics (panelist_id, topic_id) (SELECT panelist_id, 25 FROM panelists_topics WHERE topic_id = 10);

UPDATE topics SET name = 'Politics' WHERE name = 'Politics/Economics'; -- 14
INSERT INTO topics SET name = 'Economics'; -- 26
INSERT INTO panelists_topics (panelist_id, topic_id) (SELECT panelist_id, 26 FROM panelists_topics WHERE topic_id = 14);

UPDATE topics SET name = 'Technology' WHERE name = 'Technology/Physics';
UPDATE topics SET name = 'Wellness' WHERE name = 'Wellness/Psychology';
UPDATE topics SET name = 'Biology' WHERE name = 'Biology/Medicine';
