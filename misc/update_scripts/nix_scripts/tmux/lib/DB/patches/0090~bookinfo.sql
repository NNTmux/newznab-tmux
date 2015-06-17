ALTER TABLE `bookinfo` ADD  `salesrank` INT(10) UNSIGNED DEFAULT NULL;
UPDATE `site` SET `value` = '90' WHERE `setting` = 'sqlpatch';