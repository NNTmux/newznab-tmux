#Update old regexes
UPDATE release_naming_regexes SET group_regex = '^alt\\.binaries\\.warez(|\\.ibm-pc)\\.0-day$' WHERE id IN (1074, 1075, 1076, 1077, 1078);

#Add new regexes
INSERT INTO release_naming_regexes (id, group_regex, regex, status, description, ordinal)
VALUES (
  1157,
  '^alt\\.binaries\\.multimedia\\.sports$',
  '/^\\[?AFL.+\\" ?[ .-]?(?P<match0>.+?) ?[ .](7z|avi|md5|mkv|mp4|nfo|nzb|par|rar|sfv|vol)t?\\d?+.+yEnc$/',
  1,
  '//[AFL 2017 Round 8 NMFC vs SYD 720p] - "AFL 2017 Round 8 NMFC vs SYD 720p par2" yEnc or AFL.2017.Round.8.ADEL.vs.MELB.480p] - "AFL.2017.Round.8.ADEL.vs.MELB.480p.sfv" yEnc',
  5
);