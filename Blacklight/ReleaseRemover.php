<?php

namespace Blacklight;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Handles removing of various unwanted releases.
 *
 *
 * Class ReleaseRemover
 */
class ReleaseRemover
{
    protected string $blacklistID;

    protected ColorCLI $colorCLI;

    protected string $crapTime = '';

    protected bool $delete;

    protected int $deletedCount = 0;

    protected bool $echoCLI;

    /**
     * If an error occurred, store it here.
     */
    protected string $error;

    /**
     * Ignore user check?
     */
    protected bool $ignoreUserCheck;

    protected string $method = '';

    /**
     * The query we will use to select unwanted releases.
     */
    protected string $query;

    /**
     * @var \Blacklight\Releases
     */
    protected Releases $releases;

    /**
     * Result of the select query.
     */
    protected array $result;

    /**
     * @var \Blacklight\NZB
     */
    private NZB $nzb;

    /**
     * @var \Blacklight\ReleaseImage
     */
    private ReleaseImage $releaseImage;

    /**
     * @var \Blacklight\ColorCLI
     */
    protected ColorCLI $colorCli;

    public function __construct()
    {
        $this->colorCLI = new ColorCLI();
        $this->releases = new Releases();
        $this->nzb = new NZB();
        $this->releaseImage = new ReleaseImage();

        $this->query = '';
        $this->error = '';
        $this->ignoreUserCheck = false;
        $this->echoCLI = config('nntmux.echocli');
    }

    /**
     * Remove releases using user criteria.
     *
     * @param  array  $arguments  Array of criteria used to delete unwanted releases.
     *                            Criteria must look like this : columnName=modifier="content"
     *                            columnName is a column name from the releases table.
     *                            modifiers are : equals,like,bigger,smaller
     *                            content is what to change the column content to
     * @return string|bool
     *
     * @throws \Exception
     */
    public function removeByCriteria(array $arguments)
    {
        $this->delete = true;
        $this->ignoreUserCheck = false;
        // Time we started.
        $timeStart = now();

        // Start forming the query.
        $this->query = 'SELECT id, guid, searchname FROM releases WHERE 1=1';

        // Keep forming the query based on the user's criteria, return if any errors.
        foreach ($arguments as $arg) {
            $this->error = '';
            $string = $this->formatCriteriaQuery($arg);
            if ($string === false) {
                return $this->returnError();
            }
            $this->query .= $string;
        }
        $this->query = $this->cleanSpaces($this->query);

        // Check if the user wants to run the query.
        if ($this->checkUserResponse() === false) {
            return false;
        }

        // Check if the query returns results.
        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        $this->method = 'userCriteria';

        $this->deletedCount = 0;
        // Delete the releases.
        $this->deleteReleases();

        if ($this->echoCLI) {
            $this->colorCLI->headerOver(($this->delete ? 'Deleted ' : 'Would have deleted ').$this->deletedCount.' release(s). This script ran for ');
            $this->colorCLI->header(now()->diffInSeconds($timeStart).' seconds', true);
        }

        return true;
    }

