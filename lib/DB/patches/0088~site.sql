INSERT IGNORE INTO `site` (`setting`, `value`) VALUE
  ('nntpproxyr','0');
UPDATE `tmux` SET `value` = '88' WHERE `setting` = 'sqlpatch';