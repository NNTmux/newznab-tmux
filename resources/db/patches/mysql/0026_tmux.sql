INSERT IGNORE INTO tmux (setting, value) VALUE ('ffmpeg_duration', '5');
INSERT IGNORE INTO tmux (setting, VALUE) VALUE ('ffmpeg_image_time', '5');
INSERT IGNORE INTO tmux (setting, value) VALUE ('processvideos', '0');

ALTER TABLE releases ADD proc_pp TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE releases ADD videostatus TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE releases ADD audiostatus TINYINT(1) NOT NULL DEFAULT 0;
