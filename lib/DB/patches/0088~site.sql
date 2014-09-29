INSERT IGNORE INTO `site` (`setting`, `value`) VALUE
  ('nntpproxy','0');
UPDATE `tmux` SET `value` = '88' WHERE `setting` = 'sqlpatch';