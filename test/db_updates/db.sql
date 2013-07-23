ALTER TABLE  `predb` ADD  `hash` VARCHAR( 32 ) NULL;
ALTER TABLE  `predb` ADD INDEX (  `hash` ( 32 ) );
ALTER TABLE  `releases` ADD  `dehashstatus` TINYINT( 1 ) NOT NULL DEFAULT  '0' AFTER  `haspreview`;
ALTER TABLE  `releases` ADD  `nfostatus` TINYINT NOT NULL DEFAULT 0 after `dehashstatus`;
ALTER TABLE  `releases` ADD  `relnamestatus` TINYINT NOT NULL DEFAULT 1 after `nfostatus`;
ALTER TABLE  `releases` ADD  `relstatus` TINYINT(4) NOT NULL DEFAULT 0 after `relnamestatus`;




