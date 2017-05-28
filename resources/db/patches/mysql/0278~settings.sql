# Add missing setting for settings table.

INSERT IGNORE INTO settings (section, subsection, name, value, hint, setting) VALUES ('site', 'main', 'loggingopt', '0', "Where you would like to log failed logins to the site.", 'loggingopt');

INSERT IGNORE INTO settings (section, subsection, name, value, hint, setting) VALUES ('site', 'main', 'logfile', '/var/www/nntmux/resources/logs/failed-login.log', "Location of log file (MUST be set if logging to file is set).", 'logfile');


