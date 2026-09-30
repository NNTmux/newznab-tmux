<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BlacklistConstants;
use App\Models\Category;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\Nzb\NzbService;
use App\Services\Releases\ReleaseManagementService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles removing of various unwanted releases.
 */
class ReleaseRemoverService
{
    private const int BATCH_SIZE = 500;

    private const int BATCH_PAUSE_US = 10000;

    // Crap removal types
    private const string TYPE_BLACKLIST = 'blacklist';

    private const string TYPE_BLFILES = 'blfiles';

    private const string TYPE_EXECUTABLE = 'executable';

    private const string TYPE_GIBBERISH = 'gibberish';

    private const string TYPE_HASHED = 'hashed';

    private const string TYPE_INSTALLBIN = 'installbin';

    private const string TYPE_PASSWORDED = 'passworded';

    private const string TYPE_PASSWORDURL = 'passwordurl';

    private const string TYPE_SAMPLE = 'sample';

    private const string TYPE_SCR = 'scr';

    private const string TYPE_SHORT = 'short';

    private const string TYPE_SIZE = 'size';

    private const string TYPE_NZB = 'nzb';

    private const string TYPE_CODEC = 'codec';

    private const string TYPE_WMV_ALL = 'wmv_all';

    private const string TYPE_PAR2ONLY = 'par2only';

    protected string $blacklistID = '';

    protected string $crapTime = '';

    protected bool $delete = false;

    protected int $deletedCount = 0;

    protected bool $echoCLI;

    protected string $error = '';

    protected bool $ignoreUserCheck = false;

    protected string $method = '';

    protected string $query = '';

    protected ReleaseManagementService $releaseManagement;

    private NzbService $nzb;

    private ReleaseImageService $releaseImage;

    /**
     * Map of removal types to their handler methods.
     *
     * @var array<string, mixed>
     */
    private array $removalHandlers;

    public function __construct(
        ?ReleaseManagementService $releaseManagement = null,
        ?NzbService $nzb = null,
        ?ReleaseImageService $releaseImage = null
    ) {
        $this->releaseManagement = $releaseManagement ?? app(ReleaseManagementService::class);
        $this->nzb = $nzb ?? app(NzbService::class);
        $this->releaseImage = $releaseImage ?? new ReleaseImageService;
        $this->echoCLI = config('nntmux.echocli');

        $this->initRemovalHandlers();
    }

    /**
     * Initialise the removal handlers map.
     */
    private function initRemovalHandlers(): void
    {
        $this->removalHandlers = [
            self::TYPE_BLACKLIST => fn () => $this->removeBlacklist(),
            self::TYPE_BLFILES => fn () => $this->removeBlacklistFiles(),
            self::TYPE_EXECUTABLE => fn () => $this->removeExecutable(),
            self::TYPE_GIBBERISH => fn () => $this->removeGibberish(),
            self::TYPE_HASHED => fn () => $this->removeHashed(),
            self::TYPE_INSTALLBIN => fn () => $this->removeInstallBin(),
            self::TYPE_PASSWORDED => fn () => $this->removePassworded(),
            self::TYPE_PASSWORDURL => fn () => $this->removePasswordURL(),
            self::TYPE_SAMPLE => fn () => $this->removeSample(),
            self::TYPE_SCR => fn () => $this->removeSCR(),
            self::TYPE_SHORT => fn () => $this->removeShort(),
            self::TYPE_SIZE => fn () => $this->removeSize(),
            self::TYPE_NZB => fn () => $this->removeSingleNZB(),
            self::TYPE_CODEC => fn () => $this->removeCodecPoster(),
            self::TYPE_WMV_ALL => fn () => $this->removeWMV(),
            self::TYPE_PAR2ONLY => fn () => $this->removePar2Only(),
        ];
    }

