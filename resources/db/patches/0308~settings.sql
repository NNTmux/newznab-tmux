# Add OmdbAPI key setting to settings table

INSERT IGNORE INTO settings (section, subsection, name, value, hint, setting) VALUES ('APIs', '', 'omdbkey', '',
'OmdbAPI key obtained from Omdb.Used for Omdb API lookups', 'omdbkey');
