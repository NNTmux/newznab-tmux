#Create the new steam_apps table

DROP TABLE IF EXISTS steam_apps;
CREATE TABLE steam_apps (
  appid        INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  name         VARCHAR(255)        NOT NULL DEFAULT '',
  PRIMARY KEY (appid),
  UNIQUE KEY (name)
)
  ENGINE          = MYISAM
  DEFAULT CHARSET = utf8
  COLLATE         = utf8_unicode_ci
  AUTO_INCREMENT = 1;
