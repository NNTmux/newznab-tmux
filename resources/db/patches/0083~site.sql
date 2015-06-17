INSERT IGNORE INTO `site` (`setting`, `value`) VALUES
 ('lookup_reqids',	'1'),
 ('reqidthreads', '1'),
('request_hours', '1'),
('request_url',	'http://reqid.nzedb.com/index.php');
UPDATE `site` SET `value` = '83' WHERE `setting` = 'sqlpatch';