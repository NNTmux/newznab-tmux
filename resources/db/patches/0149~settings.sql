ALTER TABLE  site
ADD  section VARCHAR(25)  NOT NULL DEFAULT '',
ADD  subsection VARCHAR(25)  NOT NULL DEFAULT '',
ADD  name    VARCHAR(25)  NOT NULL DEFAULT '',
ADD  hint   TEXT NOT NULL;
ALTER TABLE site DROP COLUMN id;
ALTER TABLE site DROP INDEX setting;
UPDATE site SET name = setting;
ALTER TABLE site
ADD PRIMARY KEY (section, subsection, name),
ADD UNIQUE INDEX ui_settings_setting (setting);
RENAME TABLE site TO settings;
UPDATE settings SET value = 149 WHERE setting = 'sqlpatch';

