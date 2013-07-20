ALTER TABLE  `prehash` ADD  `source` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT  '' AFTER  `predate`
ALTER TABLE  `prehash` ADD  `nfo` VARCHAR( 500 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER  `releasename`
ALTER TABLE  `prehash` ADD  `size` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER  `nfo`
ALTER TABLE  `prehash` ADD  `category` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER  `size`
ALTER TABLE  `prehash` CHANGE  `hash`  `hash` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT  '0'
ALTER TABLE  `prehash` ADD  `releaseID` INT( 11 ) NULL DEFAULT NULL AFTER  `source`
ALTER TABLE  `prehash` ADD  `adddate` DATETIME NULL DEFAULT NULL AFTER  `predate`