    /**
     * Delete crap releases.
     *
     * @param  bool  $delete  Delete the release or just show the result?
     * @param  int|string  $time  Time in hours (to select old releases) or 'full' for no time limit.
     * @param  string  $type  Type of query to run [blacklist, executable, gibberish, hashed, installbin, passworded,
     *                        passwordurl, sample, scr, short, size, ''] ('' runs against all types)
     * @param  string|int  $blacklistID
     * @return string|bool
     *
     * @throws \Exception
     */
    public function removeCrap(bool $delete, $time, string $type = '', $blacklistID = '')
    {
        $timeStart = now();
        $this->delete = $delete;
        $this->blacklistID = '';

        if ($blacklistID !== '' && is_numeric($blacklistID)) {
            $this->blacklistID = sprintf('AND id = %d', $blacklistID);
        }

        $time = trim($time);
        $this->crapTime = '';
        $type = strtolower(trim($type));

        if ($time === 'full') {
            if ($this->echoCLI) {
                $this->colorCLI->header('Removing '.($type === '' ? 'All crap releases ' : $type.' crap releases').' - no time limit.', true);
            }
        } else {
            if (! is_numeric($time)) {
                $this->error = 'Error, time must be a number or full.';

                return $this->returnError();
            }
            if ($this->echoCLI) {
                $this->colorCLI->header('Removing '.($type === '' ? 'All crap releases ' : $type.' crap releases').' from the past '.$time.' hour(s).', true);
            }
            $this->crapTime = ' AND r.adddate > (NOW() - INTERVAL '.$time.' HOUR)';
        }

        $this->deletedCount = 0;
        switch ($type) {
            case 'blacklist':
                $this->removeBlacklist();
                break;
            case 'blfiles':
                $this->removeBlacklistFiles();
                break;
            case 'executable':
                $this->removeExecutable();
                break;
            case 'gibberish':
                $this->removeGibberish();
                break;
            case 'hashed':
                $this->removeHashed();
                break;
            case 'installbin':
                $this->removeInstallBin();
                break;
            case 'passworded':
                $this->removePassworded();
                break;
            case 'passwordurl':
                $this->removePasswordURL();
                break;
            case 'sample':
                $this->removeSample();
                break;
            case 'scr':
                $this->removeSCR();
                break;
            case 'short':
                $this->removeShort();
                break;
            case 'size':
                $this->removeSize();
                break;
            case 'huge':
                $this->removeHuge();
                break;
            case 'nzb':
                $this->removeSingleNZB();
                break;
            case 'codec':
                $this->removeCodecPoster();
                break;
            case 'wmv_all':
                $this->removeWMV();
                break;
            case '':
                $this->removeBlacklist();
                $this->removeBlacklistFiles();
                $this->removeExecutable();
                $this->removeGibberish();
                $this->removeHashed();
                $this->removeInstallBin();
                $this->removePassworded();
                $this->removeSample();
                $this->removeSCR();
                $this->removeShort();
                $this->removeSize();
                $this->removeHuge();
                $this->removeSingleNZB();
                $this->removeCodecPoster();
                break;
            default:
                $this->error = 'Wrong type: '.$type;

                return $this->returnError();
        }

        if ($this->echoCLI) {
            $this->colorCLI->headerOver(($this->delete ? 'Deleted ' : 'Would have deleted ').$this->deletedCount.' release(s). This script ran for ');
            $this->colorCLI->header(now()->diffInSeconds($timeStart).' seconds', true);
        }

        return true;
    }