    /**
     * Remove releases using user criteria.
     *
     * @param  array<string, mixed>  $arguments  Array of criteria used to delete unwanted releases.
     *                                           Criteria format: columnName=modifier="content"
     *                                           Modifiers: equals, like, bigger, smaller
     *
     * @throws Exception
     */
    public function removeByCriteria(array $arguments): bool|string
    {
        $this->delete = true;
        $this->ignoreUserCheck = false;
        $timeStart = now();

        $this->query = 'SELECT id, guid, searchname FROM releases WHERE 1=1';

        foreach ($arguments as $arg) {
            $this->error = '';
            $string = $this->formatCriteriaQuery($arg);
            if ($string === false) {
                return $this->returnError();
            }
            $this->query .= $string;
        }
        $this->query = $this->cleanSpaces($this->query);

        if (! $this->checkUserResponse()) {
            return false;
        }

        if (! $this->checkSelectQuery()) {
            return $this->returnError();
        }

        $this->method = 'userCriteria';
        $this->deletedCount = 0;
        $this->deleteReleases();

        $this->logCompletion($timeStart);

        return true;
    }

    /**
     * Delete crap releases.
     *
     * @param  bool  $delete  Delete the release or just show the result?
     * @param  int|string  $time  Time in hours (to select old releases) or 'full' for no time limit.
     * @param  string  $type  Type of query to run (blacklist, executable, gibberish, etc.) or '' for all
     *
     * @throws Exception
     */
    public function removeCrap(bool $delete, int|string $time, string $type = '', int|string $blacklistID = ''): bool|string
    {
        $timeStart = now();
        $this->delete = $delete;
        $this->blacklistID = $this->formatBlacklistID($blacklistID);
        $type = strtolower(trim($type));

        if (! $this->initCrapTime(trim($time), $type)) {
            return $this->returnError();
        }

        $this->deletedCount = 0;

        if (! $this->executeRemoval($type)) {
            return $this->returnError();
        }

        $this->logCompletion($timeStart);

        return true;
    }

    /**
     * Format the blacklist ID for query use.
     */
    private function formatBlacklistID(int|string $blacklistID): string
    {
        if ($blacklistID !== '' && is_numeric($blacklistID)) {
            return sprintf('AND id = %d', $blacklistID);
        }

        return '';
    }

    /**
     * Initialise the crap time filter.
     */
    private function initCrapTime(string $time, string $type): bool
    {
        $this->crapTime = '';

        if ($time === 'full') {
            $this->logHeader('Removing '.($type === '' ? 'All crap releases ' : $type.' crap releases').' - no time limit.');

            return true;
        }

        if (! is_numeric($time)) {
            $this->error = 'Error, time must be a number or full.';

            return false;
        }

        $this->logHeader('Removing '.($type === '' ? 'All crap releases ' : $type.' crap releases').' from the past '.$time.' hour(s).');
        $this->crapTime = ' AND r.adddate > (NOW() - INTERVAL '.$time.' HOUR)';

        return true;
    }

    /**
     * Execute the removal based on type.
     */
    private function executeRemoval(string $type): bool
    {
        if ($type === '') {
            return $this->executeAllRemovals();
        }

        if (! isset($this->removalHandlers[$type])) {
            $this->error = 'Wrong type: '.$type;

            return false;
        }

        $this->removalHandlers[$type]();

        return true;
    }

    /**
     * Execute all removal types (excluding passwordurl and wmv_all).
     */
    private function executeAllRemovals(): bool
    {
        $defaultTypes = [
            self::TYPE_BLACKLIST,
            self::TYPE_BLFILES,
            self::TYPE_EXECUTABLE,
            self::TYPE_GIBBERISH,
            self::TYPE_HASHED,
            self::TYPE_INSTALLBIN,
            self::TYPE_PASSWORDED,
            self::TYPE_SAMPLE,
            self::TYPE_SCR,
            self::TYPE_SHORT,
            self::TYPE_SIZE,
            self::TYPE_NZB,
            self::TYPE_CODEC,
            self::TYPE_PAR2ONLY,
        ];

        foreach ($defaultTypes as $removalType) {
            $this->removalHandlers[$removalType]();
        }

        return true;
    }

    /**
     * Log completion message.
     */
    private function logCompletion(Carbon $timeStart): void
    {
        if ($this->echoCLI) {
            cli()->headerOver(($this->delete ? 'Deleted ' : 'Would have deleted ').$this->deletedCount.' release(s). This script ran for ');
            cli()->header(now()->diffInSeconds($timeStart, true).' seconds', true);
        }
    }

