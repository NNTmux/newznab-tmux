ALTER TABLE releases ADD jpgstatus TINYINT(1) NOT NULL DEFAULT 0;
INSERT IGNORE INTO tmux (setting, value) VALUES ('processjpg', 0);