    /**
     * Remove releases with 15 or more letters or numbers, nothing else.
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    protected function removeGibberish()
    {
        $this->method = 'Gibberish';
        $this->query = sprintf(
            "SELECT r.guid, r.searchname, r.id
			FROM releases r
			WHERE r.nfostatus = 0
			AND r.iscategorized = 1
			AND r.rarinnerfilecount = 0
			AND r.categories_id NOT IN (%d)
			AND r.searchname REGEXP '^[a-zA-Z0-9]{15,}$'
			%s",
            Category::OTHER_HASHED,
            $this->crapTime
        );

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases with 25 or more letters/numbers, probably hashed.
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    protected function removeHashed()
    {
        $this->method = 'Hashed';
        $this->query = sprintf(
            "SELECT r.guid, r.searchname, r.id
			FROM releases r
			WHERE r.nfostatus = 0
			AND r.iscategorized = 1
			AND r.rarinnerfilecount = 0
			AND r.categories_id NOT IN (%d, %d)
			AND r.searchname REGEXP '[a-zA-Z0-9]{25,}'
			%s",
            Category::OTHER_MISC,
            Category::OTHER_HASHED,
            $this->crapTime
        );

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases with 5 or less letters/numbers.
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    protected function removeShort()
    {
        $this->method = 'Short';
        $this->query = sprintf(
            "SELECT r.guid, r.searchname, r.id
			FROM releases r
			WHERE r.nfostatus = 0
			AND r.iscategorized = 1
			AND r.rarinnerfilecount = 0
			AND r.categories_id NOT IN (%d)
			AND r.searchname REGEXP '^[a-zA-Z0-9]{0,5}$'
			%s",
            Category::OTHER_MISC,
            $this->crapTime
        );

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases with an exe file not in other misc or pc apps/games.
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    protected function removeExecutable()
    {
        $this->method = 'Executable';

        $this->query = sprintf(
            'SELECT r.guid, r.searchname, r.id
			FROM releases r
			JOIN release_files rf ON r.id = rf.releases_id
			WHERE rf.name LIKE %s
			AND r.categories_id NOT IN (%d, %d, %d, %d) %s',
            escapeString('%.exe'),
            Category::PC_0DAY,
            Category::PC_GAMES,
            Category::OTHER_MISC,
            Category::OTHER_HASHED,
            $this->crapTime
        );

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases with an install.bin file.
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    protected function removeInstallBin()
    {
        $this->method = 'Install.bin';

        $this->query = sprintf(
            'SELECT r.guid, r.searchname, r.id
			FROM releases r
			JOIN release_files rf ON r.id = rf.releases_id
			WHERE rf.name LIKE %s %s',
            escapeString('%install.bin%'),
            $this->crapTime
        );

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases with an password.url file.
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    protected function removePasswordURL()
    {
        $this->method = 'Password.url';

        $this->query = sprintf(
            'SELECT r.guid, r.searchname, r.id
			FROM releases r
			JOIN release_files rf ON r.id = rf.releases_id
			WHERE rf.name LIKE %s %s',
            escapeString('%password.url%'),
            $this->crapTime
        );

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases with password in the search name.
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    protected function removePassworded()
    {
        $this->method = 'Passworded';

        $this->query = sprintf(
            'SELECT r.guid, r.searchname, r.id
			FROM releases r
			WHERE r.searchname LIKE %s
			AND r.searchname NOT LIKE %s
			AND r.searchname NOT LIKE %s
			AND r.searchname NOT LIKE %s
			AND r.searchname NOT LIKE %s
			AND r.searchname NOT LIKE %s
			AND r.searchname NOT LIKE %s
			AND r.nzbstatus = 1
			AND r.categories_id NOT IN (%d, %d, %d, %d, %d, %d, %d, %d, %d) %s',
            // Matches passwort / passworded / etc also.
            escapeString('%passwor%'),
            escapeString('%advanced%'),
            escapeString('%no password%'),
            escapeString('%not password%'),
            escapeString('%recovery%'),
            escapeString('%reset%'),
            escapeString('%unlocker%'),
            Category::PC_GAMES,
            Category::PC_0DAY,
            Category::PC_ISO,
            Category::PC_MAC,
            Category::PC_PHONE_ANDROID,
            Category::PC_PHONE_IOS,
            Category::PC_PHONE_OTHER,
            Category::OTHER_MISC,
            Category::OTHER_HASHED,
            $this->crapTime
        );

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases smaller than 2MB with 1 part not in MP3/books/misc section.
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    protected function removeSize()
    {
        $this->method = 'Size';
        $this->query = sprintf(
            'SELECT r.guid, r.searchname, r.id
			FROM releases r
			WHERE r.totalpart = 1
			AND r.size < 2097152
			AND r.categories_id NOT IN (%d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d) %s',
            Category::MUSIC_MP3,
            Category::BOOKS_COMICS,
            Category::BOOKS_EBOOK,
            Category::BOOKS_FOREIGN,
            Category::BOOKS_MAGAZINES,
            Category::BOOKS_TECHNICAL,
            Category::BOOKS_UNKNOWN,
            Category::PC_0DAY,
            Category::PC_GAMES,
            Category::OTHER_MISC,
            Category::OTHER_HASHED,
            $this->crapTime
        );

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases bigger than 200MB with just a single file.
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    protected function removeHuge()
    {
        $this->method = 'Huge';
        $this->query = sprintf(
            'SELECT r.guid, r.searchname, r.id
			FROM releases r
			WHERE r.totalpart = 1
			AND r.size > 209715200 %s',
            $this->crapTime
        );

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases that are just a single nzb file.
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    protected function removeSingleNZB()
    {
        $this->method = '.nzb';
        $this->query = sprintf(
            'SELECT r.guid, r.searchname, r.id
			FROM releases r
			JOIN release_files rf ON r.id = rf.releases_id
			WHERE r.totalpart = 1
			AND rf.name LIKE %s %s',
            escapeString('%.nzb%'),
            $this->crapTime
        );

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases with more than 1 part, less than 40MB, sample in name. TV/Movie sections.
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    protected function removeSample()
    {
        $this->method = 'Sample';

        $this->query = sprintf(
            'SELECT r.guid, r.searchname, r.id
			FROM releases r
			WHERE r.totalpart > 1
			AND r.size < 40000000
			AND r.name LIKE %s
			AND r.categories_id IN (%d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d) %s',
            escapeString('%sample%'),
            Category::TV_ANIME,
            Category::TV_DOCU,
            Category::TV_FOREIGN,
            Category::TV_HD,
            Category::TV_OTHER,
            Category::TV_SD,
            Category::TV_SPORT,
            Category::TV_WEBDL,
            Category::MOVIE_3D,
            Category::MOVIE_BLURAY,
            Category::MOVIE_DVD,
            Category::MOVIE_FOREIGN,
            Category::MOVIE_HD,
            Category::MOVIE_OTHER,
            Category::MOVIE_SD,
            $this->crapTime
        );

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases with a scr file in the filename/subject.
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    protected function removeSCR()
    {
        $this->method = '.scr';

        $this->query = sprintf(
            "SELECT r.guid, r.searchname, r.id
			FROM releases r
			JOIN release_files rf ON r.id = rf.releases_id
			WHERE (rf.name REGEXP '[.]scr[$ \"]' OR r.name REGEXP '[.]scr[$ \"]')
			%s",
            $this->crapTime
        );

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases using the site blacklist regexes.
     *
     *
     * @throws \Exception
     */
    protected function removeBlacklist(): bool
    {
        $status = sprintf('AND status = %d', Binaries::BLACKLIST_ENABLED);

        if (! empty($this->blacklistID) && $this->delete === false) {
            $status = '';
        }

        $regexList = DB::select(
            sprintf(
                'SELECT regex, id, groupname, msgcol
				FROM binaryblacklist
				WHERE optype = %d
				AND msgcol IN (%d, %d) %s %s
				ORDER BY id ASC',
                Binaries::OPTYPE_BLACKLIST,
                Binaries::BLACKLIST_FIELD_SUBJECT,
                Binaries::BLACKLIST_FIELD_FROM,
                $this->blacklistID,
                $status
            )
        );

        if (\count($regexList) > 0) {
            foreach ($regexList as $regex) {
                $regexSQL = $ftMatch = $regexMatch = $opTypeName = '';
                $dbRegex = escapeString($regex->regex);

                if ($this->crapTime === '') {
                    $regexMatch = $this->extractSrchFromRegx($dbRegex);
                    if ($regexMatch !== '') {
                        $ftMatch = sprintf('rse.query = "@(name,searchname) %s;limit=1000000;maxmatches=1000000;mode=any" AND', str_replace('|', ' ', str_replace('"', '', $regexMatch)));
                    }
                }

                switch ((int) $regex->msgcol) {
                    case Binaries::BLACKLIST_FIELD_SUBJECT:
                        $regexSQL = sprintf('WHERE %s (r.name REGEXP %s OR r.searchname REGEXP %2$s)', $ftMatch, $dbRegex);
                        $opTypeName = 'Subject';
                        break;
                    case Binaries::BLACKLIST_FIELD_FROM:
                        $regexSQL = 'WHERE r.fromname REGEXP '.$dbRegex;
                        $opTypeName = 'Poster';
                        break;
                }

                if ($regexSQL === '') {
                    continue;
                }

                // Get the group ID if the regex is set to work against a group.
                $groupID = '';
                if (strtolower($regex->groupname) !== 'alt.binaries.*') {
                    $groupIDs = DB::select(
                        'SELECT id FROM usenet_groups WHERE name REGEXP '.
                        escapeString($regex->groupname)
                    );

                    $groupIDCount = \count($groupIDs);
                    if ($groupIDCount === 0) {
                        continue;
                    }

                    if ($groupIDCount === 1) {
                        $groupIDs = $groupIDs[0]->id;
                    } else {
                        $string = '';
                        foreach ($groupIDs as $ID) {
                            $string .= $ID->id.',';
                        }
                        $groupIDs = substr($string, 0, -1);
                    }

                    $groupID = ' AND r.groups_id in ('.$groupIDs.') ';
                }
                $this->method = 'Blacklist ['.$regex->id.']';

                // Check if using FT Match and declare for echo
                if ($ftMatch !== '' && $opTypeName === 'Subject') {
                    $blType = 'FULLTEXT match with REGEXP';
                    $ftUsing = 'Using ('.$regexMatch.') as interesting words.'.PHP_EOL;
                } else {
                    $blType = 'only REGEXP';
                    $ftUsing = PHP_EOL;
                }

                // Provide useful output of operations
                $this->colorCLI->header(
                    sprintf(
                        'Finding crap releases for %s: Using %s method against release %s.'.
                            '%s',
                        $this->method,
                        $blType,
                        $opTypeName,
                        $ftUsing
                    ),
                    true
                );

                if ($opTypeName === 'Subject') {
                    $join = 'INNER JOIN releases_se rse ON rse.id = r.id';
                } else {
                    $join = '';
                }

                $this->query = sprintf(
                    '
							SELECT r.guid, r.searchname, r.id
							FROM releases r %s %s %s %s',
                    $join,
                    $regexSQL,
                    $groupID,
                    $this->crapTime
                );

                if ($this->checkSelectQuery() === false) {
                    continue;
                }
                $this->deleteReleases();
            }
        } else {
            $this->colorCLI->error("No regular expressions were selected for blacklist removal. Make sure you have activated REGEXPs in Site Edit and you're specifying a valid ID.", true);
        }

        return true;
    }

