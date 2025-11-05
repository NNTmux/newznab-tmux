<?php

namespace Blacklight;

use App\Models\Predb;

/**
 * Cleans names for releases/imports/namefixer.
 * Names of group functions should match between CollectionsCleaning and this file.
 *
 *
 * Class ReleaseCleaning
 */
class ReleaseCleaning
{
    /**
     * Used for matching endings in article subjects.
     *
     * @const
     *
     * @string
     */
    private const REGEX_END = '[ -]{0,3}yEnc$/u';

    /**
     * Used for matching file extension endings in article subjects.
     *
     * @const
     *
     * @string
     */
    private const REGEX_FILE_EXTENSIONS = '([\-_](proof|sample|thumbs?))*(\.part\d*(\.rar)?|\.rar|\.7z)?(\d{1,3}\.rev"|\.vol.+?"|\.[A-Za-z0-9]{2,4}"|")';

    /**
     * Used for matching size strings in article subjects.
     *
     * @example ' - 365.15 KB - '
     *
     * @const
     *
     * @string
     */
    private const REGEX_SUBJECT_SIZE = '[ -]{0,3}\d+([.,]\d+)? [kKmMgG][bB][ -]{0,3}';

    /**
     * @var string
     */
    public $e0;

    /**
     * @var string
     */
    public $e1;

    /**
     * @var string
     */
    public $e2;

    /**
     * @var string
     */
    public $fromName = '';

    /**
     * @var string
     */
    public $groupName = '';

    /**
     * @var string
     */
    public $size = '';

    /**
     * @var string
     */
    public $subject = '';

    /**
     * @var Regexes
     */
    protected $_regexes;

    /**
     * ReleaseCleaning constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        // Extensions.
        $this->e0 = CollectionsCleaning::REGEX_FILE_EXTENSIONS;
        $this->e1 = CollectionsCleaning::REGEX_FILE_EXTENSIONS.CollectionsCleaning::REGEX_END;
        $this->e2 = CollectionsCleaning::REGEX_FILE_EXTENSIONS.CollectionsCleaning::REGEX_SUBJECT_SIZE.CollectionsCleaning::REGEX_END;
        $this->_regexes = new Regexes(['Settings' => null, 'Table_Name' => 'release_naming_regexes']);
    }

    /**
     * @return array|false|string
     *
     * @throws \Exception
     */
    public function releaseCleaner($subject, $fromName, $groupName, bool $usePre = false)
    {
        $hit = $hits = [];
        // Get pre style name from releases.name
        if (preg_match_all(
            '/([\w\(\)]+[\s\._-]([\w\(\)]+[\s\._-])+[\w\(\)]+-\w+)/',
            $subject,
            $hits
        )) {
            foreach ($hits as $hit) {
                foreach ($hit as $val) {
                    $title = Predb::query()->where('title', trim($val))->first(['title', 'id']);
                    // don't match against ab.teevee if title is for just the season
                    if (! empty($title) && $groupName === 'alt.binaries.teevee' && preg_match('/\.S\d\d\./', $title['title'], $hit)) {
                        $title = null;
                    }
                    if ($title !== null) {
                        return [
                            'cleansubject' => $title['title'],
                            'properlynamed' => true,
                            'increment' => false,
                            'predb' => $title['id'],
                            'requestid' => false,
                        ];
                    }
                }
            }
        }
        // Get pre style name from requestid
        if (preg_match('/^\[ ?(\d{4,6}) ?\]/', $subject, $hit) ||
            preg_match('/^REQ\s*(\d{4,6})/i', $subject, $hit) ||
            preg_match('/^(\d{4,6})-\d{1}\[/', $subject, $hit) ||
            preg_match('/(\d{4,6}) -/', $subject, $hit)
        ) {
            $title = Predb::query()->where(['predb.requestid' => $hit[1], 'g.name' => $groupName])->join('usenet_groups as g', 'g.id', '=', 'predb.groups_id')->first(['predb.title', 'predb.id']);
            // check for predb title matches against other groups where it matches relative size / fromname
            // known crossposted requests only atm
            $reqGname = '';
            switch ($groupName) {
                case 'alt.binaries.etc':
                    if ($fromName === 'kingofpr0n (brian@iamking.ws)') {
                        $reqGname = 'alt.binaries.teevee';
                    }
                    break;
                case 'alt.binaries.mom':
                    if ($fromName === 'Yenc@power-post.org (Yenc-PP-A&A)' ||
                        $fromName === 'yEncBin@Poster.com (yEncBin)'
                    ) {
                        $reqGname = 'alt.binaries.moovee';
                    }
                    break;
                case 'alt.binaries.hdtv.x264':
                    if ($fromName === 'moovee@4u.tv (moovee)') {
                        $reqGname = 'alt.binaries.moovee';
                    }
                    break;
            }
            if ($title === null && ! empty($reqGname)) {
                $title = Predb::query()->where(['predb.requestid' => $hit[1], 'g.name' => $reqGname])->join('usenet_groups as g', 'g.id', '=', 'predb.groups_id')->first(['predb.title', 'predb.id']);
            }
            // don't match against ab.teevee if title is for just the season
            if ($groupName === 'alt.binaries.teevee' && preg_match('/\.S\d\d\./', $title['title'], $hit)) {
                $title = null;
            }
            if ($title !== null) {
                return [
                    'cleansubject' => $title['title'],
                    'properlynamed' => true,
                    'increment' => false,
                    'predb' => $title['id'],
                    'requestid' => true,
                ];
            }
        }
        if ($usePre === true) {
            return false;
        }

        // Try DB regex.
        $potentialName = $this->_regexes->tryRegex($subject, $groupName);
        if ($potentialName) {
            return [
                'id' => $this->_regexes->matchedRegex,
                'cleansubject' => $potentialName,
                'properlynamed' => false,
            ];
        }

        // if www.town.ag releases check against generic_town regexes
        if (preg_match('/www\.town\.ag$/i', $subject)) {
            return $this->generic_town();
        }
        if ($groupName === 'alt.binaries.teevee') {
            return $this->teevee();
        }

        return $this->generic();
    }