    /**
     * Log a header message.
     */
    private function logHeader(string $message): void
    {
        if ($this->echoCLI) {
            cli()->header($message, true);
        }
    }

    /**
     * Execute a simple removal query with standard checks.
     *
     * @throws Exception
     */
    private function executeSimpleRemoval(string $method, string $query): bool
    {
        $this->method = $method;
        $this->query = $query;

        if (! $this->checkSelectQuery()) {
            return $this->returnError();
        }

        return $this->deleteReleases();
    }

    /**
     * Remove releases with 15 or more letters or numbers, nothing else.
     *
     * @throws Exception
     */
    protected function removeGibberish(): bool|string
    {
        return $this->executeSimpleRemoval('Gibberish', sprintf(
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
        ));
    }

    /**
     * Remove releases with 25 or more letters/numbers, probably hashed.
     *
     * @throws Exception
     */
    protected function removeHashed(): bool|string
    {
        return $this->executeSimpleRemoval('Hashed', sprintf(
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
        ));
    }

    /**
     * Remove releases with 5 or less letters/numbers.
     *
     * @throws Exception
     */
    protected function removeShort(): bool|string
    {
        return $this->executeSimpleRemoval('Short', sprintf(
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
        ));
    }

    /**
     * Remove releases with an exe file not in other misc or pc apps/games.
     *
     * @throws Exception
     */
    protected function removeExecutable(): bool|string
    {
        return $this->executeSimpleRemoval('Executable', sprintf(
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
        ));
    }

    /**
     * Remove releases with an install.bin file.
     *
     * @throws Exception
     */
    protected function removeInstallBin(): bool|string
    {
        return $this->executeSimpleRemoval('Install.bin', sprintf(
            'SELECT r.guid, r.searchname, r.id
            FROM releases r
            JOIN release_files rf ON r.id = rf.releases_id
            WHERE rf.name LIKE %s %s',
            escapeString('%install.bin%'),
            $this->crapTime
        ));
    }

    /**
     * Remove releases with a password.url file.
     *
     * @throws Exception
     */
    protected function removePasswordURL(): bool|string
    {
        return $this->executeSimpleRemoval('Password.url', sprintf(
            'SELECT r.guid, r.searchname, r.id
            FROM releases r
            JOIN release_files rf ON r.id = rf.releases_id
            WHERE rf.name LIKE %s %s',
            escapeString('%password.url%'),
            $this->crapTime
        ));
    }

    /**
     * Remove releases with password in the search name.
     *
     * @throws Exception
     */
    protected function removePassworded(): bool|string
    {
        return $this->executeSimpleRemoval('Passworded', sprintf(
            'SELECT r.guid, r.searchname, r.id
            FROM releases r
            WHERE r.searchname LIKE %s
            AND r.searchname NOT LIKE %s
            AND r.searchname NOT LIKE %s
            AND r.searchname NOT LIKE %s
            AND r.searchname NOT LIKE %s
            AND r.searchname NOT LIKE %s
            AND r.searchname NOT LIKE %s
            AND r.categories_id NOT IN (%d, %d, %d, %d, %d, %d, %d, %d, %d) %s',
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
        ));
    }

    /**
     * Remove releases smaller than site minimum file size with 1 part not in MP3/books/misc section.
     *
     * @throws Exception
     */
    protected function removeSize(): bool|string
    {
        $minSize = (int) app(ConfigurationProvider::class)->ingestion()->minSizeToFormRelease;
        if ($minSize === 0) {
            $minSize = 2097152; // Default to 2MB if not set
        }

        return $this->executeSimpleRemoval('Size', sprintf(
            'SELECT r.guid, r.searchname, r.id
            FROM releases r
            WHERE r.totalpart = 1
            AND r.size < %d
            AND r.categories_id NOT IN (%d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d) %s',
            $minSize,
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
        ));
    }

