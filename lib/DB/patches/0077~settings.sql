INSERT IGNORE INTO settings (name , value, setting, hint) VALUES ('latestregexurl', 'http://www.newznab.com/getregex.php', 'latestregexurl', 'URL for the latest regexes.');

UPDATE `tmux` SET `value` = '77' WHERE `setting` = 'sqlpatch';