    public function teevee(): array
    {
        // [140022]-[04] - [01/40] - "140022-04.nfo" yEnc
        if (preg_match('/\[\d+\]-\[.+\] - \[\d+\/\d+\] - "\d+\-.+" yEnc/', $this->subject)) {
            return [
                'cleansubject' => $this->subject,
                'properlynamed' => false,
                'ignore' => true,
            ];
        }

        return [
            'cleansubject' => $this->releaseCleanerHelper($this->subject),
            'properlynamed' => false,
        ];
    }

    /**
     * @return array|string
     */
    public function generic_town()
    {
        // <TOWN><www.town.ag > <download all our files with>>> www.ssl-news.info <<< > [05/87] - "Deep.Black.Ass.5.XXX.1080p.WEBRip.x264-TBP.part03.rar" - 7,87 GB yEnc
        // <TOWN><www.town.ag > <partner of www.ssl-news.info > [02/24] - "Dragons.Den.UK.S11E02.HDTV.x264-ANGELiC.nfo" - 288,96 MB yEnc
        // <TOWN><www.town.ag > <SSL - News.Info> [6/6] - "TTT.Magazine.2013.08.vol0+1.par2" - 33,47 MB yEnc
        if (preg_match('/^<TOWN>.+?town\.ag.+?(www\..+?|News)\.[iI]nfo.+? \[\d+\/\d+\]( -)? "(.+?)(-sample)?'.$this->e0.' - \d+[.,]\d+ [kKmMgG][bB]M? yEnc$/', $this->subject, $hit)) {
            return $hit[3];
        } // [ TOWN ]-[ www.town.ag ]-[ partner of www.ssl-news.info ]-[ 1080p ] - [320/352] - "Gq7YGEWLy8wAA2NhbZx5LukEa.vol000+5.par2" - 17.09 GB yEnc
        if (preg_match('/^\[\s*TOWN\s*\][\-_\s]{0,3}\[\s*www\.town\.ag\s*\][\-_\s]{0,3}\[\s*partner of www\.ssl-news\.info\s*\][\-_\s]{0,3}\[\s* .*\s*\][\-_\s]{0,3}\[\d+\/\d+\][\-_\s]{0,4}"([\w\säöüÄÖÜß+¤¶!.,&_()\[\]\'\`{}#-]{8,}?\b.?)'.$this->e2, $this->subject, $hit)) {
            return $hit[1];
        } // <TOWN><www.town.ag > <download all our files with>>> www.ssl-news.info <<< >IP Scanner Pro 3.21-Sebaro - [1/3] - "IP Scanner Pro 3.21-Sebaro.rar" yEnc
        if (preg_match(
            '/^<TOWN>.+?town\.ag.+?(www\..+?|News)\.[iI]nfo.+? \[\d+\/\d+\]( -)? "(.+?)(-sample)?'.
            $this->e1,
            $this->subject,
            $hit
        )
        ) {
            return $hit[3];
        } // (05/10) -<TOWN><www.town.ag > <partner of www.ssl-news.info > - "D.Olivier.Wer Boeses.saet-gsx-.part4.rar" - 741,51 kB - yEnc
        if (preg_match(
            '/^\(\d+\/\d+\) -<TOWN><www\.town\.ag >\s+<partner.+> - ("|#34;)([\w. ()-]{8,}?\b)(\.par2|-\.part\d+\.rar|\.nfo)("|#34;) - \d+[.,]\d+ [kKmMgG][bB]( -)? yEnc$/',
            $this->subject,
            $hit
        )
        ) {
            return $hit[2];
        } // [ TOWN ]-[ www.town.ag ]-[ partner of www.ssl-news.info ]-[ MOVIE ] [14/19] - "Night.Vision.2011.DVDRip.x264-IGUANA.part12.rar" - 660,80 MB yEnc
        if (preg_match(
            '/^\[ TOWN \][ _-]{0,3}\[ www\.town\.ag \][ _-]{0,3}\[ partner of www\.ssl-news\.info \][ _-]{0,3}\[ .* \] \[\d+\/\d+\][ _-]{0,3}("|#34;)(.+)((\.part\d+\.rar)|(\.vol\d+\+\d+\.par2))("|#34;)[ _-]{0,3}\d+[.,]\d+ [kKmMgG][bB][ _-]{0,3}yEnc$/i',
            $this->subject,
            $hit
        )
        ) {
            return $hit[2];
        } // [ TOWN ]-[ www.town.ag ]-[ partner of www.ssl-news.info ]-[ MOVIE ] [01/84] - "The.Butterfly.Effect.2.2006.1080p.BluRay.x264-LCHD.par2" - 7,49 GB yEnc
        if (preg_match(
            '/^\[ TOWN \][ _-]{0,3}\[ www\.town\.ag \][ _-]{0,3}\[ partner of www\.ssl-news\.info \][ _-]{0,3}\[ .* \] \[\d+\/\d+\][ _-]{0,3}("|#34;)(.+)\.(par2|rar|nfo|nzb)("|#34;)[ _-]{0,3}\d+[.,]\d+ [kKmMgG][bB][ _-]{0,3}yEnc$/i',
            $this->subject,
            $hit
        )
        ) {
            return $hit[2];
        } // [ TOWN ]-[ www.town.ag ]-[ partner of www.ssl-news.info ] [22/22] - "Arsenio.Hall.2013.09.11.Magic.Johnson.720p.HDTV.x264-2HD.vol31+11.par2" - 1,45 GB yEnc
        if (preg_match(
            '/^\[ TOWN \][ _-]{0,3}\[ www\.town\.ag \][ _-]{0,3}\[ partner of www\.ssl-news\.info \][ _-]{0,3}(\[ TV \] )?\[\d+\/\d+\][ _-]{0,3}("|#34;)(.+)((\.part\d+\.rar)|(\.vol\d+\+\d+\.par2)|\.nfo|\.vol\d+\+\.par2)("|#34;)[ _-]{0,3}\d+[.,]\d+ [kKmMgG][bB][ _-]{0,3}yEnc$/i',
            $this->subject,
            $hit
        )
        ) {
            return $hit[3];
        } // [ TOWN ]-[ www.town.ag ]-[ partner of www.ssl-news.info ] [01/28] - "Arsenio.Hall.2013.09.18.Dr.Phil.McGraw.HDTV.x264-2HD.par2" - 352,58 MB yEnc
        if (preg_match(
            '/^\[ TOWN \][ _-]{0,3}\[ www\.town\.ag \][ _-]{0,3}\[ partner of www\.ssl-news\.info \][ _-]{0,3}(\[ TV \] )?\[\d+\/\d+\][ _-]{0,3}("|#34;)(.+)\.par2("|#34;)[ _-]{0,3}\d+[.,]\d+ [kKmMgG][bB][ _-]{0,3}yEnc$/i',
            $this->subject,
            $hit
        )
        ) {
            return $hit[3];
        } // 4675.-.Wedding.Planner.multi3.(EU) <TOWN><www.town.ag > <partner of www.ssl-news.info > <Games-NDS >  [01/10] - "4675.-.Wedding.Planner.multi3.(EU).par2" - 72,80 MB - yEnc
        if (preg_match(
            '/^\d+\.-\.(.+) <TOWN><www\.town\.ag >\s+<partner .+>\s+<.+>\s+\[\d+\/\d+\] - ("|#34;).+("|#34;).+yEnc$/',
            $this->subject,
            $hit
        )
        ) {
            return $hit[1];
        }
        // 4675.-.Wedding.Planner.multi3.(EU) <TOWN><www.town.ag > <partner of www.ssl-news.info > <Games-NDS >  [01/10] - "4675.-.Wedding.Planner.multi3.(EU).par2" - 72,80 MB - yEnc
        // Some have no yEnc
        if (preg_match(
            '/^\d+\.-\.(.+) <TOWN><www\.town\.ag >\s+<partner .+>\s+<.+>\s+\[\d+\/\d+\] - ("|#34;).+/',
            $this->subject,
            $hit
        )
        ) {
            return $hit[1];
        } // Marco.Fehr.-.In.the.Mix.at.Der.Club-09-01-SAT-2012-XDS <TOWN><www.town.ag > <partner of www.ssl-news.info >  [01/13] - "Marco.Fehr.-.In.the.Mix.at.Der.Club-09-01-SAT-2012-XDS.par2" - 92,12 MB - yEnc
        if (preg_match(
            '/^(\w.+) <TOWN><www\.town\.ag >\s+<partner.+>\s+\[\d+\/\d+\] - ("|#34;).+("|#34;).+yEnc$/',
            $this->subject,
            $hit
        )
        ) {
            return $hit[1];
        }
        // Marco.Fehr.-.In.the.Mix.at.Der.Club-09-01-SAT-2012-XDS <TOWN><www.town.ag > <partner of www.ssl-news.info >  [01/13] - "Marco.Fehr.-.In.the.Mix.at.Der.Club-09-01-SAT-2012-XDS.par2" - 92,12 MB - yEnc
        // Some have no yEnc
        if (preg_match(
            '/^(\w.+) <TOWN><www\.town\.ag >\s+<partner.+>\s+\[\d+\/\d+\] - ("|#34;).+/',
            $this->subject,
            $hit
        )
        ) {
            return $hit[1];
        } // <TOWN><www.town.ag > <partner of www.ssl-news.info > JetBrains.IntelliJ.IDEA.v11.1.4.Ultimate.Edition.MacOSX.Incl.Keymaker-EMBRACE  [01/18] - "JetBrains.IntelliJ.IDEA.v11.1.4.Ultimate.Edition.MacOSX.Incl.Keymaker-EMBRACE.par2" - 200,77 MB - yEnc
        if (preg_match(
            '/^<TOWN><www\.town\.ag >\s+<partner .+>\s+(.+)\s+\[\d+\/\d+\] - ("|#34;).+("|#34;).+yEnc$/',
            $this->subject,
            $hit
        )
        ) {
            return $hit[1];
        }
        // <TOWN><www.town.ag > <partner of www.ssl-news.info > JetBrains.IntelliJ.IDEA.v11.1.4.Ultimate.Edition.MacOSX.Incl.Keymaker-EMBRACE  [01/18] - "JetBrains.IntelliJ.IDEA.v11.1.4.Ultimate.Edition.MacOSX.Incl.Keymaker-EMBRACE.par2" - 200,77 MB - yEnc
        // Some have no yEnc
        if (preg_match(
            '/^<TOWN><www\.town\.ag >\s+<partner .+>\s+(.+)\s+\[\d+\/\d+\] - ("|#34;).+/',
            $this->subject,
            $hit
        )
        ) {
            return $hit[1];
        } // <TOWN><www.town.ag > <partner of www.ssl-news.info > [01/18] - "2012-11.-.Supurbia.-.Volume.Tw o.Digital-1920.K6-Empire.par2" - 421,98 MB yEnc
        if (preg_match(
            '/^[ <\[]{0,2}TOWN[ >\]]{0,2}[ _-]{0,3}[ <\[]{0,2}www\.town\.ag[ >\]]{0,2}[ _-]{0,3}[ <\[]{0,2}partner of www.ssl-news\.info[ >\]]{0,2}[ _-]{0,3}\[\d+\/\d+\][ _-]{0,3}("|#34;)(.+)\.(par|vol|rar|nfo).*?("|#34;).+?yEnc$/i',
            $this->subject,
            $hit
        )
        ) {
            return $hit[2];
        } // <TOWN> www.town.ag > sponsored by www.ssl-news.info > (1/3) "HolzWerken_40.par2" - 43,89 MB - yEnc
        if (preg_match(
            '/^<TOWN> www\.town\.ag > sponsored by www\.ssl-news\.info > \(\d+\/\d+\) "([\w\säöüÄÖÜß+¤¶!.,&_()\[\]\'\`{}#-]{8,}?\b.?)'.
            $this->e0.' - \d+[,.]\d+ [mMkKgG][bB] - yEnc$/',
            $this->subject,
            $hit
        )
        ) {
            return $hit[1];
        } // (1/9)<<<www.town.ag>>> sponsored by ssl-news.info<<<[HorribleSubs]_AIURA_-_01_[480p].mkv "[HorribleSubs]_AIURA_-_01_[480p].par2" yEnc
        if (preg_match(
            '/^\(\d+\/\d+\).+?www\.town\.ag.+?sponsored by (www\.)?ssl-news\.info<+?.+? "([\w\säöüÄÖÜß+¤¶!.,&_()\[\]\'\`{}#-]{8,}?\b.?)'.
            $this->e1,
            $this->subject,
            $hit
        )
        ) {
            return $hit[2];
        } // [ TOWN ]-[ www.town.ag ]-[ Assassins.Creed.IV.Black.Flag.XBOX360-COMPLEX ]-[ partner of www.ssl-news.info ] [074/195]- "complex-ac4.bf.d1.r71" yEnc
        if (preg_match(
            '/^\[ TOWN \][ _-]{0,3}\[ www\.town\.ag \][ _-]{0,3}\[ (.+?) \][ _-]{0,3}\[ partner of www\.ssl-news\.info \][ _-]{0,3}\[\d+\/(\d+\])[ _-]{0,3}"(.+)(\.part\d*|\.rar)?(\.vol.+ \(\d+\/\d+\) "|\.[A-Za-z0-9]{2,4}")[ _-]{0,3}yEnc$/i',
            $this->subject,
            $hit
        )
        ) {
            return $hit[1];
        } // (TOWN)(www.town.ag ) (partner of www.ssl-news.info ) Twinz-Conversation-CD-FLAC-1995-CUSTODES  [01/23] - #34;Twinz-Conversation-CD-FLAC-1995-CUSTODES.par2#34; - 266,00 MB - yEnc
        if (preg_match(
            '/^\(TOWN\)\(www\.town\.ag \)[ _-]{0,3}\(partner of www\.ssl-news\.info \)[ _-]{0,3} (.+?) \[\d+\/(\d+\][ _-]{0,3}("|#34;).+?)\.(par2|rar|nfo|nzb)("|#34;)[ _-]{0,3}\d+[.,]\d+ [kKmMgG][bB][ _-]{0,3}yEnc$/',
            $this->subject,
            $hit
        )
        ) {
            return $hit[1];
        } // <TOWN><www.town.ag > <partner of www.ssl-news.info > Greek.S04E06.Katerstimmung.German.DL.Dubbed.WEB-DL.XviD-GEZ  [01/22] - "Greek.S04E06.Katerstimmung.German.DL.Dubbed.WEB-DL.XviD-GEZ.par2" - 526,99 MB - yEnc
        if (preg_match(
            '/^<TOWN><www\.town\.ag > <partner of www\.ssl-news\.info > (.+) \[\d+\/\d+\][ _-]{0,3}("|#34;).+?("|#34;).+?yEnc$/i',
            $this->subject,
            $hit
        )
        ) {
            return $hit[1];
        } // [ TOWN ]-[ www.town.ag ]-[ ANIME ] [01/17] - "[Chyuu] Nanatsu no Taizai - 12 [720p][D1F49539].par2" - 585,03 MB yEnc
        if (preg_match(
            '/^\[ TOWN \][ _-]{0,3}\[ www\.town\.ag \][ _-]{0,3}\[ .* \][ _-]{0,3}\[\d+\/\d+\][ _-]{0,3}"([\w\säöüÄÖÜß+¤¶!.,&_()\[\]\'\`{}#-]{8,}?\b.?)'.$this->e2,
            $this->subject,
            $hit
        )
        ) {
            return $hit[1];
        }

        return [
            'cleansubject' => $this->releaseCleanerHelper($this->subject),
            'properlynamed' => false,
        ];
    }

