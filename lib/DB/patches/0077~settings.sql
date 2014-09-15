INSERT IGNORE INTO settings (name , value, setting, hint) VALUES ('latestregexurl', 'http://www.newznab.com/getregex.php', 'latestregexurl', 'URL for the latest regexes.');
INSERT IGNORE INTO settings (name , value, setting, hint) VALUES ('latestregexrevision', '0', 'latestregexrevision', 'latest revision of newznabregexes');
INSERT IGNORE INTO settings (name , value, setting, hint) VALUES ('newznabID', '', 'newznabID', 'Your newznab ID');
INSERT IGNORE INTO settings (name , value, setting, hint) VALUES ('reqidurl', 'http://allfilled.newznab.com/query.php?t=[GROUP]&reqid=[REQID]', 'reqidurl', 'URL for the latest requestids.');
INSERT IGNORE INTO settings (name , value, setting, hint) VALUES ('completionpercent', '0', 'completionpercent', 'Completion percent of releases');
INSERT IGNORE INTO settings (name , value, setting, hint) VALUES ('partsdeletechunks', '0', 'partsdeletechunks', 'How many parts to delete in one go');
INSERT IGNORE INTO settings (name , value, setting, hint) VALUES ('rawretentiondays', '1.5', 'rawretentiondays', 'Sites retention');
INSERT IGNORE INTO settings (name , value, setting, hint) VALUES ('userdownloadpurgedays', '0', 'userdownloadpurgedays', 'How many days to keep users downloads in database');
INSERT IGNORE INTO settings (name , value, setting, hint) VALUES ('dbversion', '$Rev: 3174 $', 'dbversion', 'Version of the newznab database');

UPDATE `tmux` SET `value` = '77' WHERE `setting` = 'sqlpatch';