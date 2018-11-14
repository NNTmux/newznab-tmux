# Increase rsstoken column to varchar(64) to accommodate new api key size

ALTER TABLE users CHANGE rsstoken rsstoken VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;