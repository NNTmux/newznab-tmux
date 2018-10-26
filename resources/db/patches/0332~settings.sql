# Update settings table

UPDATE settings SET section = 'apps', subsection = '', name = 'zippath' WHERE setting = 'zippath';
UPDATE settings SET section = '', subsection = '', name = 'max_headers_iteration' WHERE setting = 'max_headers_iteration';