    /**
     * Remove releases that are just a single nzb file.
     *
     * @throws Exception
     */
    protected function removeSingleNZB(): bool|string
    {
        return $this->executeSimpleRemoval('.nzb', sprintf(
            'SELECT r.guid, r.searchname, r.id
            FROM releases r
            JOIN release_files rf ON r.id = rf.releases_id
            WHERE r.totalpart = 1
            AND rf.name LIKE %s %s',
            escapeString('%.nzb%'),
            $this->crapTime
        ));
    }

    /**
     * Remove releases with more than 1 part, less than 40MB, sample in name. TV/Movie sections.
     *
     * @throws Exception
     */
    protected function removeSample(): bool|string
    {
        return $this->executeSimpleRemoval('Sample', sprintf(
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
        ));
    }

    /**
     * Remove releases with a scr file in the filename/subject.
     *
     * @throws Exception
     */
    protected function removeSCR(): bool|string
    {
        return $this->executeSimpleRemoval('.scr', sprintf(
            "SELECT r.guid, r.searchname, r.id
            FROM releases r
            JOIN release_files rf ON r.id = rf.releases_id
            WHERE (rf.name REGEXP '[.]scr[$ \"]' OR r.name REGEXP '[.]scr[$ \"]')
            %s",
            $this->crapTime
        ));
    }

    /**
     * Remove releases using the site blacklist regexes.
     *
     * @throws Exception
     */
    protected function removeBlacklist(): bool
    {
        $status = (empty($this->blacklistID) || $this->delete)
            ? sprintf('AND status = %d', BlacklistConstants::BLACKLIST_ENABLED)
            : '';

        $regexList = DB::select(sprintf(
            'SELECT regex, id, groupname, msgcol
            FROM binaryblacklist
            WHERE optype = %d
            AND msgcol IN (%d, %d) %s %s
            ORDER BY id ASC',
            BlacklistConstants::OPTYPE_BLACKLIST,
            BlacklistConstants::BLACKLIST_FIELD_SUBJECT,
            BlacklistConstants::BLACKLIST_FIELD_FROM,
            $this->blacklistID,
            $status
        ));

        if (empty($regexList)) {
            cli()->error("No regular expressions were selected for blacklist removal. Make sure you have activated REGEXPs in Site Edit and you're specifying a valid ID.", true);

            return true;
        }

        $rules = $this->compileBlacklistRules($regexList);
        if ($rules === []) {
            return true;
        }

        $this->processPhpMatchedCandidates(
            'SELECT r.id, r.guid, r.searchname, r.fromname, r.groups_id FROM releases r WHERE 1=1 '.$this->crapTime,
            function (object $release) use ($rules): ?string {
                foreach ($rules as $rule) {
                    if (! $this->ruleAppliesToGroup($rule, (int) $release->groups_id)) {
                        continue;
                    }

                    $value = (int) $rule->msgcol === BlacklistConstants::BLACKLIST_FIELD_SUBJECT
                        ? (string) $release->searchname
                        : (string) $release->fromname;

                    if (preg_match($rule->pattern, $value) === 1) {
                        return 'Blacklist ['.$rule->id.']';
                    }
                }

                return null;
            }
        );

        return true;
    }

    /**
     * Remove releases using the site blacklist regexes against file names.
     *
     * @throws Exception
     */
    protected function removeBlacklistFiles(): bool
    {
        $allRegex = DB::select(sprintf(
            'SELECT regex, id, groupname
            FROM binaryblacklist
            WHERE status = %d
            AND optype = %d
            AND msgcol = %d
            ORDER BY id ASC',
            BlacklistConstants::BLACKLIST_ENABLED,
            BlacklistConstants::OPTYPE_BLACKLIST,
            BlacklistConstants::BLACKLIST_FIELD_SUBJECT
        ));

        if (empty($allRegex)) {
            return true;
        }

        $rules = $this->compileBlacklistRules($allRegex);
        if ($rules === []) {
            return true;
        }

        $this->processPhpMatchedCandidates(
            'SELECT r.id, r.guid, r.searchname, r.groups_id FROM releases r '.
            'WHERE EXISTS (SELECT 1 FROM release_files rf WHERE rf.releases_id = r.id) '.$this->crapTime,
            function (object $release) use ($rules): ?string {
                foreach ($rules as $rule) {
                    if (! $this->ruleAppliesToGroup($rule, (int) $release->groups_id)) {
                        continue;
                    }

                    foreach ($release->file_names as $name) {
                        if (preg_match($rule->pattern, (string) $name) === 1) {
                            return 'Blacklist Files '.$rule->id;
                        }
                    }
                }

                return null;
            },
            true
        );

        return true;
    }

