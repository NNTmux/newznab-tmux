INSERT IGNORE INTO `tmux` (`setting`, `value`) VALUES
  ('optimize', '0'),
  ('optimize_timer', '86400');
UPDATE `site` SET `value` = '101' where setting = 'sqlpatch';