    // Run at the end because this can be dangerous. In the future it's better to make these per group. There should not be numbers after yEnc because we remove them as well before inserting (even when importing).

    public function generic(): array
    {
        // This regex gets almost all of the predb release names also keep in mind that not every subject ends with yEnc, some are truncated, because of the 255 character limit and some have extra charaters tacked onto the end, like (5/10).
        if (preg_match(
            '/^\[\d+\][\-_\s]{0,3}(\[(reup|full|repost.+?|part|re-repost|xtr|sample)(\])?[\-_\s]{0,3}\[[\- #@\.\w]+\][\-_\s]{0,3}|\[[\- #@\.\w]+\][\-_\s]{0,3}\[(reup|full|repost.+?|part|re-repost|xtr|sample)(\])?[\-_\s]{0,3}|\[.+?efnet\][\-_\s]{0,3}|\[(reup|full|repost.+?|part|re-repost|xtr|sample)(\])?[\-_\s]{0,3})(\[FULL\])?[\-_\s]{0,3}(\[ )?(\[)? ?(\/sz\/)?(F: - )?(?P<title>[\- _!@\.\'\w\(\)~]{10,}) ?(\])?[\-_\s]{0,3}(\[)? ?(REPOST|REPACK|SCENE|EXTRA PARS|REAL)? ?(\])?[\-_\s]{0,3}?(\[\d+[\-\/~]\d+\])?[\-_\s]{0,3}["|#34;]*.+["|#34;]* ?[yEnc]{0,4}/i',
            $this->subject,
            $hit
        )
        ) {
            return $hit['title'];
        }

        return [
            'cleansubject' => $this->releaseCleanerHelper($this->subject),
            'properlynamed' => false,
        ];
    }

