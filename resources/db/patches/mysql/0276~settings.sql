#Make sure settings in settings table exist.

UPDATE settings SET section = 'indexer', subsection = 'ppa', name = 'innerfileblacklist', value = '/setup\.exe|password\.url/i', hint = 'You can add a regex here to set releases to potentially passworded when a file name inside a rar/zip matches this regex.' WHERE setting = 'innerfileblacklist';

UPDATE settings SET subsection = 'release' WHERE setting = 'minsizetoformrelease';

UPDATE settings SET subsection = 'release' WHERE setting = 'maxsizetoformrelease';

UPDATE settings SET subsection = 'release' WHERE setting = 'minfilestoformrelease';

UPDATE settings SET section = 'archive', subsection = 'fetch', name = 'end' WHERE setting = 'fetchlastcompressedfiles';

UPDATE settings SET section = 'APIs' WHERE setting = 'amazonassociatetag';

UPDATE settings SET section = 'APIs' WHERE setting = 'amazonprivkey';

UPDATE settings SET section = 'APIs' WHERE setting = 'amazonpubkey';

UPDATE settings SET section = 'APIs' WHERE setting = 'giantbombkey';

UPDATE settings SET section = 'APIs' WHERE setting = 'anidbkey';

UPDATE settings SET section = 'APIs' WHERE setting = 'fanarttvkey';

UPDATE settings SET section = 'APIs' WHERE setting = 'rottentomatokey';

UPDATE settings SET section = 'APIs' WHERE setting = 'tmdbkey';

UPDATE settings SET section = 'APIs' WHERE setting = 'trakttvclientkey';

UPDATE settings SET section = 'APIs', subsection = 'recaptcha', name = 'sitekey' WHERE setting = 'recaptchasitekey';

UPDATE settings SET section = 'APIs', subsection = 'recaptcha', name = 'secretkey' WHERE setting = 'recaptchasecretkey';

UPDATE settings SET section = 'APIs', subsection = 'recaptcha', name = 'enabled' WHERE setting = 'recaptchaenabled';

UPDATE settings SET section = 'apps', subsection = 'indexer', name = 'magic_file_path' WHERE setting = 'magic_file_path';

UPDATE settings SET section = 'apps', name = 'unrarpath' WHERE setting = 'unrarpath';

UPDATE settings SET section = 'apps', name = '7zippath' WHERE setting = 'zippath';

UPDATE settings SET section = 'apps', name = 'mediainfopath' WHERE setting = 'mediainfopath';

UPDATE settings SET section = 'apps', name = 'ffmpegpath' WHERE setting = 'ffmpegpath';

UPDATE settings SET section = 'apps', name = 'timeoutpath' WHERE setting = 'timeoutpath';

UPDATE settings SET section = 'apps', name = 'lamepath' WHERE setting = 'lamepath';

UPDATE settings SET section = 'apps', name = 'yydecoderpath' WHERE setting = 'yydecoderpath';

UPDATE settings SET section = 'apps', subsection = 'sabnzbplus', name = 'apikey' WHERE setting = 'sabapikey';

UPDATE settings SET section = 'apps', subsection = 'sabnzbplus', name = 'apikeytype' WHERE setting = 'sabapikeytype';

UPDATE settings SET section = 'apps', subsection = 'sabnzbplus', name = 'integrationtype' WHERE setting = 'sabintegrationtype';

UPDATE settings SET section = 'indexer', subsection = 'categorise', name = 'categorizeforeign' WHERE setting = 'categorizeforeign';

UPDATE settings SET section = 'indexer', subsection = 'categorise', name = 'catwebdl' WHERE setting = 'catwebdl';

UPDATE settings SET section = 'indexer', subsection = 'categorise', name = 'imdblanguage', setting = 'imdblanguage' WHERE setting = 'lookuplanguage';

UPDATE settings SET section = 'indexer', subsection = 'categorise', name = 'imdburl' WHERE setting = 'imdburl';

UPDATE settings SET section = 'indexer', subsection = 'processing', name = 'collection_timeout' WHERE setting = 'collection_timeout';

UPDATE settings SET section = 'indexer', subsection = 'processing', name = 'last_run_time' WHERE setting = 'last_run_time';

UPDATE settings SET section = 'site', subsection = 'main', name = 'code' WHERE setting = 'code';

UPDATE settings SET section = 'site', subsection = 'main', name = 'title' WHERE setting = 'title';

