#Make sure innerfileblacklist setting exists.

UPDATE settings SET section = 'indexer', subsection = 'ppa', name = 'innerfileblacklist', value = '/setup\.exe|password\.url/i', hint = 'You can add a regex here to set releases to potentially passworded when a file name inside a rar/zip matches this regex.' WHERE setting = 'innerfileblacklist';

#Make sure minsizetoformrelease setting exists.

 UPDATE settings SET subsection = 'release' where setting = 'minsizetoformrelease';

 #Make sure maxsizetoformrelease setting exists.

 UPDATE settings SET subsection = 'release' where setting = 'maxsizetoformrelease';

#Make sure minfilestoformrelease setting exists.

UPDATE settings SET subsection = 'release' where setting = 'minfilestoformrelease';

#Update coverspath setting.

 UPDATE settings SET section = 'site', subsection = 'main' where setting = 'coverspath';

 #Update coverspath setting.

 UPDATE settings SET section = 'site', subsection = 'main' where setting = 'style';
