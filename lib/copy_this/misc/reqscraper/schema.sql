
DROP TABLE IF EXISTS `feed`;
CREATE TABLE `feed` 
(
	`ID` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`code` VARCHAR(50) NULL,
	`name` VARCHAR(255) NULL,
	`url` VARCHAR(1000) NOT NULL,
	`reqidcol` VARCHAR(255) NULL,
	`reqidregex` VARCHAR(2000) NOT NULL,
	`titlecol` VARCHAR(255) NULL,
	`titleregex` VARCHAR(2000) NOT NULL,
	`lastupdate` DATETIME NULL,
	`updatemins` TINYINT(3) NOT NULL DEFAULT  '55',
	`status` INT NOT NULL DEFAULT 1,
	PRIMARY KEY  (`ID`)
) ENGINE=MYISAM AUTO_INCREMENT=1 ;

CREATE INDEX ix_feed_code ON feed (CODE);

INSERT INTO feed (CODE, NAME, url, titlecol, titleregex, reqidcol, reqidregex, lastupdate) VALUES ('alt.binaries.teevee', 'abteevee', 'http://abteevee.allfilled.com/rss.php', 'title', '/(?P<title>.*)/i', 'description', '/^ReqId: (?P<reqid>\\d{3,6})/i', NULL);
INSERT INTO feed (CODE, NAME, url, titlecol, titleregex, reqidcol, reqidregex, lastupdate) VALUES ('alt.binaries.erotica', 'aberotica', 'http://aberotica.allfilled.com/rss.php', 'title', '/(?P<title>.*)/i', 'description', '/^ReqId: (?P<reqid>\\d{3,6})/i', NULL);
INSERT INTO feed (CODE, NAME, url, titlecol, titleregex, reqidcol, reqidregex, lastupdate) VALUES ('alt.binaries.games.wii', 'abgwii', 'http://www.abgx.net/rss/abgw/posted.rss', 'title', '/^Req\\s\\d{1,6}\\s\\-\\s(?P<title>.\\S*)/i', 'title', '/^Req (?P<reqid>\\d{3,6})/i', NULL);
INSERT INTO feed (CODE, NAME, url, titlecol, titleregex, reqidcol, reqidregex, lastupdate) VALUES ('alt.binaries.games.xbox360', 'abg360', 'http://www.abgx.net/rss/x360/posted.rss', 'title', '/^Req\\s\\d{1,6}\\s\\-\\s(?P<title>.\\S*)/i', 'title', '/^Req (?P<reqid>\\d{3,6})/i', NULL);
INSERT INTO feed (CODE, NAME, url, titlecol, titleregex, reqidcol, reqidregex, lastupdate) VALUES ('alt.binaries.console.ps3', 'ps3', 'http://www.abgx.net/rss/abcp/posted.rss', 'title', '/^Req\\s\\d{1,6}\\s\\-\\s(?P<title>.\\S*)/i', 'title', '/^Req (?P<reqid>\\d{3,6})/i', NULL);
INSERT INTO feed (CODE, NAME, url, titlecol, titleregex, reqidcol, reqidregex, lastupdate) VALUES ('alt.binaries.sony.psp', 'psp', 'http://www.abgx.net/rss/absp/posted.rss', 'title', '/^Req\\s\\d{1,6}\\s\\-\\s(?P<title>.\\S*)/i', 'title', '/^Req (?P<reqid>\\d{3,6})/i', NULL);
INSERT INTO feed (CODE, NAME, url, titlecol, titleregex, reqidcol, reqidregex, lastupdate) VALUES ('alt.binaries.games.nintendods', 'nds', 'http://www.abgx.net/rss/abgn/posted.rss', 'title', '/^Req\\s\\d{1,6}\\s\\-\\s(?P<title>.\\S*)/i', 'title', '/^Req (?P<reqid>\\d{3,6})/i', NULL);
INSERT INTO feed (CODE, NAME, url, titlecol, titleregex, reqidcol, reqidregex, lastupdate) VALUES ('alt.binaries.inner-sanctum', 'innersanct', 'http://rss.omgwtfnzbs.org/rss-info.php', 'title', '/^(?P<title>.*)$/i', '', '-1', NULL);
INSERT INTO feed (CODE, NAME, url, titlecol, titleregex, reqidcol, reqidregex, lastupdate) VALUES ('alt.binaries.moovee', 'abmoovee', 'http://abmoovee.allfilled.com/rss.php', 'title', '/(?P<title>.*)/i', 'description', '/^ReqId: (?P<reqid>\\d{3,6})/i', NULL);
INSERT INTO feed (CODE, NAME, url, titlecol, titleregex, reqidcol, reqidregex, lastupdate) VALUES ('alt.binaries.srrdb', 'srrdb', 'http://www.srrdb.com/feed/srrs', 'title', '/(?P<title>.*)/i', 'description', '/Archived files.*<td>(?P<reqid>.*?\\.(avi|mkv|mp4|mov|wmv|iso|img|mp3|m3u|gcm|ps3|wad|ac3|nds|bin|mdf))<\\/td>/ims', null);



DROP TABLE IF EXISTS `item`;
CREATE TABLE `item` 
(
	`ID` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`feedID` INT NOT NULL,
	`reqid` VARCHAR(255) NOT NULL,
	`title` VARCHAR(255) NULL,
	`link` VARCHAR(1000) NULL,
	`description` VARCHAR(1000) NULL,
	`pubdate` DATETIME NOT NULL,
	`guid` VARCHAR(1000) NULL,
	`adddate` DATETIME NOT NULL,
	`adddateunique` BIGINT UNSIGNED NOT NULL,
	PRIMARY KEY  (`ID`)
) ENGINE=MYISAM AUTO_INCREMENT=1 ;

CREATE INDEX ix_item_feedID ON item (feedID);
CREATE INDEX ix_item_reqid ON item (reqid);
CREATE UNIQUE INDEX ix_reqid_title ON item (reqid, title);
CREATE UNIQUE INDEX ix_feedid_reqid ON item (feedID, reqid);
CREATE INDEX ix_item_adddateunique ON item (adddateunique);


DROP TABLE IF EXISTS `access`;
CREATE TABLE `access`
(
	`ID` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`guid` VARCHAR(40) NOT NULL,
	`role` INT NOT NULL,
	`description` VARCHAR(1000) NULL,
	`misc` VARCHAR(1000) NULL,
	PRIMARY KEY  (`ID`)
) ENGINE=MYISAM AUTO_INCREMENT=1 ;

CREATE INDEX ix_access_guid ON access (guid);