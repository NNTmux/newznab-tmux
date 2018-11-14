# Add missing saburl setting

UPDATE settings SET section = 'apps', subsection = 'sabnzbplus', name = 'url' WHERE setting = 'saburl';
