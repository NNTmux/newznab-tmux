DROP TABLE IF EXISTS category;
CREATE TABLE category
(
  id                   INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  title                VARCHAR(255)    NOT NULL,
  parentid             INT             NULL,
  status               INT             NOT NULL DEFAULT '1',
  minsizetoformrelease BIGINT UNSIGNED NOT NULL DEFAULT '0',
  maxsizetoformrelease BIGINT UNSIGNED NOT NULL DEFAULT '0',
  description          VARCHAR(255)    NULL,
  disablepreview       TINYINT(1)      NOT NULL DEFAULT '0'
)
  ENGINE =INNODB
  DEFAULT CHARACTER SET utf8
  COLLATE utf8_unicode_ci
  AUTO_INCREMENT =100000;

INSERT IGNORE INTO category (id, title) VALUES (1000, 'Console');
INSERT IGNORE INTO category (id, title) VALUES (2000, 'Movies');
INSERT IGNORE INTO category (id, title) VALUES (3000, 'Audio');
INSERT IGNORE INTO category (id, title) VALUES (4000, 'PC');
INSERT IGNORE INTO category (id, title) VALUES (5000, 'TV');
INSERT IGNORE INTO category (id, title) VALUES (6000, 'XXX');
INSERT IGNORE INTO category (id, title) VALUES (7000, 'Books');
INSERT IGNORE INTO category (id, title) VALUES (8000, 'Other');

INSERT IGNORE INTO category (id, title, parentid) VALUES (1010, 'NDS', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1020, 'PSP', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1030, 'Wii', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1040, 'Xbox', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1050, 'Xbox 360', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1060, 'WiiWare/VC', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1070, 'XBOX 360 DLC', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1080, 'PS3', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1999, 'Other', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1110, '3DS', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1120, 'PS Vita', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1130, 'WiiU', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1140, 'Xbox One', 1000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (1180, 'PS4', 1000);

INSERT IGNORE INTO category (id, title, parentid) VALUES (2010, 'Foreign', 2000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (2999, 'Other', 2000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (2030, 'SD', 2000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (2040, 'HD', 2000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (2050, '3D', 2000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (2060, 'BluRay', 2000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (2070, 'DVD', 2000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (2080, 'WEB-DL', 2000);

INSERT IGNORE INTO category (id, title, parentid) VALUES (3010, 'MP3', 3000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (3020, 'Video', 3000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (3030, 'Audiobook', 3000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (3040, 'Lossless', 3000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (3999, 'Other', 3000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (3060, 'Foreign', 3000);

INSERT IGNORE INTO category (id, title, parentid) VALUES (4010, '0day', 4000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (4020, 'ISO', 4000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (4030, 'Mac', 4000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (4040, 'Mobile-Other', 4000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (4050, 'Games', 4000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (4060, 'Mobile-iOS', 4000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (4070, 'Mobile-Android', 4000);

INSERT IGNORE INTO category (id, title, parentid) VALUES (5010, 'WEB-DL', 5000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (5020, 'Foreign', 5000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (5030, 'SD', 5000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (5040, 'HD', 5000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (5999, 'Other', 5000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (5060, 'Sport', 5000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (5070, 'Anime', 5000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (5080, 'Documentary', 5000);

INSERT IGNORE INTO category (id, title, parentid) VALUES (6010, 'DVD', 6000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (6020, 'WMV', 6000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (6030, 'XviD', 6000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (6040, 'x264', 6000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (6050, 'Pack', 6000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (6060, 'ImgSet', 6000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (6999, 'Other', 6000);

INSERT IGNORE INTO category (id, title, parentid) VALUES (7010, 'Mags', 7000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (7020, 'Ebook', 7000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (7030, 'Comics', 7000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (7040, 'Technical', 7000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (7050, 'Other', 7000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (7060, 'Foreign', 7000);

INSERT IGNORE INTO category (id, title, parentid) VALUES (8010, 'Misc', 8000);
INSERT IGNORE INTO category (id, title, parentid) VALUES (8020, 'Hashed', 8000);