    /**
     * Remove releases using the site blacklist regexes against file names.
     *
     *
     * @throws \Exception
     */
    protected function removeBlacklistFiles(): bool
    {
        $allRegex = DB::select(
            sprintf(
                'SELECT regex, id, groupname
				FROM binaryblacklist
				WHERE status = %d
				AND optype = %d
				AND msgcol = %d
				ORDER BY id ASC',
                Binaries::BLACKLIST_ENABLED,
                Binaries::OPTYPE_BLACKLIST,
                Binaries::BLACKLIST_FIELD_SUBJECT
            )
        );

        if (\count($allRegex) > 0) {
            foreach ($allRegex as $regex) {
                $regexSQL = sprintf(
                    'JOIN release_files rf ON r.id = rf.releases_id WHERE rf.name REGEXP %s',
                    escapeString($regex->regex)
                );

                if ($regexSQL === '') {
                    continue;
                }

                // Get the group ID if the regex is set to work against a group.
                $groupID = '';
                if (strtolower($regex->groupname) !== 'alt.binaries.*') {
                    $groupIDs = DB::select(
                        'SELECT id FROM usenet_groups WHERE name REGEXP '.
                        escapeString($regex->groupname)
                    );
                    $groupIDCount = \count($groupIDs);
                    if ($groupIDCount === 0) {
                        continue;
                    }

                    if ($groupIDCount === 1) {
                        $groupIDs = $groupIDs[0]->id;
                    } else {
                        $string = '';
                        foreach ($groupIDs as $fID) {
                            $string .= $fID->id.',';
                        }
                        $groupIDs = substr($string, 0, -1);
                    }

                    $groupID = ' AND r.groups_id in ('.$groupIDs.') ';
                }

                $this->method = 'Blacklist Files '.$regex->id;

                $blType = 'only REGEXP';
                $ftUsing = PHP_EOL;

                // Provide useful output of operations
                $this->colorCLI->header(
                    sprintf(
                        'Finding crap releases for %s: Using %s method against release filenames.'.PHP_EOL.
                            '%s',
                        $this->method,
                        $blType,
                        $ftUsing
                    ),
                    true
                );

                $this->query = sprintf(
                    'SELECT DISTINCT r.id, r.guid, r.searchname
					FROM releases r %s %s %s',
                    $regexSQL,
                    $groupID,
                    $this->crapTime
                );

                if ($this->checkSelectQuery() === false) {
                    continue;
                }

                $this->deleteReleases();
            }
        }

        return true;
    }

