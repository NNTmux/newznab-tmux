ALTER TABLE `predb` drop `MD5`;
UPDATE `site` SET `value` = '102' where setting = 'sqlpatch';