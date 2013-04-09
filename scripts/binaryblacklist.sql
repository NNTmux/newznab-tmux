DROP TABLE IF EXISTS `binaryblacklist`;
CREATE TABLE IF NOT EXISTS `binaryblacklist` (
  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `groupname` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `regex` varchar(2000) COLLATE utf8_unicode_ci NOT NULL,
  `msgcol` int(11) unsigned NOT NULL DEFAULT '1',
  `optype` int(11) unsigned NOT NULL DEFAULT '1',
  `status` int(11) unsigned NOT NULL DEFAULT '1',
  `description` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1000100;

--
-- Dumping data for table `binaryblacklist`
--

INSERT INTO `binaryblacklist` (`ID`, `groupname`, `regex`, `msgcol`, `optype`, `status`, `description`) VALUES
(100001, 'alt.binaries.*', '(brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|latin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)[\\)]?( \\-)?[ \\-\\.]((19|20)\\d\\d|(480|720|1080)(i|p)|3d|5\\.1|dts|ac3|truehd|(bd|dvd|hd|sat|vhs|web)\\.?rip|(bd.)?(h|x).?2?64|divx|xvid|bluray|svcd|board|custom|"|(d|h|p|s)d?v?tv|m?dvd(-|sc)?r|int(ernal)?|nzb|par2|\\b(((dc|ld|md|ml|dl|hr|se)[.])|(anime\\.)|(fs|ws)|dsr|pal|ntsc|iso|complete|cracked|ebook|extended|dirfix|festival|proper|game|limited|read.?nfo|real|rerip|repack|remastered|retail|samplefix|scan|screener|theatrical|uncut|unrated|incl|winall)\\b|doku|doc|dub|sub|\\(uncut\\))', 1, 1, 0, 'Blacklists non-english releases.'),
(100002, 'alt.binaries.*', '[ -.](bl|cz|de|es|fr|ger|heb|hu|hun|ita|ko|kor|nl|pl|se)[ -.]((19|20)\\d\\d|(480|720|1080)(i|p)|(bd|dvd.?|sat|vhs)?rip?|(bd|dl)mux|( -.)?(dub|sub)(ed|bed)?|complete|convert|(d|h|p|s)d?tv|dirfix|docu|dual|dvbs|dvdscr|eng|(h|x).?2?64|int(ernal)?|pal|proper|repack|xbox)', 1, 1, 0, 'Blacklists non-english abbreviated releases.'),
(100003, 'alt.binaries.*', '[ -.]((19|20)\\d\\d|(bd|dvd.?|sat|vhs)?rip?|custom|divx|dts)[ -.](bl|cz|de|es|fr|ger|heb|hu|ita|ko|kor|nl|pl|se)[ -.]', 1, 1, 0, 'Blacklists non-english abbreviated (reversed) releases.'),
(100004, 'alt.binaries.*', '[ -.](chinese.subbed|dksubs|fansubs?|finsub|hebdub|hebsub|korsub|norsub|nordicsubs|nl( -.)?sub(ed|bed|s)?|nlvlaams|pldub|plsub|slosinh|swesub|truefrench|vost(fr)?)[ -.]', 1, 1, 0, 'Blacklists non-english subtitled releases.'),
(100005, 'alt.binaries.*', '[ -._](4u\\.nl|nov[ a]+rip|realco|videomann|vost)[ -._]', 1, 1, 0, 'Blacklists non-english (release group specific) releases.'),
(100006, 'alt.binaries.*', '[ -.]((bd|dl)mux|doku|\\[foreign\\]|seizoen|staffel)[ -.]', 1, 1, 0, 'Blacklists non-english (lang specific) releases.'),
(100020, 'alt.binaries.*', '[ -.](imageset|pictureset|xxx)[ -.]', 1, 1, 0, 'Blacklists porn releases.'),
(100025, 'alt.binaries.*', 'hdnectar|nzbcave', 1, 1, 0, 'Bad releases.'),
(100030, 'alt.binaries.*', '[ -](de|es|fr|it|ko|nl|pl|se)[ -]((audiobook|cuefix|bonus|bootleg|dirfix|nfofix|dvb|promo|readnfo|real|rerip|repack|proper)[ -])?\\d?(bluray|hddvd|dvd|ddc|cd(s|rs|rep)?|mcd|(\\d_)?(vls|m?lp|ep|vinyl))', 1, 1, 0, 'Blacklists music, music video etc releases.'),
(100040, 'alt.binaries.*', '[ -.](espn|ultimate.?fighter|mlb|mls|mfc|mma|ncaa|nba|nbl|nfl|nhl|pga|tna|ufc|us.?open|wec|wcw|world.?series|wwe|wwf)[ -.].*((480|720|1080)(i|p)|(d|h|p|s)d?tv|divx|(dvd.?|sat|vhs)rip|(h|x).?264|vcd|xvi ?d)', 1, 1, 0, 'Blacklists american and canadian sports releases.'),
(100041, 'alt.binaries.*', '[ -.](anz|bundesliga|cfb|epl|fa.?cup|la.?liga|netball|nrl|rugby|serie.?a|supercup|super.?league|uefa|wimbledon)[ -.].*((480|720|1080)(i|p)|(d|h|p|s)d?tv|divx|(dvd.?|sat|vhs)rip|(h|x).?264|xvid)', 1, 1, 0, 'Blacklists european sports releases.'),
(100042, 'alt.binaries.*', '[ -.](afl|fifa|fina|gymanstics|hdnet.?fights|iihf|league(.?challenge|.?cup|.?soccer)|olympics?|playoffs|wba|world.?championships?|world cup)[ -.].*((480|720|1080)(i|p)|(d|h|p|s)d?tv|divx|(dvd.?|sat|vhs)rip|(h|x).?264|xvid)', 1, 1, 0, 'Blacklists other sports releases.'),
(100043, 'alt.binaries.*', '[ -.](bmx|motogp|sbk|superbike|tour.?de.?france|la.?vuelta|wsbk).*[ -.]((480|720|1080)(i|p)|(bi|motor)?cycle?(ing)?|(d|h|p|s)d?tv|divx|(dvd.?|sat|vhs)rip|(h|x).?264|rac(e|ing)|round|series|xvid)', 1, 1, 0, 'Blacklists bike racing releases.'),
(100044, 'alt.binaries.*', '[ -.](ama.?supercross|dtm|f1.?legends|fia|formula(.?one|.?1|.?honda|.?renault)|gp3|grand.?prix|indycar|nascar|rally(.?challenge|.?championship)|supercars?|wrc|wtcc).*[ -.]((480|720|1080)(i|p)|(d|h|p|s)d?tv|divx|(dvd.?|sat|vhs)rip|(h|x).?264|rac(e|ing)|round|series|xvid)', 1, 1, 0, 'Blacklists car racing releases.'),
(100045, 'alt.binaries.*', '[ -.](horse.?racing|kentucky.?derby).*[ -.]((480|720|1080)(i|p)|(d|h|p|s)d?tv|divx|(dvd.?|sat|vhs)rip|(h|x).?264|rac(e|ing)|xvid)', 1, 1, 0, 'Blacklists other racing releases.');