    /**
     * Remove releases that contain .wmv file, aka that spam poster.
     * Thanks to dizant from nZEDb forums for the sql query.
     *
     * @return string|bool
     *
     * @throws \Exception
     */
    protected function removeWMV()
    {
        $this->method = 'WMV_ALL';
        $this->query = "
			SELECT r.guid, r.searchname
			FROM releases r
			LEFT JOIN release_files rf ON (r.id = rf.releases_id)
			WHERE r.categories_id BETWEEN ' . Category::TV_ROOT . ' AND ' . Category::TV_OTHER . '
			AND rf.name REGEXP 'x264.*\.wmv$'
			GROUP BY r.id";

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases that contain .wmv files and Codec\Setup.exe files, aka that spam poster.
     * Thanks to dizant from nZEDb forums for parts of the sql query.
     *
     * @return string|bool
     *
     * @throws \Exception
     */
    protected function removeCodecPoster()
    {
        $categories = sprintf(
            'r.categories_id IN (%d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d)',
            Category::MOVIE_3D,
            Category::MOVIE_BLURAY,
            Category::MOVIE_DVD,
            Category::MOVIE_FOREIGN,
            Category::MOVIE_HD,
            Category::MOVIE_OTHER,
            Category::MOVIE_SD,
            Category::XXX_WMV,
            Category::XXX_X264,
            Category::XXX_XVID,
            Category::XXX_OTHER
        );

        $regex =
            '\.*((DVDrip|BRRip)[. ].*[. ](R[56]|HQ)|720p[ .](DVDrip|HQ)|Webrip.*[. ](R[56]|Xvid|AC3|US)'.
            '|720p.*[. ]WEB-DL[. ]Xvid[. ]AC3[. ]US|HDRip.*[. ]Xvid[. ]DD5).*[. ]avi$';

        $this->query = "
			SELECT r.guid, r.searchname, r.id
			FROM releases r
			LEFT JOIN release_files rf ON r.id = rf.releases_id
			WHERE {$categories}
			AND (r.imdbid NOT IN ('0000000', 0) OR xxxinfo_id > 0)
			AND r.nfostatus = 1
			AND r.haspreview = 0
			AND r.jpgstatus = 0
			AND r.predb_id = 0
			AND r.videostatus = 0
			AND
			(
				rf.name REGEXP 'XviD-[a-z]{3}\\.(avi|mkv|wmv)$'
				OR rf.name REGEXP 'x264.*\\.(wmv|avi)$'
				OR rf.name REGEXP '{$regex}'
				OR rf.name LIKE '%\\Codec%Setup.exe%'
				OR rf.name LIKE '%\\Codec%Installer.exe%'
				OR rf.name LIKE '%\\Codec.exe%'
				OR rf.name LIKE '%If_you_get_error.txt%'
				OR rf.name LIKE '%read me if the movie not playing.txt%'
				OR rf.name LIKE '%Lisez moi si le film ne demarre pas.txt%'
				OR rf.name LIKE '%lees me als de film niet spelen.txt%'
				OR rf.name LIKE '%Lesen Sie mir wenn der Film nicht abgespielt.txt%'
				OR rf.name LIKE '%Lesen Sie mir, wenn der Film nicht starten.txt%'
			)
			GROUP BY r.id {$this->crapTime}";

        if ($this->checkSelectQuery() === false) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Delete releases from the database.
     *
     *
     * @throws \Exception
     */
    protected function deleteReleases(): bool
    {
        $deletedCount = 0;
        foreach ($this->result as $release) {
            if ($this->delete) {
                $this->releases->deleteSingle(['g' => $release->guid, 'i' => $release->id], $this->nzb, $this->releaseImage);
                if ($this->echoCLI) {
                    $this->colorCLI->primary('Deleting: '.$this->method.': '.$release->searchname, true);
                }
            } elseif ($this->echoCLI) {
                $this->colorCLI->primary('Would be deleting: '.$this->method.': '.$release->searchname, true);
            }
            $deletedCount++;
        }

        $this->deletedCount += $deletedCount;

        return true;
    }

    /**
     * Verify if the query has any results.
     *
     * @return bool False on failure, true on success after setting a count of found releases.
     */
    protected function checkSelectQuery(): bool
    {
        // Run the query, check if it picked up anything.
        $result = DB::select($this->cleanSpaces($this->query));
        if (\count($result) <= 0) {
            $this->error = '';
            if ($this->method === 'userCriteria') {
                $this->error = 'No releases were found to delete, try changing your criteria.';
            }

            return false;
        }
        $this->result = $result;

        return true;
    }

    /**
     * Go through user arguments and format part of the query.
     *
     * @param  string  $argument  User argument.
     * @return string|false
     */
    protected function formatCriteriaQuery(string $argument)
    {
        // Check if the user wants to ignore the check.
        if ($argument === 'ignore') {
            $this->ignoreUserCheck = true;

            return '';
        }

        $this->error = 'Invalid argument supplied: '.$argument.PHP_EOL;
        $args = explode('=', $argument);
        if (\count($args) === 3) {
            $args[0] = $this->cleanSpaces($args[0]);
            $args[1] = $this->cleanSpaces($args[1]);
            $args[2] = $this->cleanSpaces($args[2]);
            switch ($args[0]) {
                case 'categories_id':
                    if ($args[1] === 'equals') {
                        return ' AND categories_id = '.$args[2];
                    }
                    break;
                case 'imdbid':
                    if ($args[1] === 'equals') {
                        if ($args[2] === 'NULL') {
                            return ' AND imdbid IS NULL ';
                        }

                        return ' AND imdbid = '.$args[2];
                    }
                    break;
                case 'nzbstatus':
                    if ($args[1] === 'equals') {
                        return ' AND nzbstatus = '.$args[2];
                    }
                    break;
                case 'videos_id':
                    if ($args[1] === 'equals') {
                        return ' AND videos_id = '.$args[2];
                    }
                    break;
                case 'totalpart':
                    switch ($args[1]) {
                        case 'equals':
                            return ' AND totalpart = '.$args[2];
                        case 'bigger':
                            return ' AND totalpart > '.$args[2];
                        case 'smaller':
                            return ' AND totalpart < '.$args[2];
                        default:
                            break;
                    }
                    break;
                case 'fromname':
                    switch ($args[1]) {
                        case 'equals':
                            return ' AND fromname = '.escapeString($args[2]);
                        case 'like':
                            return ' AND fromname '.$this->formatLike($args[2], 'fromname');
                    }
                    break;
                case 'groupname':
                    switch ($args[1]) {
                        case 'equals':
                            $group = DB::select('SELECT id FROM usenet_groups WHERE name = '.escapeString($args[2]));
                            if (empty($group)) {
                                $this->error = 'This group was not found in your database: '.$args[2].PHP_EOL;
                                break;
                            }

                            return ' AND groups_id = '.$group[0]->id;
                        case 'like':
                            $groups = DB::select('SELECT id FROM usenet_groups WHERE name '.$this->formatLike($args[2], 'name'));
                            if (\count($groups) === 0) {
                                $this->error = 'No groups were found with this pattern in your database: '.$args[2].PHP_EOL;
                                break;
                            }
                            $gQuery = ' AND groups_id IN (';
                            foreach ($groups as $group) {
                                $gQuery .= $group->id.',';
                            }
                            $gQuery = substr($gQuery, 0, -0).')';

                            return $gQuery;
                        default:
                            break;
                    }
                    break;
                case 'guid':
                    if ($args[1] === 'equals') {
                        return ' AND guid = '.escapeString($args[2]);
                    }
                    break;
                case 'name':
                    switch ($args[1]) {
                        case 'equals':
                            return ' AND name = '.escapeString($args[2]);
                        case 'like':
                            return ' AND name '.$this->formatLike($args[2], 'name');
                        default:
                            break;
                    }
                    break;
                case 'searchname':
                    switch ($args[1]) {
                        case 'equals':
                            return ' AND searchname = '.escapeString($args[2]);
                        case 'like':
                            return ' AND searchname '.$this->formatLike($args[2], 'searchname');
                        default:
                            break;
                    }
                    break;
                case 'size':
                    if (! is_numeric($args[2])) {
                        break;
                    }
                    switch ($args[1]) {
                        case 'equals':
                            return ' AND size = '.$args[2];
                        case 'bigger':
                            return ' AND size > '.$args[2];
                        case 'smaller':
                            return ' AND size < '.$args[2];
                        default:
                            break;
                    }
                    break;
                case 'adddate':
                    if (! is_numeric($args[2])) {
                        break;
                    }
                    switch ($args[1]) {
                        case 'bigger':
                            return ' AND adddate <  NOW() - INTERVAL '.$args[2].' HOUR';
                        case 'smaller':
                            return ' AND adddate >  NOW() - INTERVAL '.$args[2].' HOUR';
                        default:
                            break;
                    }
                    break;
                case 'postdate':
                    if (! is_numeric($args[2])) {
                        break;
                    }
                    switch ($args[1]) {
                        case 'bigger':
                            return ' AND postdate <  NOW() - INTERVAL '.$args[2].' HOUR';
                        case 'smaller':
                            return ' AND postdate >  NOW() - INTERVAL '.$args[2].' HOUR';
                        default:
                            break;
                    }
                    break;
                case 'completion':
                    if (! is_numeric($args[2])) {
                        break;
                    }
                    if ($args[1] === 'smaller') {
                        return ' AND completion > 0 AND completion < '.$args[2];
                    }
            }
        }

        return false;
    }

    /**
     * Check if the user wants to run the current query.
     */
    protected function checkUserResponse(): bool
    {
        if ($this->ignoreUserCheck) {
            return true;
        }

        // Print the query to the user, ask them if they want to continue using it.
        $this->colorCLI->primary(
            'This is the query we have formatted using your criteria, you can run it in SQL to see if you like the results:'.
            PHP_EOL.$this->query.';'.PHP_EOL.
            'If you are satisfied, type yes and press enter. Anything else will exit.',
            true
        );

        // Check the users response.
        $userInput = trim(fgets(fopen('php://stdin', 'rtb')));
        if ($userInput !== 'yes') {
            $this->colorCLI->primary('You typed: "'.$userInput.'", the program will exit.', true);

            return false;
        }

        return true;
    }

    /**
     * Remove multiple spaces and trim leading spaces.
     */
    protected function cleanSpaces(string $string): string
    {
        return trim(preg_replace('/\s{2,}/', ' ', $string));
    }

    /**
     * Format a "like" string. ie: "name LIKE '%test%' AND name LIKE '%123%'.
     *
     * @param  string  $string  The string to format.
     * @param  string  $type  The column name.
     */
    protected function formatLike(string $string, string $type): string
    {
        $newString = explode(' ', $string);
        if (\count($newString) > 1) {
            $string = implode("%' AND {$type} LIKE '%", array_unique($newString));
        }

        return " LIKE '%".$string."%' ";
    }

    /**
     * Echo the error and return false if on CLI.
     * Return the error if on browser.
     *
     * @return bool|string
     */
    protected function returnError()
    {
        if ($this->echoCLI && $this->error !== '') {
            $this->colorCLI->error($this->error, true);
        }

        return false;
    }

    /**
     * @return array|string|string[]
     */
    protected function extractSrchFromRegx(string $dbRegex = '')
    {
        $regexMatch = '';

        // Match Regex beginning for long running foreign search
        if (substr($dbRegex, 2, 17) === 'brazilian|chinese') {
            // Find first brazilian instance position in Regex, then find first closing parenthesis.
            // Then substitute all pipes (|) with spaces for FT search and insert into query
            $forBegin = strpos($dbRegex, 'brazilian');
            $regexMatch =
                substr(
                    $dbRegex,
                    $forBegin,
                    strpos($dbRegex, ')') - $forBegin
                );
        } elseif (substr($dbRegex, 7, 11) === 'bl|cz|de|es') {
            // Find first bl|cz instance position in Regex, then find first closing parenthesis.
            $forBegin = strpos($dbRegex, 'bl|cz');
            $regexMatch = '"'.
                str_replace(
                    '|',
                    '" "',
                    substr($dbRegex, $forBegin, strpos($dbRegex, ')') - $forBegin)
                ).'"';
        } elseif (substr($dbRegex, 8, 5) === '19|20') {
            // Find first bl|cz instance position in Regex, then find last closing parenthesis as this is reversed.
            $forBegin = strpos($dbRegex, 'bl|cz');
            $regexMatch = '"'.
                str_replace(
                    '|',
                    '" "',
                    substr($dbRegex, $forBegin, strrpos($dbRegex, ')') - $forBegin)
                ).'"';
        } elseif (substr($dbRegex, 7, 14) === 'chinese.subbed') {
            // Find first brazilian instance position in Regex, then find first closing parenthesis.
            $forBegin = strpos($dbRegex, 'chinese');
            $regexMatch =
                str_replace(
                    ['-', '(', ')', '.', '?', 'nl  subed|bed|s'],
                    [
                        '',
                        '',
                        '',
                        ' ', '', 'nlsubs|nlsubbed|nlsubed', ],
                    substr(
                        $dbRegex,
                        $forBegin,
                        strrpos($dbRegex, ')') - $forBegin
                    )
                );
        } elseif (substr($dbRegex, 8, 2) === '4u') {
            // Find first 4u\.nl instance position in Regex, then find first closing parenthesis.
            $forBegin = strpos($dbRegex, '4u');
            $regexMatch =
                str_replace(
                    ['4u.nl', 'nov[ a]+rip'],
                    ['"4u" "nl"', 'nova'],
                    substr($dbRegex, $forBegin, strpos($dbRegex, ')') - $forBegin)
                );
        } elseif (substr($dbRegex, 8, 5) === 'bd|dl') {
            // Find first bd|dl instance position in Regex, then find last closing parenthesis as this is reversed.
            $forBegin = strpos($dbRegex, 'bd|dl');
            $regexMatch =
                str_replace(
                    ['bd|dl)mux', '\\', ']', '['],
                    ['bdmux|dlmux', '', '', ''],
                    substr(
                        $dbRegex,
                        $forBegin,
                        strrpos($dbRegex, ')') - $forBegin
                    )
                );
        } elseif (substr($dbRegex, 7, 9) === 'imageset|') {
            // Find first imageset| instance position in Regex, then find last closing parenthesis.
            $forBegin = strpos($dbRegex, 'imageset');
            $regexMatch = substr($dbRegex, $forBegin, strpos($dbRegex, ')') - $forBegin);
        } elseif (substr($dbRegex, 1, 9) === 'hdnectar|') {
            // Find first hdnectar| instance position in Regex.
            $regexMatch = str_replace('\'', '', $dbRegex);
        } elseif (substr($dbRegex, 1, 10) === 'Passworded') {
            // Find first Passworded instance position esin Regex, then find last closing parenthesis.
            $regexMatch = str_replace('\'', '', $dbRegex);
        }

        return $regexMatch;
    }
}
