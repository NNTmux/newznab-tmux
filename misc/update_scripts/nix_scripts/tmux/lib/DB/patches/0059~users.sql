ALTER TABLE users ADD COLUMN xxxview INT AFTER gameview;
UPDATE `site` set `value` = '59' where `setting` = 'sqlpatch';