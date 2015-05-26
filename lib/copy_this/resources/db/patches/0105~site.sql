INSERT IGNORE INTO `site` (`setting`, `value`) VALUES
  ('magic_file_path', '');
UPDATE `tmux` set `value` = '105' where `setting` = 'sqlpatch';