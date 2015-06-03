ALTER TABLE `prehash` ADD INDEX `ix_prehash_size` (`size`);
ALTER TABLE `prehash` ADD INDEX `ix_prehash_category` (`category`);
UPDATE `site` set `value` = '11' where `setting` = 'sqlpatch';