    /**
     * @param  array<int, object>  $rules
     * @return array<int, object>
     */
    private function compileBlacklistRules(array $rules): array
    {
        $groups = DB::table('usenet_groups')->pluck('name', 'id');
        $compiled = [];

        foreach ($rules as $rule) {
            $pattern = '/'.$rule->regex.'/i';
            if (@preg_match($pattern, '') === false) {
                Log::warning('Skipping invalid release-removal blacklist regex', [
                    'blacklist_id' => $rule->id,
                    'regex' => $rule->regex,
                ]);

                continue;
            }

            $rule->pattern = $pattern;
            $rule->group_ids = null;

            if (strtolower((string) $rule->groupname) !== 'alt.binaries.*') {
                $groupPattern = '/'.$rule->groupname.'/i';
                if (@preg_match($groupPattern, '') === false) {
                    Log::warning('Skipping blacklist with invalid group regex', [
                        'blacklist_id' => $rule->id,
                        'group_regex' => $rule->groupname,
                    ]);

                    continue;
                }

                $rule->group_ids = $groups
                    ->filter(static fn (string $name): bool => preg_match($groupPattern, $name) === 1)
                    ->keys()
                    ->map(static fn (int|string $id): int => (int) $id)
                    ->all();

                if ($rule->group_ids === []) {
                    continue;
                }
            }

            $compiled[] = $rule;
        }

        return $compiled;
    }

    private function ruleAppliesToGroup(object $rule, int $groupId): bool
    {
        return $rule->group_ids === null || in_array($groupId, $rule->group_ids, true);
    }

    /**
     * @param  callable(object): ?string  $matcher
     */
    private function processPhpMatchedCandidates(string $candidateSql, callable $matcher, bool $includeFiles = false): void
    {
        $lastId = 0;

        do {
            $candidates = DB::select(
                'SELECT candidates.* FROM ('.$this->cleanSpaces($candidateSql).') candidates '.
                'WHERE candidates.id > ? ORDER BY candidates.id ASC LIMIT '.self::BATCH_SIZE,
                [$lastId]
            );

            if ($candidates === []) {
                break;
            }

            $lastId = (int) end($candidates)->id;
            $matches = collect();

            if ($includeFiles) {
                $fileNames = DB::table('release_files')
                    ->whereIn('releases_id', array_column($candidates, 'id'))
                    ->select(['releases_id', 'name'])
                    ->get()
                    ->groupBy('releases_id');

                foreach ($candidates as $candidate) {
                    $candidate->file_names = $fileNames
                        ->get((int) $candidate->id, collect())
                        ->pluck('name');
                }
            }

            foreach ($candidates as $candidate) {
                $method = $matcher($candidate);
                if ($method === null) {
                    continue;
                }

                $candidate->removal_method = $method;
                $matches->push($candidate);
            }

            foreach ($matches as $release) {
                if ($this->echoCLI) {
                    cli()->primary(
                        ($this->delete ? 'Deleting: ' : 'Would be deleting: ').$release->removal_method.': '.$release->searchname,
                        true
                    );
                }
            }

            if ($this->delete && $matches->isNotEmpty()) {
                $this->releaseManagement->deleteBatch($matches, $this->nzb, $this->releaseImage);
            }

            $this->deletedCount += $matches->count();

            if (count($candidates) === self::BATCH_SIZE) {
                usleep(self::BATCH_PAUSE_US);
            }
        } while (true);
    }

    /**
     * Remove releases that contain .wmv file, aka that spam poster.
     * Thanks to dizant from nZEDb forums for the sql query.
     *
     * @throws Exception
     */
    protected function removeWMV(): bool|string
    {
        return $this->executeSimpleRemoval('WMV_ALL', sprintf(
            "SELECT r.guid, r.searchname, r.id
            FROM releases r
            LEFT JOIN release_files rf ON (r.id = rf.releases_id)
            WHERE r.categories_id BETWEEN %d AND %d
            AND rf.name REGEXP 'x264.*\\.wmv$'
            GROUP BY r.id %s",
            Category::TV_ROOT,
            Category::TV_OTHER,
            $this->crapTime
        ));
    }

