INSERT IGNORE INTO settings (name, value, hint, setting)
VALUES (
	'processthumbnails', 0,
	'Whether to attempt to process a video thumbnail image. You must have ffmpeg for this.',
	'processthumbnails'
);

UPDATE site SET value = 1
WHERE setting = 'processthumbnails' AND (SELECT * FROM (SELECT value FROM site WHERE setting = 'ffmpegpath') s) != '';