    public function releaseCleanerHelper($subject): string
    {
        $cleanerName = preg_replace('/(\- )?yEnc$/', '', $subject);

        return trim(preg_replace('/\s\s+/', ' ', $cleanerName));
    }

    /**
     * Cleans release name for the namefixer class.
     *
     * @return mixed|string
     */
    public function fixerCleaner($name)
    {
        $cleanerName = $name;

        // Remove sample/proof/thumbs markers from the end
        $cleanerName = preg_replace('/[.\-_](sample|proof|thumbs?)$/i', '', $cleanerName);

        // Remove archive extensions from the end
        $cleanerName = preg_replace('/\.(par2?|nfo|sfv|nzb|rar|r\d{2,3}|zip)$/i', '', $cleanerName);

        // Remove part/volume markers from the end
        $cleanerName = preg_replace('/\.part\d+(\.rar)?$/i', '', $cleanerName);
        $cleanerName = preg_replace('/\.vol\d+\+\d+\.par2$/i', '', $cleanerName);
        $cleanerName = preg_replace('/\d{1,3}\.rev"?$/i', '', $cleanerName);

        // Remove "Release Name" or "sample-" from the start
        $cleanerName = preg_replace('/^(Release Name|sample[-_])/i', '', $cleanerName);

        // Collapse multiple spaces
        $cleanerName = preg_replace('/\s\s+/', ' ', $cleanerName);

        // Remove invalid characters
        return trim(mb_convert_encoding(preg_replace('/[^(\x20-\x7F)]*/', '', $cleanerName), 'UTF-8', mb_list_encodings()));
    }
}
