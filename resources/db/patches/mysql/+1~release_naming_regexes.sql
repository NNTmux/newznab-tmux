#Add updated regex
INSERT INTO release_naming_regexes (id, group_regex, regex, status, description, ordinal)
VALUES (
  1157,
  '^alt\\.binaries\\.multimedia\\.sports$',
  '/^\\[?AFL.+\\" ?[ .-]?(?P<match0>.+?) ?[ .](7z|avi|md5|mkv|mp4|nfo|nzb|par|rar|sfv|vol)t?\\d?+.+yEnc$/',
  1,
  '//[AFL 2017 Round 8 NMFC vs SYD 720p] - "AFL 2017 Round 8 NMFC vs SYD 720p par2" yEnc or AFL.2017.Round.8.ADEL.vs.MELB.480p] - "AFL.2017.Round.8.ADEL.vs.MELB.480p.sfv" yEnc',
  1
);