UPDATE settings SET section = 'site', subsection = 'main', name = 'email' WHERE setting = 'email';

UPDATE settings SET section = 'site', subsection = 'main', name = 'coverspath' WHERE setting = 'coverspath';

UPDATE settings SET section = 'site', subsection = 'main', name = 'style' WHERE setting = 'style';

UPDATE settings SET section = 'site', subsection = 'main', name = 'userselstyle' WHERE setting = 'userselstyle';

UPDATE settings SET section = 'site', subsection = 'main', name = 'dereferrer_link' WHERE setting = 'dereferrer_link';

UPDATE settings SET section = 'site', subsection = 'main', name = 'footer' WHERE setting = 'footer';

UPDATE settings SET section = 'site', subsection = 'main', name = 'home_link' WHERE setting = 'home_link';

UPDATE settings SET section = 'site', subsection = 'main', name = 'logfile' WHERE setting = 'logfile';

UPDATE settings SET section = 'site', subsection = 'main', name = 'loggingopt' WHERE setting = 'loggingopt';

UPDATE settings SET section = 'site', subsection = 'main', name = 'menuposition' WHERE setting = 'menuposition';

UPDATE settings SET section = 'site', subsection = 'main', name = 'metadescription' WHERE setting = 'metadescription';

UPDATE settings SET section = 'site', subsection = 'main', name = 'metakeywords' WHERE setting = 'metakeywords';

UPDATE settings SET section = 'site', subsection = 'main', name = 'metatitle' WHERE setting = 'metatitle';

UPDATE settings SET section = 'site', subsection = 'main', name = 'strapline' WHERE setting = 'strapline';

UPDATE settings SET section = 'site', subsection = 'main', name = 'tandc' WHERE setting = 'tandc';

UPDATE settings SET section = 'site', subsection = 'spotnab', name = 'spotnabautoenable' WHERE setting = 'spotnabautoenable';

UPDATE settings SET section = 'site', subsection = 'spotnab', name = 'spotnabbroadcast' WHERE setting = 'spotnabbroadcast';

UPDATE settings SET section = 'site', subsection = 'spotnab', name = 'spotnabdiscover' WHERE setting = 'spotnabdiscover';

UPDATE settings SET section = 'site', subsection = 'spotnab', name = 'spotnabemail' WHERE setting = 'spotnabemail';

UPDATE settings SET section = 'site', subsection = 'spotnab', name = 'spotnabgroup' WHERE setting = 'spotnabgroup';

UPDATE settings SET section = 'site', subsection = 'spotnab', name = 'spotnablastarticle' WHERE setting = 'spotnablastarticle';

UPDATE settings SET section = 'site', subsection = 'spotnab', name = 'spotnabpost' WHERE setting = 'spotnabpost';

UPDATE settings SET section = 'site', subsection = 'spotnab', name = 'spotnabprivacy' WHERE setting = 'spotnabprivacy';

UPDATE settings SET section = 'site', subsection = 'spotnab', name = 'spotnabsiteprvkey' WHERE setting = 'spotnabsiteprvkey';

UPDATE settings SET section = 'site', subsection = 'spotnab', name = 'spotnabsitepubkey' WHERE setting = 'spotnabsitepubkey';

UPDATE settings SET section = 'site', subsection = 'spotnab', name = 'spotnabuser' WHERE setting = 'spotnabuser';

UPDATE settings SET section = 'site', subsection = 'google', name = 'adbrowse' WHERE setting = 'adbrowse';

UPDATE settings SET section = 'site', subsection = 'google', name = 'addetail' WHERE setting = 'addetail';

UPDATE settings SET section = 'site', subsection = 'google', name = 'adheader' WHERE setting = 'adheader';

UPDATE settings SET section = 'site', subsection = 'google', name = 'gogle_adsense_acc' WHERE setting = 'google_adsense_acc';

UPDATE settings SET section = 'site', subsection = 'google', name = 'google_adsense_search' WHERE setting = 'google_adsense_search';

UPDATE settings SET section = 'site', subsection = 'google', name = 'google_analytics_acc' WHERE setting = 'google_analytics_acc';

INSERT IGNORE INTO settings (section, subsection, name, value, hint, setting) VALUES ('shell', 'date', 'format', '%Y-%m-%d %T', 'Format string to use in shell date command output. See `man date` for acceptable format.\nDefault: %Y-%m-%d %T', 'shell.date.format');












