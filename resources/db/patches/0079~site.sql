INSERT IGNORE INTO `site` (`setting`, `value`) VALUE ('maxnforetries', '5');
UPDATE `site` SET `value` = '79' WHERE `setting` = 'sqlpatch';