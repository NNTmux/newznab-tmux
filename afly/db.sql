ALTER TABLE  `predb` ADD  `hash` VARCHAR( 32 ) NULL
ALTER TABLE  `predb` ADD INDEX (  `hash` ( 32 ) )
ALTER TABLE  `releases` ADD  `dehashstatus` TINYINT( 1 ) NOT NULL DEFAULT  '0' AFTER  `haspreview`




