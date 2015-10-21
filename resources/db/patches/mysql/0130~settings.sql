INSERT IGNORE INTO settings (section, subsection, name, value, hint, setting)
VALUES (
  'site',
  'main',
  'coverspath',
  './www/covers',
  'Path to covers folder',
  'coverspath'
);