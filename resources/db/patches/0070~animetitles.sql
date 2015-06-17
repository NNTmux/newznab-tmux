ALTER TABLE animetitles CHANGE createddate unixtime INT(12) UNSIGNED NOT NULL;
UPDATE `site` SET `value` = '70' WHERE `setting` = 'sqlpatch';