ALTER TABLE  settings
ADD  section VARCHAR(25)  NOT NULL DEFAULT '',
ADD  subsection VARCHAR(25)  NOT NULL DEFAULT '',
ADD  name    VARCHAR(25)  NOT NULL DEFAULT '',
ADD  hint   TEXT NOT NULL;
ALTER TABLE settings DROP COLUMN id;
ALTER TABLE settings DROP INDEX setting;
UPDATE settings SET name = setting;
ALTER TABLE settings
ADD PRIMARY KEY (section, subsection, name),
ADD UNIQUE INDEX ui_settings_setting (setting);
RENAME TABLE settings TO settings;

