#Update AniDb settings
UPDATE IGNORE settings SET section = 'APIs', subsection = 'AniDB', name = 'max_update_frequency' WHERE name = 'lastanidbupdate';
UPDATE IGNORE settings SET section = 'APIs', subsection = 'AniDB', name = 'last_full_update' WHERE name = 'intanidbupdate';