    /**
     * Remove releases that contain .wmv files and Codec\Setup.exe files, aka that spam poster.
     * Thanks to dizant from nZEDb forums for parts of the sql query.
     *
     * @throws Exception
     */
    protected function removeCodecPoster(): bool|string
    {
        $categories = $this->buildCategoryList([ // @phpstan-ignore argument.type
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
            Category::XXX_OTHER,
        ]);

        $videoRegex = '\.*((DVDrip|BRRip)[. ].*[. ](R[56]|HQ)|720p[ .](DVDrip|HQ)|Webrip.*[. ](R[56]|Xvid|AC3|US)'
            .'|720p.*[. ]WEB-DL[. ]Xvid[. ]AC3[. ]US|HDRip.*[. ]Xvid[. ]DD5).*[. ]avi$';

        return $this->executeSimpleRemoval('Codec', "
            SELECT r.guid, r.searchname, r.id
            FROM releases r
            LEFT JOIN release_files rf ON r.id = rf.releases_id
            WHERE r.categories_id IN ({$categories})
            AND ((r.imdbid IS NOT NULL AND r.imdbid NOT IN ('', '0')) OR r.categories_id BETWEEN ".Category::XXX_ROOT.' AND '.Category::XXX_OTHER.")
            AND r.nfostatus = 1
            AND r.haspreview = 0
            AND r.jpgstatus = 0
            AND r.predb_id = 0
            AND r.videostatus = 0
            AND (
                rf.name REGEXP 'XviD-[a-z]{3}\\.(avi|mkv|wmv)$'
                OR rf.name REGEXP 'x264.*\\.(wmv|avi)$'
                OR rf.name REGEXP '{$videoRegex}'
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
            GROUP BY r.id {$this->crapTime}");
    }

    /**
     * Remove releases that contain only PAR2 files (no actual content files).
     *
     * These releases are useless since PAR2 files are only repair/verification
     * data and cannot be used without the original content files.
     *
     * Two detection strategies are used:
     * 1. The searchname contains a .par2 filename AND has no associated
     *    release_files, or only par2 release_files. The character class after
     *    .par2 includes underscore because the searchname sanitizer replaces
     *    quotes and special chars with underscores (e.g. .par2" becomes .par2_).
     *    Note: We intentionally do NOT match on r.name (raw Usenet subject)
     *    because many legitimate releases list a .par2 index file in the subject
     *    even though the actual content is video/audio/etc.
     * 2. All associated release_files have names containing .par2 (rare edge case
     *    where par2 metadata was stored during post-processing).
     *
     * @throws Exception
     */
    protected function removePar2Only(): bool|string
    {
        // Strategy 1: searchname contains a par2 filename pattern and has
        // no non-par2 release_files. Matches .par2 (index) and .vol123+45.par2 (volumes)
        // followed by a delimiter char or end of string.
        // IMPORTANT: We use [.] and [+] instead of \. and \+ because MySQL's SQL
        // string parser silently strips backslashes before unrecognized escape chars
        // (e.g. \. → . , \+ → +) before the regex engine sees them.
        // The delimiter class []" _[)(>] includes: ] " space _ [ ) ( > where ]
        // must be first in the class per regex syntax.
        $this->executeSimpleRemoval('Par2Only', sprintf(
            "SELECT r.guid, r.searchname, r.id
            FROM releases r
            WHERE r.searchname REGEXP '[.](vol[0-9]+[+][0-9]+[.]par2|par2)([]\" _[)(>]|$)'
            AND r.id NOT IN (
                SELECT rf.releases_id FROM release_files rf
                WHERE rf.name NOT REGEXP '[.]par2'
            )
            %s",
            $this->crapTime
        ));

        // Strategy 2: All release_files are .par2
        return $this->executeSimpleRemoval('Par2Only_Files', sprintf(
            "SELECT r.guid, r.searchname, r.id
            FROM releases r
            INNER JOIN release_files rf ON r.id = rf.releases_id
            WHERE 1=1 %s
            GROUP BY r.id, r.guid, r.searchname
            HAVING COUNT(*) = SUM(CASE WHEN rf.name REGEXP '[.]par2' THEN 1 ELSE 0 END)",
            $this->crapTime
        ));
    }

    /**
     * Build a comma-separated list of category IDs.
     *
     * @param  array<string, mixed>  $categories
     */
    private function buildCategoryList(array $categories): string
    {
        return implode(', ', $categories);
    }

    /**
     * Delete releases from the database.
     *
     * @throws Exception
     */
    protected function deleteReleases(): bool
    {
        $lastId = 0;

        do {
            $batch = DB::select(
                'SELECT candidates.id, candidates.guid, candidates.searchname FROM ('.$this->cleanSpaces($this->query).') candidates '.
                'WHERE candidates.id > ? ORDER BY candidates.id ASC LIMIT '.self::BATCH_SIZE,
                [$lastId]
            );

            if ($batch === []) {
                break;
            }

            $batch = collect($batch)->unique('id')->values();
            $lastId = (int) $batch->max('id');

            foreach ($batch as $release) {
                if ($this->echoCLI) {
                    cli()->primary(
                        ($this->delete ? 'Deleting: ' : 'Would be deleting: ').$this->method.': '.$release->searchname,
                        true
                    );
                }
            }

            if ($this->delete) {
                $this->releaseManagement->deleteBatch($batch, $this->nzb, $this->releaseImage);
            }

            $this->deletedCount += $batch->count();

            if ($batch->count() === self::BATCH_SIZE) {
                usleep(self::BATCH_PAUSE_US);
            }
        } while (true);

        return true;
    }

    /**
     * Verify if the query has any results.
     *
     * @return bool False on failure, true on success after setting a count of found releases.
     */
    protected function checkSelectQuery(): bool
    {
        $result = DB::select(
            'SELECT 1 FROM ('.$this->cleanSpaces($this->query).') candidates LIMIT 1'
        );
        if (empty($result)) {
            $this->error = '';
            if ($this->method === 'userCriteria') {
                $this->error = 'No releases were found to delete, try changing your criteria.';
            }

            return false;
        }

        return true;
    }

    /**
     * Go through user arguments and format part of the query.
     *
     * @param  string  $argument  User argument (format: column=modifier=value)
     * @return string|false
     */
    protected function formatCriteriaQuery(string $argument): bool|string
    {
        if ($argument === 'ignore') {
            $this->ignoreUserCheck = true;

            return '';
        }

        $this->error = 'Invalid argument supplied: '.$argument.PHP_EOL;
        $args = explode('=', $argument);

        if (\count($args) !== 3) {
            return false;
        }

        $column = $this->cleanSpaces($args[0]);
        $modifier = $this->cleanSpaces($args[1]);
        $value = $this->cleanSpaces($args[2]);

        return $this->buildCriteriaClause($column, $modifier, $value);
    }

    /**
     * Build a criteria clause based on column, modifier and value.
     */
    private function buildCriteriaClause(string $column, string $modifier, string $value): bool|string
    {
        // Simple equals columns
        $simpleEqualsColumns = ['categories_id', 'nzbstatus', 'videos_id'];
        if (in_array($column, $simpleEqualsColumns) && $modifier === 'equals') {
            return " AND {$column} = {$value}";
        }

        // Columns that support equals, bigger, smaller
        $numericColumns = ['totalpart', 'size'];
        if (in_array($column, $numericColumns)) {
            if ($column === 'size' && ! is_numeric($value)) {
                return false;
            }

            return $this->buildNumericClause($column, $modifier, $value);
        }

        // Date columns (note: bigger means older, smaller means newer)
        $dateColumns = ['adddate', 'postdate'];
        if (in_array($column, $dateColumns)) {
            if (! is_numeric($value)) {
                return false;
            }

            return $this->buildDateClause($column, $modifier, $value);
        }

        // String columns with equals/like support
        $stringColumns = ['fromname', 'name', 'searchname'];
        if (in_array($column, $stringColumns)) {
            return $this->buildStringClause($column, $modifier, $value);
        }

        // Special cases
        return match ($column) {
            'imdbid' => $this->buildImdbClause($modifier, $value),
            'guid' => $modifier === 'equals' ? ' AND guid = '.escapeString($value) : false,
            'groupname' => $this->buildGroupClause($modifier, $value),
            'completion' => $this->buildCompletionClause($modifier, $value),
            default => false,
        };
    }

    /**
     * Build numeric comparison clause.
     */
    private function buildNumericClause(string $column, string $modifier, string $value): bool|string
    {
        return match ($modifier) {
            'equals' => " AND {$column} = {$value}",
            'bigger' => " AND {$column} > {$value}",
            'smaller' => " AND {$column} < {$value}",
            default => false,
        };
    }

    /**
     * Build date comparison clause.
     */
    private function buildDateClause(string $column, string $modifier, string $value): bool|string
    {
        return match ($modifier) {
            'bigger' => " AND {$column} < NOW() - INTERVAL {$value} HOUR",
            'smaller' => " AND {$column} > NOW() - INTERVAL {$value} HOUR",
            default => false,
        };
    }

    /**
     * Build string comparison clause.
     */
    private function buildStringClause(string $column, string $modifier, string $value): bool|string
    {
        return match ($modifier) {
            'equals' => " AND {$column} = ".escapeString($value),
            'like' => " AND {$column} ".$this->formatLike($value, $column),
            default => false,
        };
    }

    /**
     * Build imdbid clause.
     */
    private function buildImdbClause(string $modifier, string $value): bool|string
    {
        if ($modifier !== 'equals') {
            return false;
        }

        return $value === 'NULL' ? ' AND imdbid IS NULL ' : ' AND imdbid = '.$value;
    }

    /**
     * Build group clause.
     */
    private function buildGroupClause(string $modifier, string $value): bool|string
    {
        if ($modifier === 'equals') {
            $group = DB::select('SELECT id FROM usenet_groups WHERE name = '.escapeString($value));
            if (empty($group)) {
                $this->error = 'This group was not found in your database: '.$value.PHP_EOL;

                return false;
            }

            return ' AND groups_id = '.$group[0]->id;
        }

        if ($modifier === 'like') {
            $groups = DB::select('SELECT id FROM usenet_groups WHERE name '.$this->formatLike($value, 'name'));
            if (empty($groups)) {
                $this->error = 'No groups were found with this pattern in your database: '.$value.PHP_EOL;

                return false;
            }

            $ids = collect($groups)->pluck('id')->implode(',');

            return ' AND groups_id IN ('.$ids.')';
        }

        return false;
    }

    /**
     * Build completion clause.
     */
    private function buildCompletionClause(string $modifier, string $value): bool|string
    {
        if (! is_numeric($value) || $modifier !== 'smaller') {
            return false;
        }

        return ' AND completion > 0 AND completion < '.$value;
    }

    /**
     * Check if the user wants to run the current query.
     */
    protected function checkUserResponse(): bool
    {
        if ($this->ignoreUserCheck) {
            return true;
        }

        cli()->primary(
            'This is the query we have formatted using your criteria, you can run it in SQL to see if you like the results:'.
            PHP_EOL.$this->query.';'.PHP_EOL.
            'If you are satisfied, type yes and press enter. Anything else will exit.',
            true
        );

        $userInput = trim(fgets(fopen('php://stdin', 'rtb')));
        if ($userInput !== 'yes') {
            cli()->primary('You typed: "'.$userInput.'", the program will exit.', true);

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
     */
    protected function formatLike(string $string, string $column): string
    {
        $parts = explode(' ', $string);
        if (\count($parts) > 1) {
            $string = implode("%' AND {$column} LIKE '%", array_unique($parts));
        }

        return " LIKE '%".$string."%' ";
    }

    /**
     * Log error and return false.
     */
    protected function returnError(): bool
    {
        if ($this->echoCLI && $this->error !== '') {
            cli()->error($this->error, true);
        }

        return false;
    }
}
