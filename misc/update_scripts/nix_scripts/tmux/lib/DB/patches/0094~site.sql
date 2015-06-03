INSERT IGNORE INTO `site` (`setting`, `value`) VALUES
  ('intanidbupdate','7'),
  ('lastanidbupdate','0'),
  ('banned','0');
UPDATE `site` SET `value` = '94' WHERE `setting` = 'sqlpatch';