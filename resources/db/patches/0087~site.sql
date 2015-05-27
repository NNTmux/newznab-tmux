INSERT IGNORE INTO `site` (`setting`, `value`) VALUES
  ('safepartrepair','0'),
  ('maxpartrepair', '15000'),
  ('partrepairmaxtries', '3'),
  ('tablepergroup', '0');
UPDATE `tmux` SET `value` = '87' WHERE `setting` = 'sqlpatch';
