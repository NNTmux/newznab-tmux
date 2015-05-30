INSERT IGNORE INTO `site` (`setting`, `value`) VALUE  ('safebackfilldate', '2012-06-24');
UPDATE `site` SET `value` = '85' WHERE `setting` = 'sqlpatch';