<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\Predb;
use App\Models\Release;
use App\Models\UsenetGroup;
use App\Services\Categorization\CategorizationService;
use Blacklight\utility\Utility;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Class NameFixer.
 */
class NameFixer
{
    public const PREDB_REGEX = '/([\w.\'()\[\]-]+(?:[\s._-]+[\w.\'()\[\]-]+)+[-.][\w]+)/ui';

    // Constants for name fixing status
    public const PROC_NFO_NONE = 0;

    public const PROC_NFO_DONE = 1;

    public const PROC_FILES_NONE = 0;

    public const PROC_FILES_DONE = 1;

    public const PROC_PAR2_NONE = 0;

    public const PROC_PAR2_DONE = 1;

    public const PROC_UID_NONE = 0;

    public const PROC_UID_DONE = 1;

    public const PROC_HASH16K_NONE = 0;

    public const PROC_HASH16K_DONE = 1;

    public const PROC_SRR_NONE = 0;

    public const PROC_SRR_DONE = 1;

    public const PROC_CRC_NONE = 0;

    public const PROC_CRC_DONE = 1;

    // Constants for overall rename status
    public const IS_RENAMED_NONE = 0;

    public const IS_RENAMED_DONE = 1;

    /**
     * Has the current release found a new name?
     */
    public bool $matched;

    /**
     * How many releases have got a new name?
     */
    public int $fixed;

    /**
     * How many releases were checked.
     */
    public int $checked;

    /**
     * Was the check completed?
     */
    public bool $done;

    /**
     * Do we want to echo info to CLI?
     */
    public bool $echoOutput;

    /**
     * Total releases we are working on.
     */
    protected int $_totalReleases;

    /**
     * The cleaned filename we want to match.
     */
    protected string $_fileName;

    /**
     * The release ID we are trying to rename.
     */
    protected int $relid;

    protected string $othercats;

    protected string $timeother;

    protected string $timeall;

    protected string $fullother;

    protected string $fullall;

    public ColorCLI $colorCLI;

    /**
     * @var CategorizationService
     */
    public mixed $category;

    public Utility $text;

    /**
     * @var ManticoreSearch
     */
    public mixed $manticore;

    protected ColorCLI $colorCli;

    private ElasticSearchSiteSearch $elasticsearch;

    public function __construct()
    {
        $this->echoOutput = config('nntmux.echocli');
        $this->relid = $this->fixed = $this->checked = 0;
        $this->othercats = implode(',', Category::OTHERS_GROUP);
        $this->timeother = sprintf(' AND rel.adddate > (NOW() - INTERVAL 6 HOUR) AND rel.categories_id IN (%s) GROUP BY rel.id ORDER BY postdate DESC', $this->othercats);
        $this->timeall = ' AND rel.adddate > (NOW() - INTERVAL 6 HOUR) GROUP BY rel.id ORDER BY postdate DESC';
        $this->fullother = sprintf(' AND rel.categories_id IN (%s) GROUP BY rel.id', $this->othercats);
        $this->fullall = '';
        $this->_fileName = '';
        $this->done = $this->matched = false;
        $this->colorCLI = new ColorCLI;
        $this->category = new CategorizationService();
        $this->manticore = new ManticoreSearch;
        $this->elasticsearch = new ElasticSearchSiteSearch;
    }

    /**
     * Attempts to fix release names using the NFO.
     *
     * Enhanced to use the new Nfo class metadata extraction features for better
     * release name identification from IMDB, TVDB, TMDB, and other media sources.
     *
     * @param  int|string  $time  Time limit for query
     * @param  bool  $echo  Whether to actually update the database
     * @param  int  $cats  Category filter (2=misc/hashed, 3=predb)
     * @param  bool  $nameStatus  Whether to update status columns
     * @param  bool  $show  Whether to show output
     *
     * @throws \Exception
     */
    public function fixNamesWithNfo($time, $echo, $cats, $nameStatus, $show): void
    {
        $this->_echoStartMessage($time, '.nfo files');
        $type = 'NFO, ';

        // Initialize the Nfo parser
        $nfoParser = new Nfo();

        // Only select releases we haven't checked here before
        $preId = false;
        if ($cats === 3) {
            $query = sprintf(
                '
					SELECT rel.id AS releases_id, rel.fromname
					FROM releases rel
					INNER JOIN release_nfos nfo ON (nfo.releases_id = rel.id)
					WHERE rel.predb_id = 0'
            );
            $cats = 2;
            $preId = true;
        } else {
            $query = sprintf(
                '
					SELECT rel.id AS releases_id, rel.fromname
					FROM releases rel
					INNER JOIN release_nfos nfo ON (nfo.releases_id = rel.id)
					WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
					AND rel.predb_id = 0
					AND rel.proc_nfo = %d',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                self::PROC_NFO_NONE
            );
        }

        $releases = $this->_getReleases($time, $cats, $query);

        $total = $releases->count();

        if ($total > 0) {
            $this->_totalReleases = $total;
            $this->colorCLI->info(number_format($total).' releases to process.');

            foreach ($releases as $rel) {
                $releaseRow = Release::fromQuery(
                    sprintf(
                        '
							SELECT nfo.releases_id AS nfoid, rel.groups_id, rel.fromname, rel.categories_id, rel.name, rel.searchname,
								UNCOMPRESS(nfo) AS textstring, rel.id AS releases_id
							FROM releases rel
							INNER JOIN release_nfos nfo ON (nfo.releases_id = rel.id)
							WHERE rel.id = %d LIMIT 1',
                        $rel->releases_id
                    )
                );

                $this->checked++;

                // Ignore encrypted NFOs.
                if (preg_match('/^=newz\[NZB\]=\w+/', $releaseRow[0]->textstring)) {
                    $this->_updateSingleColumn('proc_nfo', self::PROC_NFO_DONE, $rel->releases_id);

                    continue;
                }

                $this->reset();

                // First, try to extract metadata using the enhanced Nfo parser
                $nfoMetadata = $nfoParser->parseNfoMetadata($releaseRow[0]->textstring);

                // Try to find a better name using extracted media IDs
                $betterNameFound = $this->tryNfoMetadataRename($releaseRow[0], $nfoMetadata, $echo, $type, $nameStatus, $show, $preId);

                // If metadata extraction didn't find a name, fall back to traditional checks
                if (! $betterNameFound) {
                    $this->checkName($releaseRow[0], $echo, $type, $nameStatus, $show, $preId);
                }

                $this->_echoRenamed($show);
            }
            $this->_echoFoundCount($echo, ' NFO\'s');
        } else {
            $this->colorCLI->info('Nothing to fix.');
        }
    }

    /**
     * Try to rename a release using extracted NFO metadata.
     *
     * Uses media IDs (IMDB, TVDB, TMDB) and codec info from NFO to build
     * a better release name.
     *
     * @param  object  $release  The release object
     * @param  array  $nfoMetadata  Metadata extracted from NFO by Nfo::parseNfoMetadata()
     * @param  bool  $echo  Whether to update database
     * @param  string  $type  The type string for logging
     * @param  bool  $nameStatus  Whether to update status columns
     * @param  bool  $show  Whether to show output
     * @param  bool  $preId  Whether processing for PreDB
     * @return bool True if a better name was found and applied
     *
     * @throws \Exception
     */
    protected function tryNfoMetadataRename(object $release, array $nfoMetadata, bool $echo, string $type, $nameStatus, bool $show, bool $preId = false): bool
    {
        // Skip if already processed
        if ($this->done || $this->relid === (int) $release->releases_id) {
            return false;
        }

        // Try to get a name from media database IDs
        $mediaIds = $nfoMetadata['media_ids'] ?? [];
        $codecInfo = $nfoMetadata['codec_info'] ?? [];
        $releaseGroup = $nfoMetadata['group'] ?? null;

        // Priority order: IMDB (movies/TV), TMDB, TVDB, TVMaze
        foreach ($mediaIds as $mediaId) {
            $newName = $this->getNameFromMediaId($mediaId['source'], $mediaId['id']);
            if ($newName !== null) {
                // Enhance the name with codec info if available
                $enhancedName = $this->enhanceNameWithCodecInfo($newName, $codecInfo, $releaseGroup);

                $this->updateRelease(
                    $release,
                    $enhancedName,
                    'nfoCheck: Media ID ('.$mediaId['source'].': '.$mediaId['id'].')',
                    $echo,
                    $type,
                    $nameStatus,
                    $show
                );

                return true;
            }
        }

        // If we have codec info but no media ID match, try to enhance existing name patterns
        if (! empty($codecInfo)) {
            // Check if there's a recognizable title pattern in the NFO
            $titleFromNfo = $this->extractTitleFromNfoContent($release->textstring);
            if ($titleFromNfo !== null) {
                $enhancedName = $this->enhanceNameWithCodecInfo($titleFromNfo, $codecInfo, $releaseGroup);
                if (strtolower($enhancedName) !== strtolower($release->searchname)) {
                    $this->updateRelease(
                        $release,
                        $enhancedName,
                        'nfoCheck: NFO Title with Codec Info',
                        $echo,
                        $type,
                        $nameStatus,
                        $show
                    );

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get a release name from a media database ID.
     *
     * Queries local database or external APIs to resolve media IDs to titles.
     *
     * @param  string  $source  The source database (imdb, thetvdb, tmdb_movie, tmdb_tv, tvmaze, anidb, mal)
     * @param  string  $id  The media ID
     * @return string|null The title if found, null otherwise
     */
    protected function getNameFromMediaId(string $source, string $id): ?string
    {
        switch ($source) {
            case 'imdb':
                // Check if we have this IMDB ID in our movieinfo table
                $movie = \App\Models\MovieInfo::where('imdbid', ltrim($id, 't'))->first(['title', 'year']);
                if ($movie !== null) {
                    return $movie->year > 0 ? "{$movie->title} ({$movie->year})" : $movie->title;
                }
                // Also check Video table for TV shows with IMDB ID
                $video = \App\Models\Video::where('imdb', (int) ltrim($id, 't'))->first(['title']);
                if ($video !== null) {
                    return $video->title;
                }
                break;

            case 'thetvdb':
                // Check Video table for TVDB ID
                $video = \App\Models\Video::where('tvdb', (int) $id)->first(['title']);
                if ($video !== null) {
                    return $video->title;
                }
                break;

            case 'tmdb_movie':
                // Check local movie database for TMDB ID
                $movie = \App\Models\MovieInfo::where('tmdbid', (int) $id)->first(['title', 'year']);
                if ($movie !== null) {
                    return $movie->year > 0 ? "{$movie->title} ({$movie->year})" : $movie->title;
                }
                break;

            case 'tmdb_tv':
                // Check Video table for TMDB ID
                $video = \App\Models\Video::where('tmdb', (int) $id)->first(['title']);
                if ($video !== null) {
                    return $video->title;
                }
                break;

            case 'tvmaze':
                // Check Video table for TVMaze ID
                $video = \App\Models\Video::where('tvmaze', (int) $id)->first(['title']);
                if ($video !== null) {
                    return $video->title;
                }
                break;

            case 'anidb':
                // Check AniDB table - uses Video table with anidb column
                $video = \App\Models\Video::where('anidb', (int) $id)->first(['title']);
                if ($video !== null) {
                    return $video->title;
                }
                // Also check anidb_titles table
                $anime = \App\Models\AnidbTitle::where('anidbid', (int) $id)->first(['title']);
                if ($anime !== null) {
                    return $anime->title;
                }
                break;

            case 'trakt':
                // Check Video table for Trakt ID
                $video = \App\Models\Video::where('trakt', (int) $id)->first(['title']);
                if ($video !== null) {
                    return $video->title;
                }
                break;

            case 'mal':
                // MyAnimeList - currently no direct support in the database
                break;
        }

        return null;
    }

    /**
     * Enhance a title with codec/resolution information.
     *
     * @param  string  $title  The base title
     * @param  array  $codecInfo  Codec info from Nfo::extractCodecInfo()
     * @param  string|null  $releaseGroup  The release group name if found
     * @return string The enhanced title
     */
    protected function enhanceNameWithCodecInfo(string $title, array $codecInfo, ?string $releaseGroup = null): string
    {
        $parts = [$title];

        // Add resolution
        if (! empty($codecInfo['resolution'])) {
            $parts[] = $codecInfo['resolution'];
        }

        // Add video codec
        if (! empty($codecInfo['video'])) {
            $parts[] = $codecInfo['video'];
        }

        // Add audio codec
        if (! empty($codecInfo['audio'])) {
            $parts[] = $codecInfo['audio'];
        }

        // Add release group
        if ($releaseGroup !== null) {
            $parts[] = '-'.$releaseGroup;

            return implode('.', array_slice($parts, 0, -1)).$parts[count($parts) - 1];
        }

        return implode('.', $parts);
    }

    /**
     * Extract a recognizable title from NFO content.
     *
     * Looks for common title patterns like "Title (Year)" or scene-style names.
     *
     * @param  string  $nfoContent  The NFO content
     * @return string|null The extracted title or null if not found
     */
    protected function extractTitleFromNfoContent(string $nfoContent): ?string
    {
        // Look for "Title (Year)" pattern - common in movie NFOs
        if (preg_match('/^[\s\S]*?([A-Z][A-Za-z0-9\s\.\'\-\:]+(?:\s+\((?:19|20)\d{2}\)))/m', $nfoContent, $matches)) {
            $title = trim($matches[1]);
            // Validate it's not too short or too long
            if (strlen($title) >= 5 && strlen($title) <= 150) {
                return $title;
            }
        }

        // Look for release name patterns (Scene style)
        if (preg_match('/(?:Release|Rls|Name)\s*[:\-]?\s*([A-Za-z0-9][\w.\-]+(?:[\s._-][\w.\-]+)+)/i', $nfoContent, $matches)) {
            $title = trim($matches[1]);
            if (strlen($title) >= 5 && strlen($title) <= 150) {
                return $title;
            }
        }

        // Look for title in common NFO header patterns
        if (preg_match('/(?:presents|proudly brings)\s*[:\-]?\s*([A-Za-z0-9][\w.\s\-\']+(?:[\s._-][\w.\s\-\']+)*)/i', $nfoContent, $matches)) {
            $title = trim($matches[1]);
            if (strlen($title) >= 5 && strlen($title) <= 150) {
                return $title;
            }
        }

        return null;
    }

    /**
     * Attempts to fix release names using the File name.
     *
     * Enhanced to better handle:
     * - RAR archives and their contents
     * - Multiple file selection with priority ordering
     * - Modern scene release naming conventions
     *
     * @throws \Exception
     */
    public function fixNamesWithFiles($time, $echo, $cats, $nameStatus, $show): void
    {
        $this->_echoStartMessage($time, 'file names');
        $type = 'Filenames, ';

        $preId = false;
        if ($cats === 3) {
            $query = sprintf(
                '
					SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
						rf.releases_id AS fileid, rel.id AS releases_id
					FROM releases rel
					INNER JOIN release_files rf ON rf.releases_id = rel.id
					WHERE predb_id = 0'
            );
            $cats = 2;
            $preId = true;
        } else {
            $query = sprintf(
                '
					SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
						rf.releases_id AS fileid, rel.id AS releases_id
					FROM releases rel
					INNER JOIN release_files rf ON rf.releases_id = rel.id
					WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
					AND rel.predb_id = 0
					AND proc_files = %d',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                self::PROC_FILES_NONE
            );
        }

        $releases = $this->_getReleases($time, $cats, $query);
        $total = $releases->count();
        if ($total > 0) {
            $this->_totalReleases = $total;
            $this->colorCLI->info(number_format($total).' file names to process.');

            // Group files by release for better processing
            $releaseFiles = [];
            foreach ($releases as $release) {
                $releaseId = $release->releases_id;
                if (! isset($releaseFiles[$releaseId])) {
                    $releaseFiles[$releaseId] = [
                        'release' => $release,
                        'files' => [],
                    ];
                }
                $releaseFiles[$releaseId]['files'][] = $release->textstring;
            }

            foreach ($releaseFiles as $releaseId => $data) {
                $this->reset();
                $this->checked++;

                // Prioritize files for matching
                $prioritizedFiles = $this->prioritizeFilesForMatching($data['files']);

                foreach ($prioritizedFiles as $filename) {
                    $release = clone $data['release'];
                    $release->textstring = $filename;

                    $this->checkName($release, $echo, $type, $nameStatus, $show, $preId);

                    if ($this->matched) {
                        break; // Found a match, stop trying other files
                    }
                }

                $this->_echoRenamed($show);
            }

            $this->_echoFoundCount($echo, ' files');
        } else {
            $this->colorCLI->info('Nothing to fix.');
        }
    }

    /**
     * Prioritize files for name matching.
     *
     * Returns files sorted by usefulness for name matching:
     * 1. Main video files (mkv, avi, mp4, etc.)
     * 2. SRR files (often contain original release name)
     * 3. NFO files
     * 4. RAR archives (first file, not parts)
     * 5. Other files
     */
    protected function prioritizeFilesForMatching(array $files): array
    {
        $videoFiles = [];
        $srrFiles = [];
        $nfoFiles = [];
        $rarMainFiles = [];
        $otherFiles = [];

        foreach ($files as $file) {
            $lowerFile = strtolower($file);

            // Skip sample and proof files
            if (preg_match('/[\.\-_](sample|proof|subs?|thumbs?)[\.\-_]/i', $file)) {
                continue;
            }

            if (preg_match('/\.(mkv|avi|mp4|m4v|wmv|divx|ts|m2ts)$/i', $file)) {
                $videoFiles[] = $file;
            } elseif (str_ends_with($lowerFile, '.srr')) {
                $srrFiles[] = $file;
            } elseif (str_ends_with($lowerFile, '.nfo')) {
                $nfoFiles[] = $file;
            } elseif (preg_match('/\.rar$/i', $file) && ! preg_match('/\.part\d+\.rar$/i', $file)) {
                // Main RAR file (not .part01.rar style)
                $rarMainFiles[] = $file;
            } elseif (preg_match('/\.part0*1\.rar$/i', $file)) {
                // First part of split RAR
                $rarMainFiles[] = $file;
            } else {
                $otherFiles[] = $file;
            }
        }

        // Sort video files by size (longer names often more descriptive)
        usort($videoFiles, fn($a, $b) => strlen($b) - strlen($a));

        return array_merge($videoFiles, $srrFiles, $nfoFiles, $rarMainFiles, $otherFiles);
    }

    /**
     * Attempts to fix release names using the rar file crc32 hash.
     *
     * Enhanced to better match RAR file CRC32 hashes across releases,
     * with improved size tolerance and multiple file support.
     *
     * @throws \Exception
     */
    public function fixNamesWithCrc($time, $echo, $cats, $nameStatus, $show): void
    {
        $this->_echoStartMessage($time, 'CRC32');
        $type = 'CRC32, ';

        $preId = false;
        if ($cats === 3) {
            $query = sprintf(
                '
					SELECT rf.crc32 AS textstring, rf.name AS filename, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id, rel.size as relsize,
						rf.releases_id AS fileid, rel.id AS releases_id
					FROM releases rel
					INNER JOIN release_files rf ON rf.releases_id = rel.id
					WHERE predb_id = 0
					AND rf.crc32 != \'\'
					AND rf.crc32 IS NOT NULL'
            );
            $cats = 2;
            $preId = true;
        } else {
            $query = sprintf(
                '
					SELECT rf.crc32 AS textstring, rf.name AS filename, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id, rel.size as relsize,
						rf.releases_id AS fileid, rel.id AS releases_id
					FROM releases rel
					INNER JOIN release_files rf ON rf.releases_id = rel.id
					WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
					AND rel.predb_id = 0
					AND rel.proc_crc32 = %d
					AND rf.crc32 != \'\'
					AND rf.crc32 IS NOT NULL',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                self::PROC_CRC_NONE
            );
        }

        $releases = $this->_getReleases($time, $cats, $query);
        $total = $releases->count();
        if ($total > 0) {
            $this->_totalReleases = $total;
            $this->colorCLI->info(number_format($total).' CRC32\'s to process.');

            // Group by release to handle multiple CRC32 values per release
            $releasesCrc = [];
            foreach ($releases as $release) {
                $releaseId = $release->releases_id;
                if (! isset($releasesCrc[$releaseId])) {
                    $releasesCrc[$releaseId] = [
                        'release' => $release,
                        'crcs' => [],
                    ];
                }
                // Prioritize CRC from main files (video, RAR) over others
                if (! empty($release->textstring)) {
                    $priority = $this->getCrcPriority($release->filename ?? '');
                    $releasesCrc[$releaseId]['crcs'][$priority][] = $release->textstring;
                }
            }

            foreach ($releasesCrc as $releaseId => $data) {
                $this->reset();
                $this->checked++;

                // Sort CRCs by priority and try each
                ksort($data['crcs']);
                foreach ($data['crcs'] as $crcs) {
                    foreach ($crcs as $crc) {
                        $release = clone $data['release'];
                        $release->textstring = $crc;

                        $this->checkName($release, $echo, $type, $nameStatus, $show, $preId);

                        if ($this->matched) {
                            break 2;
                        }
                    }
                }

                $this->_echoRenamed($show);
            }

            $this->_echoFoundCount($echo, ' crc32\'s');
        } else {
            $this->colorCLI->info('Nothing to fix.');
        }
    }

    /**
     * Get priority for CRC matching based on filename.
     *
     * Lower number = higher priority.
     */
    protected function getCrcPriority(string $filename): int
    {
        $lower = strtolower($filename);

        // Skip sample/proof files - lowest priority
        if (preg_match('/[\.\-_](sample|proof)[\.\-_]/i', $filename)) {
            return 100;
        }

        // Video files - highest priority
        if (preg_match('/\.(mkv|avi|mp4|m4v|wmv|divx|ts|m2ts)$/i', $filename)) {
            return 1;
        }

        // Main RAR files - high priority
        if (preg_match('/\.rar$/i', $filename) && ! preg_match('/\.part\d+\.rar$/i', $filename)) {
            return 2;
        }

        // First split RAR
        if (preg_match('/\.part0*1\.rar$/i', $filename)) {
            return 3;
        }

        // Other RAR parts
        if (preg_match('/\.(rar|r\d{2,3})$/i', $filename)) {
            return 4;
        }

        // NFO files
        if (str_ends_with($lower, '.nfo')) {
            return 5;
        }

        return 50;
    }

    /**
     * Attempts to fix XXX release names using the File name.
     *
     *
     *
     * @throws \Exception
     */
    public function fixXXXNamesWithFiles($time, $echo, $cats, $nameStatus, $show): void
    {
        $this->_echoStartMessage($time, 'file names');
        $type = 'Filenames, ';

        if ($cats === 3) {
            $query = sprintf(
                '
					SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
						rf.releases_id AS fileid, rel.id AS releases_id
					FROM releases rel
					INNER JOIN release_files rf ON rf.releases_id = rel.id
					WHERE predb_id = 0'
            );
            $cats = 2;
        } else {
            $query = sprintf(
                '
					SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
						rf.releases_id AS fileid, rel.id AS releases_id
					FROM releases rel
					INNER JOIN release_files rf ON rf.releases_id = rel.id
					WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
					AND rel.predb_id = 0
					AND rf.name LIKE %s',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                escapeString('%SDPORN%')
            );
        }

        $releases = $this->_getReleases($time, $cats, $query);
        $total = $releases->count();
        if ($total > 0) {
            $this->_totalReleases = $total;
            $this->colorCLI->info(number_format($total).' xxx file names to process.');

            foreach ($releases as $release) {
                $this->reset();
                $this->xxxNameCheck($release, $echo, $type, $nameStatus, $show);
                $this->checked++;
                $this->_echoRenamed($show);
            }
            $this->_echoFoundCount($echo, ' files');
        } else {
            $this->colorCLI->info('Nothing to fix.');
        }
    }

    /**
     * Attempts to fix release names using the SRR filename.
     *
     * SRR (Scene Release Renamer) files contain the original scene release name,
     * making them highly reliable for name fixing.
     *
     * @throws \Exception
     */
    public function fixNamesWithSrr($time, $echo, $cats, $nameStatus, $show): void
    {
        $this->_echoStartMessage($time, 'SRR file names');
        $type = 'SRR, ';

        if ($cats === 3) {
            $query = sprintf(
                '
					SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
						rf.releases_id AS fileid, rel.id AS releases_id
					FROM releases rel
					INNER JOIN release_files rf ON rf.releases_id = rel.id
					WHERE predb_id = 0
					AND (rf.name LIKE %s OR rf.name LIKE %s)',
                escapeString('%.srr'),
                escapeString('%.srs')
            );
            $cats = 2;
        } else {
            $query = sprintf(
                '
					SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
						rf.releases_id AS fileid, rel.id AS releases_id
					FROM releases rel
					INNER JOIN release_files rf ON rf.releases_id = rel.id
					WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
					AND rel.predb_id = 0
					AND (rf.name LIKE %s OR rf.name LIKE %s)
					AND rel.proc_srr = %d',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                escapeString('%.srr'),
                escapeString('%.srs'),
                self::PROC_SRR_NONE
            );
        }

        $releases = $this->_getReleases($time, $cats, $query);
        $total = $releases->count();
        if ($total > 0) {
            $this->_totalReleases = $total;
            $this->colorCLI->info(number_format($total).' srr file extensions to process.');

            foreach ($releases as $release) {
                $this->reset();
                $this->srrNameCheck($release, $echo, $type, $nameStatus, $show);
                $this->checked++;
                $this->_echoRenamed($show);
            }
            $this->_echoFoundCount($echo, ' files');
        } else {
            $this->colorCLI->info('Nothing to fix.');
        }
    }

    /**
     * Attempts to fix release names using the Par2 File.
     *
     * @param  $time  1: 24 hours, 2: no time limit
     * @param  $echo  1: change the name, anything else: preview of what could have been changed.
     * @param  $cats  1: other categories, 2: all categories
     *
     * @throws \Exception
     */
    public function fixNamesWithPar2($time, $echo, $cats, $nameStatus, $show, NNTP $nntp): void
    {
        $this->_echoStartMessage($time, 'par2 files');

        if ($cats === 3) {
            $query = sprintf(
                '
					SELECT rel.id AS releases_id, rel.guid, rel.groups_id, rel.fromname
					FROM releases rel
					WHERE rel.predb_id = 0'
            );
            $cats = 2;
        } else {
            $query = sprintf(
                '
					SELECT rel.id AS releases_id, rel.guid, rel.groups_id, rel.fromname
					FROM releases rel
					WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
					AND rel.predb_id = 0
					AND rel.proc_par2 = %d',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                self::PROC_PAR2_NONE
            );
        }

        $releases = $this->_getReleases($time, $cats, $query);

        $total = $releases->count();
        if ($total > 0) {
            $this->_totalReleases = $total;

            $this->colorCLI->info(number_format($total).' releases to process.');
            $Nfo = new Nfo;
            $nzbContents = new NZBContents;

            foreach ($releases as $release) {
                if ($nzbContents->checkPAR2($release->guid, $release->releases_id, $release->groups_id, $nameStatus, $show)) {
                    $this->fixed++;
                }

                $this->checked++;
                $this->_echoRenamed($show);
            }
            $this->_echoFoundCount($echo, ' files');
        } else {
            $this->colorCLI->info('Nothing to fix.');
        }
    }

    /**
     * @throws \Exception
     */
    public function fixNamesWithMedia($time, $echo, $cats, $nameStatus, $show): void
    {
        $type = 'UID, ';

        $this->_echoStartMessage($time, 'mediainfo Unique_IDs');

        // Re-check all releases we haven't matched to a PreDB
        if ($cats === 3) {
            $query = sprintf(
                '
				SELECT
					rel.id AS releases_id, rel.size AS relsize, rel.groups_id, rel.fromname, rel.categories_id,
					rel.name, rel.name AS textstring, rel.predb_id, rel.searchname,
					ru.unique_id AS uid
				FROM releases rel
				LEFT JOIN media_infos ru ON ru.releases_id = rel.id
				WHERE ru.releases_id IS NOT NULL
				AND rel.predb_id = 0'
            );
            $cats = 2;
            // Otherwise check only releases we haven't renamed and checked uid before in Misc categories
        } else {
            $query = sprintf(
                '
				SELECT
					rel.id AS releases_id, rel.size AS relsize, rel.groups_id, rel.fromname, rel.categories_id,
					rel.name, rel.name AS textstring, rel.predb_id, rel.searchname,
					ru.unique_id AS uid
				FROM releases rel
				LEFT JOIN media_infos ru ON ru.releases_id = rel.id
				WHERE ru.releases_id IS NOT NULL
				AND (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
				AND rel.predb_id = 0
				AND rel.proc_uid = %d',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                self::PROC_UID_NONE
            );
        }

        $releases = $this->_getReleases($time, $cats, $query);
        $total = $releases->count();
        if ($total > 0) {
            $this->_totalReleases = $total;
            $this->colorCLI->info(number_format($total).' unique ids to process.');
            foreach ($releases as $rel) {
                $this->checked++;
                $this->reset();
                $this->uidCheck($rel, $echo, $type, $nameStatus, $show);
                $this->_echoRenamed($show);
            }
            $this->_echoFoundCount($echo, ' UID\'s');
        } else {
            $this->colorCLI->info('Nothing to fix.');
        }
    }

    /**
     * @throws \Exception
     */
    public function fixNamesWithMediaMovieName($time, $echo, $cats, $nameStatus, $show): void
    {
        $type = 'Mediainfo, ';

        $this->_echoStartMessage($time, 'Mediainfo movie_name');

        // Re-check all releases we haven't matched to a PreDB
        if ($cats === 3) {
            $query = sprintf(
                '
				SELECT rel.id AS releases_id, rel.name, rel.name AS textstring, rel.predb_id, rel.searchname, rel.fromname, rel.groups_id, rel.categories_id, rel.id AS releases_id, rf.movie_name as movie_name
				FROM releases rel
				INNER JOIN media_infos rf ON rf.releases_id = rel.id
                WHERE rel.predb_id = 0'
            );
            $cats = 2;
            // Otherwise check only releases we haven't renamed and checked uid before in Misc categories
        } else {
            $query = sprintf(
                '
				SELECT rel.id AS releases_id, rel.name, rel.name AS textstring, rel.predb_id, rel.searchname, rel.fromname, rel.groups_id, rel.categories_id, rel.id AS releases_id, rf.movie_name as movie_name, rf.file_name as file_name
				FROM releases rel
				INNER JOIN media_infos rf ON rf.releases_id = rel.id
				WHERE rel.isrenamed = %d
                AND rel.predb_id = 0',
                self::IS_RENAMED_NONE
            );
            if ($cats === 2) {
                $query .= PHP_EOL.'AND rel.categories_id IN ('.Category::OTHER_MISC.','.Category::OTHER_HASHED.')';
            }
        }

        $releases = $this->_getReleases($time, $cats, $query);
        $total = $releases->count();
        if ($total > 0) {
            $this->_totalReleases = $total;
            $this->colorCLI->info(number_format($total).' mediainfo movie names to process.');
            foreach ($releases as $rel) {
                $this->checked++;
                $this->reset();
                $this->mediaMovieNameCheck($rel, $echo, $type, $nameStatus, $show);
                $this->_echoRenamed($show);
            }
            $this->_echoFoundCount($echo, ' MediaInfo\'s');
        } else {
            $this->colorCLI->info('Nothing to fix.');
        }
    }

    /**
     * Attempts to fix release names using the par2 hash_16K block.
     *
     *
     *
     * @throws \Exception
     */
    public function fixNamesWithParHash($time, $echo, $cats, $nameStatus, $show): void
    {
        $type = 'PAR2 hash, ';

        $this->_echoStartMessage($time, 'PAR2 hash_16K');

        // Re-check all releases we haven't matched to a PreDB
        if ($cats === 3) {
            $query = sprintf(
                '
				SELECT
					rel.id AS releases_id, rel.size AS relsize, rel.groups_id, rel.fromname, rel.categories_id,
					rel.name, rel.name AS textstring, rel.predb_id, rel.searchname,
					IFNULL(ph.hash, \'\') AS hash
				FROM releases rel
				LEFT JOIN par_hashes ph ON ph.releases_id = rel.id
				WHERE ph.hash != \'\'
				AND rel.predb_id = 0'
            );
            $cats = 2;
            // Otherwise check only releases we haven't renamed and checked their par2 hash_16K before in Misc categories
        } else {
            $query = sprintf(
                '
				SELECT
					rel.id AS releases_id, rel.size AS relsize, rel.groups_id, rel.fromname, rel.categories_id,
					rel.name, rel.name AS textstring, rel.predb_id, rel.searchname,
					IFNULL(ph.hash, \'\') AS hash
				FROM releases rel
				LEFT JOIN par_hashes ph ON ph.releases_id = rel.id
				WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
				AND rel.predb_id = 0
				AND ph.hash != \'\'
				AND rel.proc_hash16k = %d',
                self::IS_RENAMED_NONE,
                Category::OTHER_MISC,
                Category::OTHER_HASHED,
                self::PROC_HASH16K_NONE
            );
        }

        $releases = $this->_getReleases($time, $cats, $query);

        $total = $releases->count();
        if ($total > 0) {
            $this->_totalReleases = $total;
            $this->colorCLI->info(number_format($total).' hash_16K to process.');
            foreach ($releases as $rel) {
                $this->checked++;
                $this->reset();
                $this->hashCheck($rel, $echo, $type, $nameStatus, $show);
                $this->_echoRenamed($show);
            }
            $this->_echoFoundCount($echo, ' hashes');
        } else {
            $this->colorCLI->info('Nothing to fix.');
        }
    }

    /**
     * @return false|\Illuminate\Database\Eloquent\Collection
     */
    protected function _getReleases($time, $cats, $query, int $limit = 0): \Illuminate\Database\Eloquent\Collection|bool
    {
        $releases = false;
        $queryLimit = ($limit === 0) ? '' : ' LIMIT '.$limit;
        // 24 hours, other cats
        if ($time === 1 && $cats === 1) {
            $releases = Release::fromQuery($query.$this->timeother.$queryLimit);
        } // 24 hours, all cats
        if ($time === 1 && $cats === 2) {
            $releases = Release::fromQuery($query.$this->timeall.$queryLimit);
        } // other cats
        if ($time === 2 && $cats === 1) {
            $releases = Release::fromQuery($query.$this->fullother.$queryLimit);
        } // all cats
        if ($time === 2 && $cats === 2) {
            $releases = Release::fromQuery($query.$this->fullall.$queryLimit);
        }

        return $releases;
    }

    /**
     * Echo the amount of releases that found a new name.
     *
     * @param  bool|int  $echo  1: change the name, anything else: preview of what could have been changed.
     * @param  string  $type  The function type that found the name.
     */
    protected function _echoFoundCount(bool|int $echo, string $type): void
    {
        if ($echo === true) {
            $this->colorCLI->info(
                PHP_EOL.
                number_format($this->fixed).
                ' releases have had their names changed out of: '.
                number_format($this->checked).
                $type.'.'
            );
        } else {
            $this->colorCLI->info(
                PHP_EOL.
                number_format($this->fixed).
                ' releases could have their names changed. '.
                number_format($this->checked).
                $type.' were checked.'
            );
        }
    }

    /**
     * @param  int  $time  1: 24 hours, 2: no time limit
     * @param  string  $type  The function type.
     */
    protected function _echoStartMessage(int $time, string $type): void
    {
        $this->colorCLI->info(
            sprintf(
                'Fixing search names %s using %s.',
                ($time === 1 ? 'in the past 6 hours' : 'since the beginning'),
                $type
            )
        );
    }

    protected function _echoRenamed(int $show): void
    {
        if ($this->checked % 500 === 0 && $show === 1) {
            $this->colorCLI->alternate(PHP_EOL.number_format($this->checked).' files processed.'.PHP_EOL);
        }

        if ($show === 2) {
            $this->colorCLI->info(
                'Renamed Releases: ['.
                number_format($this->fixed).
                '] '.
                (new ConsoleTools)->percentString($this->checked, $this->_totalReleases)
            );
        }
    }

    /**
     * Update the release with the new information.
     *
     * @throws \Exception
     */
    public function updateRelease($release, $name, $method, $echo, $type, $nameStatus, $show, $preId = 0): void
    {
        if (\is_array($release)) {
            $release = (object) $release;
        }
        // If $release does not have a releases_id, we should add it.
        if (! isset($release->releases_id)) {
            $release->releases_id = $release->id;
        }
        if ($this->relid !== $release->releases_id) {
            $newName = (new ReleaseCleaning)->fixerCleaner($name);
            // Normalize and sanity-check candidate for non-trusted sources
            $newName = $this->normalizeCandidateTitle($newName);

            // Determine if the source is trusted enough to bypass plausibility checks
            $trustedSource = (
                (! empty($preId) && (int) $preId > 0) ||
                str_starts_with((string) $type, 'PreDB') ||
                str_starts_with((string) $type, 'PreDb') ||
                $type === 'UID, ' ||
                $type === 'PAR2 hash, ' ||
                $type === 'CRC32, ' ||
                $type === 'SRR, ' ||
                stripos((string) $method, 'Title Match') !== false ||
                stripos((string) $method, 'file matched source') !== false ||
                stripos((string) $method, 'PreDb') !== false ||
                stripos((string) $method, 'preDB') !== false
            );

            if (! $trustedSource && ! $this->isPlausibleReleaseTitle($newName)) {
                // Skip low-quality rename candidates for untrusted sources (e.g., Filenames, PAR2, generic NFO)
                $this->done = true;

                return;
            }

            if (strtolower($newName) !== strtolower($release->searchname)) {
                $this->matched = true;
                $this->relid = (int) $release->releases_id;

                $determinedCategory = $this->category->determineCategory($release->groups_id, $newName, ! empty($release->fromname) ? $release->fromname : '');

                if ($type === 'PAR2, ') {
                    $newName = ucwords($newName);
                    if (preg_match('/(.+?)\.[a-z0-9]{2,3}(PAR2)?$/i', $name, $hit)) {
                        $newName = $hit[1];
                    }
                }

                $this->fixed++;

                // Split on path separator backslash to strip any path
                $newName = explode('\\', $newName);
                $newName = preg_replace(['/^[=_.:\s-]+/', '/[=_.:\s-]+$/'], '', $newName[0]);

                if ($this->echoOutput && $show) {
                    $groupName = UsenetGroup::getNameByID($release->groups_id);
                    $oldCatName = Category::getNameByID($release->categories_id);
                    $newCatName = Category::getNameByID($determinedCategory['categories_id']);

                    if ($type === 'PAR2, ') {
                        echo PHP_EOL;
                    }

                    echo PHP_EOL;

                    // Display release information with clean formatting
                    $this->colorCLI->primary('Release Information:');

                    echo '  ' . $this->colorCLI->headerOver('New name:   ') . $this->colorCLI->primary(substr($newName, 0, 100)) . PHP_EOL;
                    echo '  ' . $this->colorCLI->headerOver('Old name:   ') . $this->colorCLI->primary(substr($release->searchname, 0, 100)) . PHP_EOL;
                    echo '  ' . $this->colorCLI->headerOver('Use name:   ') . $this->colorCLI->primary(substr($release->name, 0, 100)) . PHP_EOL;
                    echo PHP_EOL;

                    echo '  ' . $this->colorCLI->headerOver('New cat:    ') . $this->colorCLI->primary($newCatName) . PHP_EOL;
                    echo '  ' . $this->colorCLI->headerOver('Old cat:    ') . $this->colorCLI->primary($oldCatName) . PHP_EOL;
                    echo '  ' . $this->colorCLI->headerOver('Group:      ') . $this->colorCLI->primary($groupName) . PHP_EOL;
                    echo PHP_EOL;

                    echo '  ' . $this->colorCLI->headerOver('Method:     ') . $this->colorCLI->primary($type.$method) . PHP_EOL;
                    echo '  ' . $this->colorCLI->headerOver('Release ID: ') . $this->colorCLI->primary($release->releases_id) . PHP_EOL;

                    if (! empty($release->filename)) {
                        echo '  ' . $this->colorCLI->headerOver('Filename:   ') . $this->colorCLI->primary(substr($release->filename, 0, 100)) . PHP_EOL;
                    }

                    if ($type !== 'PAR2, ') {
                        echo PHP_EOL;
                    }
                }

                $newTitle = substr($newName, 0, 299);

                if ($echo === true) {
                    if ($nameStatus === true) {
                        $status = '';
                        switch ($type) {
                            case 'NFO, ':
                                $status = ['isrenamed' => 1, 'iscategorized' => 1, 'proc_nfo' => 1];
                                break;
                            case 'PAR2, ':
                                $status = ['isrenamed' => 1, 'iscategorized' => 1, 'proc_par2' => 1];
                                break;
                            case 'Filenames, ':
                            case 'file matched source: ':
                                $status = ['isrenamed' => 1, 'iscategorized' => 1, 'proc_files' => 1];
                                break;
                            case 'SHA1, ':
                            case 'MD5, ':
                                $status = ['isrenamed' => 1, 'iscategorized' => 1, 'dehashstatus' => 1];
                                break;
                            case 'PreDB FT Exact, ':
                                $status = ['isrenamed' => 1, 'iscategorized' => 1];
                                break;
                            case 'sorter, ':
                                $status = ['isrenamed' => 1, 'iscategorized' => 1, 'proc_sorter' => 1];
                                break;
                            case 'UID, ':
                            case 'Mediainfo, ':
                                $status = ['isrenamed' => 1, 'iscategorized' => 1, 'proc_uid' => 1];
                                break;
                            case 'PAR2 hash, ':
                                $status = ['isrenamed' => 1, 'iscategorized' => 1, 'proc_hash16k' => 1];
                                break;
                            case 'SRR, ':
                                $status = ['isrenamed' => 1, 'iscategorized' => 1, 'proc_srr' => 1];
                                break;
                            case 'CRC32, ':
                                $status = ['isrenamed' => 1, 'iscategorized' => 1, 'proc_crc32' => 1];
                                break;
                        }

                        $updateColumns = [
                            'videos_id' => 0,
                            'tv_episodes_id' => 0,
                            'imdbid' => '',
                            'musicinfo_id' => '',
                            'consoleinfo_id' => '',
                            'bookinfo_id' => '',
                            'anidbid' => '',
                            'predb_id' => $preId,
                            'searchname' => $newTitle,
                            'categories_id' => $determinedCategory['categories_id'],
                        ];

                        if ($status !== '') {
                            foreach ($status as $key => $stat) {
                                $updateColumns = Arr::add($updateColumns, $key, $stat);
                            }
                        }

                        Release::query()
                            ->where('id', $release->releases_id)
                            ->update($updateColumns);

                        if (config('nntmux.elasticsearch_enabled') === true) {
                            $this->elasticsearch->updateRelease($release->releases_id);
                        } else {
                            $this->manticore->updateRelease($release->releases_id);
                        }
                    } else {
                        Release::query()
                            ->where('id', $release->releases_id)
                            ->update(
                                [
                                    'videos_id' => 0,
                                    'tv_episodes_id' => 0,
                                    'imdbid' => null,
                                    'musicinfo_id' => null,
                                    'consoleinfo_id' => null,
                                    'bookinfo_id' => null,
                                    'anidbid' => null,
                                    'predb_id' => $preId,
                                    'searchname' => $newTitle,
                                    'categories_id' => $determinedCategory['categories_id'],
                                    'iscategorized' => 1,
                                ]
                            );

                        if (config('nntmux.elasticsearch_enabled') === true) {
                            $this->elasticsearch->updateRelease($release->releases_id);
                        } else {
                            $this->manticore->updateRelease($release->releases_id);
                        }
                    }
                }
            }
        }
        $this->done = true;
    }

    /**
     * Echo an updated release name to CLI.
     *
     *
     * @static
     *
     * @void
     */
    public static function echoChangedReleaseName(
        array $data =
        [
            'new_name' => '',
            'old_name' => '',
            'new_category' => '',
            'old_category' => '',
            'group' => '',
            'releases_id' => 0,
            'method' => '',
        ]
    ): void {
        $colorCLI = new ColorCLI;
        echo PHP_EOL;

        $colorCLI->info('New name:     ').$colorCLI->primaryOver($data['new_name']).
            $colorCLI->info('Old name:     ').$colorCLI->primaryOver($data['old_name']).
            $colorCLI->info('New category: ').$colorCLI->primaryOver($data['new_category']).
            $colorCLI->info('Old category: ').$colorCLI->primaryOver($data['old_category']).
            $colorCLI->info('Group:        ').$colorCLI->primaryOver($data['group']).
            $colorCLI->info('Releases ID:   ').$colorCLI->primaryOver($data['releases_id']).
            $colorCLI->info('Method:       ').$colorCLI->primaryOver($data['method']);
    }

    /**
     * Match a PreDB title to a release name or searchname using an exact full-text match.
     *
     *
     * @throws \Exception
     */
    public function matchPredbFT($pre, $echo, $nameStatus, $show): int
    {
        $matching = $total = 0;

        $join = $this->_preFTsearchQuery($pre['title']);

        if ($join === '') {
            return $matching;
        }

        // Find release matches with fulltext and then identify exact matches with cleaned LIKE string
        $res = Release::fromQuery(
            sprintf(
                '
				SELECT r.id AS releases_id, r.name, r.searchname,
				r.fromname, r.groups_id, r.categories_id
				FROM releases r
				WHERE r.id IN (%s)
				AND r.predb_id = 0',
                $join
            )
        );

        if ($res->isNotEmpty()) {
            $total = $res->count();
        }

        // Run if row count is positive, but do not run if row count exceeds 10 (as this is likely a failed title match)
        if ($total > 0 && $total <= 15) {
            foreach ($res as $row) {
                if ($pre['title'] !== $row->searchname) {
                    $this->updateRelease($row, $pre['title'], 'Title Match source: '.$pre['source'], $echo, 'PreDB FT Exact, ', $nameStatus, $show, $pre['predb_id']);
                    $matching++;
                }
            }
        } elseif ($total >= 16) {
            $matching = -1;
        }

        return $matching;
    }

    protected function _preFTsearchQuery($preTitle): string
    {
        $join = '';

        if (\strlen($preTitle) >= 15 && preg_match(self::PREDB_REGEX, $preTitle)) {
            $titleMatch = $this->manticore->searchIndexes('releases_rt', $preTitle, ['name', 'searchname', 'filename']);
            if (! empty($titleMatch)) {
                $join = implode(',', Arr::get($titleMatch, 'id'));
            }
        }

        return $join;
    }

    /**
     * Retrieves releases and their file names to attempt PreDB matches
     * Runs in a limited mode based on arguments passed or a full mode broken into chunks of entire DB.
     *
     * @param  array  $args  The CLI script arguments
     *
     * @throws \Exception
     */
    public function getPreFileNames(array $args = []): void
    {
        $show = isset($args[2]) && $args[2] === 'show';

        if (isset($args[1]) && is_numeric($args[1])) {
            $limit = 'LIMIT '.$args[1];
            $orderBy = 'ORDER BY r.id DESC';
        } else {
            $orderBy = 'ORDER BY r.id ASC';
            $limit = 'LIMIT 1000000';
        }

        $this->colorCLI->info(PHP_EOL.'Match PreFiles '.$args[1].' Started at '.now());
        $this->colorCLI->info('Matching predb filename to cleaned release_files.name.');

        $counter = $counted = 0;
        $timeStart = now();

        $query = Release::fromQuery(
            sprintf(
                "
					SELECT r.id AS releases_id, r.name, r.searchname,
						r.fromname, r.groups_id, r.categories_id,
						GROUP_CONCAT(rf.name ORDER BY LENGTH(rf.name) DESC SEPARATOR '||') AS filename
					FROM releases r
					INNER JOIN release_files rf ON r.id = rf.releases_id
					WHERE rf.name IS NOT NULL
					AND r.predb_id = 0
					AND r.categories_id IN (%s)
					AND r.isrenamed = 0
					GROUP BY r.id
					%s %s",
                implode(',', Category::OTHERS_GROUP),
                $orderBy,
                $limit
            )
        );

        if ($query->isNotEmpty()) {
            $total = $query->count();

            if ($total > 0) {
                $this->colorCLI->info(PHP_EOL.number_format($total).' releases to process.');

                foreach ($query as $row) {
                    $success = $this->matchPreDbFiles($row, true, 1, $show);
                    if ($success === 1) {
                        $counted++;
                    }
                    if ($show === 0) {
                        $this->colorCLI->info('Renamed Releases: ['.number_format($counted).'] '.(new ConsoleTools)->percentString(++$counter, $total));
                    }
                }
                $this->colorCLI->info(PHP_EOL.'Renamed '.number_format($counted).' releases in '.now()->diffInSeconds($timeStart, true).' seconds'.'.');
            } else {
                $this->colorCLI->info('Nothing to do.');
            }
        }
    }

    /**
     * Match a release filename to a PreDB filename or title.
     *
     * Enhanced with:
     * - Better RAR archive filename handling
     * - Multiple file attempt with prioritization
     * - Improved scene naming pattern recognition
     *
     * @throws \Exception
     */
    public function matchPreDbFiles($release, bool $echo, $nameStatus, bool $show): int
    {

        $matching = 0;

        // Split files and prioritize them for matching
        $files = explode('||', $release->filename);
        $prioritizedFiles = $this->prioritizeFilesForPreDbMatch($files);

        foreach ($prioritizedFiles as $fileName) {
            $this->_fileName = $fileName;
            $this->_cleanMatchFiles();

            // Skip if the cleaned filename is too short or empty
            if (empty($this->_fileName) || strlen($this->_fileName) < 8) {
                continue;
            }

            $preMatch = $this->preMatch($this->_fileName);
            if ($preMatch[0] === true) {
                if (config('nntmux.elasticsearch_enabled') === true) {
                    $results = $this->elasticsearch->searchPreDb($preMatch[1]);
                } else {
                    $results = Arr::get($this->manticore->searchIndexes('predb_rt', $preMatch[1], ['filename', 'title']), 'data');
                }
                if (! empty($results)) {
                    foreach ($results as $result) {
                        if (! empty($result)) {
                            $preFtMatch = $this->preMatch($result['filename']);
                            if ($preFtMatch[0] === true) {
                                $this->_fileName = $result['filename'];
                                $release->filename = $this->_fileName;
                                if ($result['title'] !== $release->searchname) {
                                    $this->updateRelease($release, $result['title'], 'file matched source: '.$result['source'], $echo, 'PreDB file match, ', $nameStatus, $show);
                                } else {
                                    $this->_updateSingleColumn('predb_id', $result['id'], $release->releases_id);
                                }
                                $matching++;

                                return $matching; // Found a match, stop processing
                            }
                        }
                    }
                }
            }

            // Also try matching the file directly to PreDB title without preMatch validation
            // This helps with files that have slight naming variations
            if ($matching === 0 && strlen($this->_fileName) >= 12) {
                $cleanedForTitle = $this->cleanFileForTitleMatch($this->_fileName);
                if (strlen($cleanedForTitle) >= 12) {
                    if (config('nntmux.elasticsearch_enabled') === true) {
                        $results = $this->elasticsearch->searchPreDb($cleanedForTitle);
                    } else {
                        $results = Arr::get($this->manticore->searchIndexes('predb_rt', $cleanedForTitle, ['title']), 'data');
                    }

                    if (! empty($results)) {
                        foreach ($results as $result) {
                            if (! empty($result) && ! empty($result['title'])) {
                                // Verify the match quality
                                if ($this->isGoodPreDbMatch($cleanedForTitle, $result['title'])) {
                                    if ($result['title'] !== $release->searchname) {
                                        $this->updateRelease($release, $result['title'], 'file matched title: '.$result['source'], $echo, 'PreDB title match, ', $nameStatus, $show);
                                        $matching++;

                                        return $matching;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $matching;
    }

    /**
     * Prioritize files for PreDB matching.
     *
     * Files are ordered by their likelihood of containing a good release name:
     * 1. Main RAR files (not split parts)
     * 2. SRR files
     * 3. First split RAR parts
     * 4. Video files
     * 5. Other files
     */
    protected function prioritizeFilesForPreDbMatch(array $files): array
    {
        $mainRar = [];
        $srrFiles = [];
        $firstParts = [];
        $videoFiles = [];
        $otherFiles = [];

        foreach ($files as $file) {
            $lowerFile = strtolower($file);

            // Skip sample/proof files
            if (preg_match('/[\.\-_](sample|proof)[\.\-_]/i', $file)) {
                continue;
            }

            // SRR files - highest priority (contain original release name)
            if (str_ends_with($lowerFile, '.srr') || str_ends_with($lowerFile, '.srs')) {
                $srrFiles[] = $file;
            }
            // Main RAR (not split)
            elseif (preg_match('/\.rar$/i', $file) && ! preg_match('/\.part\d+\.rar$/i', $file)) {
                $mainRar[] = $file;
            }
            // First part of split RAR
            elseif (preg_match('/\.part0*1\.rar$/i', $file)) {
                $firstParts[] = $file;
            }
            // Video files
            elseif (preg_match('/\.(mkv|avi|mp4|m4v|wmv|divx|ts|m2ts)$/i', $file)) {
                $videoFiles[] = $file;
            } else {
                $otherFiles[] = $file;
            }
        }

        // Sort each group by length (longer names are often more descriptive)
        usort($mainRar, fn($a, $b) => strlen($b) - strlen($a));
        usort($videoFiles, fn($a, $b) => strlen($b) - strlen($a));

        return array_merge($srrFiles, $mainRar, $firstParts, $videoFiles, $otherFiles);
    }

    /**
     * Clean a filename for PreDB title matching.
     *
     * Removes file extensions and common suffixes while preserving the release name.
     */
    protected function cleanFileForTitleMatch(string $filename): string
    {
        // Remove file extension
        $clean = preg_replace('/\.(mkv|avi|mp4|m4v|wmv|mpg|mpeg|mov|ts|m2ts|vob|divx|flv|nfo|sfv|nzb|srr|srs|rar|r\d{2,4}|zip|7z|par2?|vol\d+[\+\-]\d+|\d{3})$/i', '', $filename);

        // Remove part/volume indicators
        $clean = preg_replace('/[\.\-_]?(part|vol|cd|dvd|disc|disk)\d+$/i', '', $clean);

        // Remove sample/proof indicators
        $clean = preg_replace('/[\.\-_](sample|proof|subs?)$/i', '', $clean);

        return trim($clean, " \t\n\r\0\x0B.-_");
    }

    /**
     * Check if a PreDB match is of good quality.
     *
     * Compares the search term with the PreDB title to ensure they're similar enough.
     */
    protected function isGoodPreDbMatch(string $searchTerm, string $preDbTitle): bool
    {
        // Normalize both strings for comparison
        $searchNorm = strtolower(preg_replace('/[.\-_\s]+/', '', $searchTerm));
        $titleNorm = strtolower(preg_replace('/[.\-_\s]+/', '', $preDbTitle));

        // Check if one is a substring of the other or if they're very similar
        if (str_contains($titleNorm, $searchNorm) || str_contains($searchNorm, $titleNorm)) {
            return true;
        }

        // Calculate similarity
        $similarity = 0;
        similar_text($searchNorm, $titleNorm, $similarity);

        // Accept if similarity is high enough (80%+)
        return $similarity >= 80;
    }

    /**
     * Extract a scene release name from a RAR archive filename or path.
     *
     * Scene releases typically follow the pattern: Release.Name-GROUP
     * This method attempts to extract the release name from various RAR naming conventions.
     *
     * @param  string  $filename  The RAR archive filename or path
     * @return string|null The extracted release name or null if not found
     */
    protected function extractReleaseNameFromRar(string $filename): ?string
    {
        // Extract filename from path
        if (preg_match('/[\\\\\/]([^\\\\\/]+)$/', $filename, $match)) {
            $filename = $match[1];
        }

        // Remove RAR extensions
        $baseName = preg_replace('/\.(rar|r\d{2,4}|part\d+\.rar|\d{3})$/i', '', $filename);

        // Check if the base name looks like a scene release name
        if (preg_match(self::PREDB_REGEX, $baseName)) {
            // Clean up any remaining artifacts
            $baseName = preg_replace('/[._-]?(sample|proof|subs?)$/i', '', $baseName);
            $baseName = trim($baseName, '.-_');

            if (strlen($baseName) >= 10) {
                return $baseName;
            }
        }

        // Try to extract from common RAR naming patterns
        // Pattern: releasename-group.rar or releasename.group.rar
        if (preg_match('/^([a-z0-9][a-z0-9._-]+[a-z0-9])\-([a-z0-9]{2,15})$/i', $baseName, $match)) {
            return $match[1] . '-' . $match[2];
        }

        return null;
    }

    /**
     * Try to find a release name from multiple RAR files.
     *
     * Analyzes multiple RAR files from a release to determine the most likely release name.
     *
     * @param  array  $rarFiles  Array of RAR filenames
     * @return string|null The most likely release name or null if not found
     */
    protected function findReleaseNameFromRarFiles(array $rarFiles): ?string
    {
        $candidates = [];

        foreach ($rarFiles as $file) {
            // Skip sample/proof files
            if (preg_match('/[\._-](sample|proof)[\._-]/i', $file)) {
                continue;
            }

            $extracted = $this->extractReleaseNameFromRar($file);
            if ($extracted !== null) {
                // Count occurrences of each candidate
                if (!isset($candidates[$extracted])) {
                    $candidates[$extracted] = 0;
                }
                $candidates[$extracted]++;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Return the most common candidate (in case of ties, prefer longer names)
        arsort($candidates);
        $topCount = reset($candidates);
        $topCandidates = array_filter($candidates, fn($count) => $count === $topCount);

        // If multiple candidates have the same count, prefer the longest one
        $best = null;
        foreach (array_keys($topCandidates) as $candidate) {
            if ($best === null || strlen($candidate) > strlen($best)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Detect if a filename looks like it's from a scene release based on naming conventions.
     *
     * @param  string  $filename  The filename to check
     * @return bool True if the filename follows scene naming conventions
     */
    protected function looksLikeSceneRelease(string $filename): bool
    {
        // Remove file extension for checking
        $baseName = preg_replace('/\.[a-z0-9]{2,4}$/i', '', $filename);

        // Scene releases typically have:
        // 1. Words separated by dots or underscores
        // 2. A group name at the end after a hyphen
        // 3. Common scene tags (720p, 1080p, x264, etc.)

        // Check for group suffix pattern: -GROUPNAME
        if (!preg_match('/\-[A-Za-z0-9]{2,15}$/', $baseName)) {
            return false;
        }

        // Check for scene-style word separation (dots, underscores, hyphens)
        if (!preg_match('/[._-]/', $baseName)) {
            return false;
        }

        // Check for common scene tags
        $sceneTags = [
            '720p', '1080p', '2160p', '4k',
            'x264', 'x265', 'hevc', 'xvid', 'divx',
            'bluray', 'bdrip', 'dvdrip', 'hdtv', 'webrip', 'web-dl', 'webdl',
            'aac', 'ac3', 'dts', 'flac', 'mp3',
            'proper', 'repack', 'internal', 'retail',
            'pal', 'ntsc', 'multi', 'dual',
        ];

        $baseNameLower = strtolower($baseName);
        foreach ($sceneTags as $tag) {
            if (str_contains($baseNameLower, $tag)) {
                return true;
            }
        }

        // Check for TV episode patterns
        if (preg_match('/s\d{1,2}e\d{1,3}/i', $baseName)) {
            return true;
        }

        // Check for year pattern (common in movies)
        if (preg_match('/[._-](19|20)\d{2}[._-]/i', $baseName)) {
            return true;
        }

        return false;
    }

    /**
     * Cleans file names for PreDB Match.
     *
     * Enhanced to better handle:
     * - RAR archives and split RAR files (.rar, .r00, .r01, etc.)
     * - PAR2 recovery files
     * - Various archive formats
     * - Scene naming conventions
     * - Path separators
     */
    protected function _cleanMatchFiles(): string|false
    {
        // First strip all non-printing chars from filename
        $this->_fileName = preg_replace('/[[:^print:]]/', '', $this->_fileName);

        if ($this->_fileName === '' || str_starts_with($this->_fileName, '.')) {
            return false;
        }

        // Extract filename from path (handle both Unix and Windows separators)
        if (preg_match('/[\\\\\/]([^\\\\\/]+)$/', $this->_fileName, $pathMatch)) {
            $this->_fileName = $pathMatch[1];
        }

        // Remove sample/proof/subs indicators before extension removal
        $this->_fileName = preg_replace('/[\.\-_](sample|proof|subs?|thumbs?|cover|screens?)[\.\-_]?$/i', '', $this->_fileName);

        // Strip common archive extensions and split file patterns
        $archivePatterns = [
            // RAR split files: .rar, .r00-.r999, .part01.rar, .part001.rar, etc.
            '/\.part\d{1,4}\.rar$/i',
            '/\.r\d{2,4}$/i',
            '/\.rar$/i',
            // ZIP split files
            '/\.z\d{2}$/i',
            '/\.zip$/i',
            // 7z split files
            '/\.7z\.\d{3}$/i',
            '/\.7z$/i',
            // PAR2 files
            '/\.vol\d+[\+\-]\d+\.par2?$/i',
            '/\.par2?$/i',
            // Other archive formats
            '/\.(tar|gz|bz2|xz|lz|lzma|cab|arj|ace|arc)$/i',
            // Numbered split files (001, 002, etc.)
            '/\.\d{3}$/i',
        ];

        foreach ($archivePatterns as $pattern) {
            $this->_fileName = preg_replace($pattern, '', $this->_fileName);
        }

        // Remove video file extensions
        $this->_fileName = preg_replace('/\.(mkv|avi|mp4|m4v|wmv|mpg|mpeg|mov|ts|m2ts|vob|divx|flv|webm|ogv|3gp|asf|rm|rmvb|f4v)$/i', '', $this->_fileName);

        // Remove audio file extensions
        $this->_fileName = preg_replace('/\.(mp3|flac|m4a|aac|ogg|wav|wma|ape|opus|mka|ac3|dts|eac3|truehd|mpc|shn|tak|tta|wv)$/i', '', $this->_fileName);

        // Remove image/other file extensions
        $this->_fileName = preg_replace('/\.(nfo|sfv|nzb|srr|srs|jpg|jpeg|png|gif|bmp|tiff?|webp|pdf|txt|diz|md5|sha1|cue|log)$/i', '', $this->_fileName);

        // Remove ebook extensions
        $this->_fileName = preg_replace('/\.(epub|mobi|azw3?|pdf|djvu|cbr|cbz|fb2|lit|prc|opf)$/i', '', $this->_fileName);

        // Remove game/app extensions
        $this->_fileName = preg_replace('/\.(iso|bin|cue|mdf|mds|nrg|img|ccd|sub|exe|msi|dmg|pkg|apk|xap|appx|deb|rpm)$/i', '', $this->_fileName);

        // Remove subtitle extensions
        $this->_fileName = preg_replace('/\.(srt|sub|idx|ass|ssa|vtt|sup)$/i', '', $this->_fileName);

        // Remove part/volume indicators that might remain
        $this->_fileName = preg_replace('/[\.\-_]?(part|vol|cd|dvd|disc|disk)\d*$/i', '', $this->_fileName);

        // Remove leading track numbers (common in music releases)
        $this->_fileName = preg_replace('/^\d{1,3}[\.\-_\s]+(?=[A-Za-z])/', '', $this->_fileName);

        // Trim whitespace and punctuation
        $this->_fileName = trim($this->_fileName, " \t\n\r\0\x0B.-_");

        return $this->_fileName !== '' ? $this->_fileName : false;
    }

    /**
     * Check the array using regex for a clean name.
     *
     *
     * @throws \Exception
     */
    public function checkName($release, bool $echo, string $type, $nameStatus, bool $show, bool $preId = false): bool
    {

        // Get pre style name from releases.name
        if (preg_match_all(self::PREDB_REGEX, $release->textstring, $hits) && ! preg_match('/Source\s\:/i', $release->textstring)) {
            foreach ($hits as $hit) {
                foreach ($hit as $val) {
                    $title = Predb::query()->where('title', trim($val))->select(['title', 'id'])->first();
                    if ($title !== null) {
                        $this->updateRelease($release, $title['title'], 'preDB: Match', $echo, $type, $nameStatus, $show, $title['id']);
                        $preId = true;
                    }
                }
            }
        }

        // if only processing for PreDB match skip to return
        if (! $preId) {
            switch ($type) {
                case 'PAR2, ':
                    $this->fileCheck($release, $echo, $type, $nameStatus, $show);
                    break;
                case 'PAR2 hash, ':
                    $this->hashCheck($release, $echo, $type, $nameStatus, $show);
                    break;
                case 'UID, ':
                    $this->uidCheck($release, $echo, $type, $nameStatus, $show);
                    break;
                case 'Mediainfo, ':
                    $this->mediaMovieNameCheck($release, $echo, $type, $nameStatus, $show);
                    break;
                case 'SRR, ':
                    $this->srrNameCheck($release, $echo, $type, $nameStatus, $show);
                    break;
                case 'CRC32, ':
                    $this->crcCheck($release, $echo, $type, $nameStatus, $show);
                    break;
                case 'NFO, ':
                    $this->nfoCheckTV($release, $echo, $type, $nameStatus, $show);
                    $this->nfoCheckMov($release, $echo, $type, $nameStatus, $show);
                    $this->nfoCheckMus($release, $echo, $type, $nameStatus, $show);
                    $this->nfoCheckTY($release, $echo, $type, $nameStatus, $show);
                    $this->nfoCheckG($release, $echo, $type, $nameStatus, $show);
                    break;
                case 'Filenames, ':
                    $this->preDbFileCheck($release, $echo, $type, $nameStatus, $show);
                    $this->preDbTitleCheck($release, $echo, $type, $nameStatus, $show);
                    $this->fileCheck($release, $echo, $type, $nameStatus, $show);
                    break;
                default:
                    $this->tvCheck($release, $echo, $type, $nameStatus, $show);
                    $this->movieCheck($release, $echo, $type, $nameStatus, $show);
                    $this->gameCheck($release, $echo, $type, $nameStatus, $show);
                    $this->appCheck($release, $echo, $type, $nameStatus, $show);
            }

            // set NameFixer process flags after run
            if ($nameStatus === true && ! $this->matched) {
                switch ($type) {
                    case 'NFO, ':
                        $this->_updateSingleColumn('proc_nfo', self::PROC_NFO_DONE, $release->releases_id);
                        break;
                    case 'Filenames, ':
                        $this->_updateSingleColumn('proc_files', self::PROC_FILES_DONE, $release->releases_id);
                        break;
                    case 'PAR2, ':
                        $this->_updateSingleColumn('proc_par2', self::PROC_PAR2_DONE, $release->releases_id);
                        break;
                    case 'PAR2 hash, ':
                        $this->_updateSingleColumn('proc_hash16k', self::PROC_HASH16K_DONE, $release->releases_id);
                        break;
                    case 'SRR, ':
                        $this->_updateSingleColumn('proc_srr', self::PROC_SRR_DONE, $release->releases_id);
                        break;
                    case 'UID, ':
                        $this->_updateSingleColumn('proc_uid', self::PROC_UID_DONE, $release->releases_id);
                        break;
                }
            }
        }

        return $this->matched;
    }

    /**
     * This function updates a single variable column in releases
     *  The first parameter is the column to update, the second is the value
     *  The final parameter is the ID of the release to update.
     */
    public function _updateSingleColumn($column = '', $status = 0, $id = 0): void
    {
        if ((string) $column !== '' && (int) $id !== 0) {
            Release::query()->where('id', $id)->update([$column => $status]);
        }
    }

    /**
     * Look for a TV name.
     *
     * Enhanced with support for:
     * - Modern streaming services (AMZN, NF, DSNP, etc.)
     * - 4K/UHD releases with HDR
     * - Modern codecs (HEVC, x265)
     * - Multi-episode releases (S01E01-E03)
     * - Daily show formats
     *
     * @throws \Exception
     */
    public function tvCheck($release, bool $echo, string $type, $nameStatus, $show): void
    {

        $result = [];

        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            // Streaming service 4K releases
            if (preg_match('/\w[\-\w.\',;& ]+((s\d{1,2}[._ -]?e\d{1,3}(-?e\d{1,3})?)|(?<!\d)[S|]\d{1,2}[E|x]\d{1,}(?!\d)|ep[._ -]?\d{2,3})[._ -](2160p|4K|UHD)[._ -](AMZN|ATVP|DSNP|HMAX|HULU|iT|NF|PMTP|PCOK|ROKU|STAN|VUDU)[._ -](WEB-?DL|WEB-?RIP)[._ -](HDR10?\+?|DV|Dolby[._ -]?Vision)?[._ -]?(HEVC|x265|H\.?265)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'tvCheck: Title.SxxExx.4K.streaming.source.hdr.vcodec', $echo, $type, $nameStatus, $show);
            }
            // Streaming service 1080p/720p releases
            elseif (preg_match('/\w[\-\w.\',;& ]+((s\d{1,2}[._ -]?e\d{1,3}(-?e\d{1,3})?)|(?<!\d)[S|]\d{1,2}[E|x]\d{1,}(?!\d)|ep[._ -]?\d{2,3})[._ -](1080p|720p)[._ -](AMZN|ATVP|DSNP|HMAX|HULU|iT|NF|PMTP|PCOK|ROKU|STAN|VUDU)[._ -](WEB-?DL|WEB-?RIP)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'tvCheck: Title.SxxExx.res.streaming.source', $echo, $type, $nameStatus, $show);
            }
            // Standard TV with source and group
            elseif (preg_match('/\w[\-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2}(-?e\d{1,3})?)|(?<!\d)[S|]\d{1,2}[E|x]\d{1,}(?!\d)|ep[._ -]?\d{2})[\-\w.\',;.()]+(BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[._ -][\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'tvCheck: Title.SxxExx.Text.source.group', $echo, $type, $nameStatus, $show);
            }
            // TV with year
            elseif (preg_match('/\w[\-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2}(-?e\d{1,3})?)|(?<!\d)\d{1,2}x\d{2}(?!\d)|ep[._ -]?\d{2})[\-\w.\',;& ]+((19|20)\d\d)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'tvCheck: Title.SxxExx.Text.year.group', $echo, $type, $nameStatus, $show);
            }
            // TV with resolution.source.vcodec
            elseif (preg_match('/\w[\-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2}(-?e\d{1,3})?)|(?<!\d)\d{1,2}x\d{2}(?!\d)|ep[._ -]?\d{2,3})[\-\w.\',;& ]+(480|720|1080|2160)[ip]?[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[._ -](DivX|[HX][._ -]?264|[HX][._ -]?265|HEVC|MPEG2|XviD(HD)?|WMV|AV1)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'tvCheck: Title.SxxExx.Text.resolution.source.vcodec.group', $echo, $type, $nameStatus, $show);
            }
            // TV with source.vcodec
            elseif (preg_match('/\w[\-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2}(-?e\d{1,3})?)|(?<!\d)\d{1,2}x\d{2}(?!\d)|ep[._ -]?\d{2})[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[._ -](DivX|[HX][._ -]?264|[HX][._ -]?265|HEVC|MPEG2|XviD(HD)?|WMV|AV1)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'tvCheck: Title.SxxExx.source.vcodec.group', $echo, $type, $nameStatus, $show);
            }
            // TV with acodec.source.res.vcodec
            elseif (preg_match('/\w[\-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2}(-?e\d{1,3})?)|(?<!\d)\d{1,2}x\d{2}(?!\d)|ep[._ -]?\d{2})[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?(-?MA)?|Dolby( ?TrueHD)?|MP3|TrueHD|Atmos|EAC3)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[._ -](480|720|1080|2160)[ip]?[._ -](DivX|[HX][._ -]?264|[HX][._ -]?265|HEVC|MPEG2|XviD(HD)?|WMV|AV1)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'tvCheck: Title.SxxExx.acodec.source.res.vcodec.group', $echo, $type, $nameStatus, $show);
            }
            // TV with year and season/episode
            elseif (preg_match('/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -]((s\d{1,2}[._ -]?[bde]\d{1,2}(-?e\d{1,3})?)|(?<!\d)\d{1,2}x\d{2}(?!\d)|ep[._ -]?\d{2})[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'tvCheck: Title.year.###(season/episode).source.group', $echo, $type, $nameStatus, $show);
            }
            // Daily shows with date format (YYYY.MM.DD or YYYY-MM-DD)
            elseif (preg_match('/\w[\-\w.\',;& ]+(19|20)\d\d[._ -]\d{2}[._ -]\d{2}[._ -](720p|1080p|2160p|HDTV|WEB-?DL|WEB-?RIP)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'tvCheck: Daily show with date', $echo, $type, $nameStatus, $show);
            }
            // Sports releases
            elseif (preg_match('/\w(19|20)\d\d[._ -]\d{2}[._ -]\d{2}[._ -](IndyCar|F1|Formula[._ -]?1|MotoGP|NBA|NCW([TY])S|NNS|NSCS?|NFL|NHL|MLB|UFC|WWE|Boxing)([._ -](19|20)\d\d)?[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'tvCheck: Sports', $echo, $type, $nameStatus, $show);
            }
            // Complete season packs
            elseif (preg_match('/\w[\-\w.\',;& ]+[._ -]S\d{1,2}[._ -](COMPLETE|FULL)[._ -](720p|1080p|2160p|HDTV|WEB-?DL|WEB-?RIP|BluRay)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'tvCheck: Complete season', $echo, $type, $nameStatus, $show);
            }
            // Anime releases with episode numbers
            elseif (preg_match('/\w[\-\w.\',;& ]+[._ -](\d{2,4})[._ -](480p|720p|1080p|2160p)[._ -](HEVC|x265|x264|H\.?264)[._ -](10bit)?[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'tvCheck: Anime episode', $echo, $type, $nameStatus, $show);
            }
        }
    }

    /**
     * Look for a movie name.
     *
     * Enhanced with support for:
     * - 4K/UHD releases with HDR, Dolby Vision
     * - Modern codecs (HEVC, x265, AV1)
     * - Streaming service releases (AMZN, NF, DSNP, etc.)
     * - REMUX and high-quality releases
     *
     * @throws \Exception
     */
    public function movieCheck($release, bool $echo, string $type, $nameStatus, $show): void
    {

        $result = [];

        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            // 4K/UHD releases with HDR
            if (preg_match('/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -](2160p|4K|UHD)[._ -](HDR10\+?|DV|Dolby[._ -]?Vision|HLG)?[._ -]?(REMUX|BluRay|WEB-?DL|WEB-?RIP|UHD[._ -]?BluRay)[._ -](HEVC|x265|H\.?265|AV1)[._ -]?(Atmos|DTS[._ -]?(HD)?|TrueHD)?[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'movieCheck: 4K/UHD with HDR', $echo, $type, $nameStatus, $show);
            }
            // 4K BluRay REMUX
            elseif (preg_match('/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -](2160p|4K)[._ -](REMUX|Complete[._ -]?UHD)[._ -](HEVC|x265|H\.?265)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'movieCheck: 4K REMUX', $echo, $type, $nameStatus, $show);
            }
            // Streaming service releases (4K)
            elseif (preg_match('/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -](2160p|4K)[._ -](AMZN|ATVP|DSNP|HMAX|HULU|iT|NF|PMTP|PCOK|ROKU|STAN|VUDU)[._ -](WEB-?DL|WEB-?RIP)[._ -](HDR10\+?|DV)?[._ -]?(HEVC|x265|H\.?265)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'movieCheck: 4K Streaming service', $echo, $type, $nameStatus, $show);
            }
            // Streaming service releases (1080p/720p)
            elseif (preg_match('/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -](1080p|720p)[._ -](AMZN|ATVP|DSNP|HMAX|HULU|iT|NF|PMTP|PCOK|ROKU|STAN|VUDU)[._ -](WEB-?DL|WEB-?RIP)[._ -](x264|x265|H\.?264|H\.?265|HEVC)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'movieCheck: HD Streaming service', $echo, $type, $nameStatus, $show);
            }
            // Standard year.res.vcodec pattern
            elseif (preg_match('/\w[\-\w.\',;& ]+((19|20)\d\d)[\-\w.\',;& ]+(480|720|1080|2160)[ip]?[._ -](DivX|[HX][._ -]?264|[HX][._ -]?265|HEVC|MPEG2|XviD(HD)?|WMV|AV1)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'movieCheck: Title.year.Text.res.vcod.group', $echo, $type, $nameStatus, $show);
            }
            // Year.source.vcodec.resolution
            elseif (preg_match('/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[._ -](DivX|[HX][._ -]?264|[HX][._ -]?265|HEVC|MPEG2|XviD(HD)?|WMV|AV1)[._ -](480|720|1080|2160)[ip]?[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'movieCheck: Title.year.source.vcodec.res.group', $echo, $type, $nameStatus, $show);
            }
            // Year.source.vcodec.acodec
            elseif (preg_match('/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[._ -](DivX|[HX][._ -]?264|[HX][._ -]?265|HEVC|MPEG2|XviD(HD)?|WMV|AV1)[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?(-?MA)?|Dolby( ?TrueHD)?|MP3|TrueHD|Atmos|EAC3|FLAC)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'movieCheck: Title.year.source.vcodec.acodec.group', $echo, $type, $nameStatus, $show);
            }
            // Resolution.source.acodec.vcodec
            elseif (preg_match('/\w[\-\w.\',;& ]+(480|720|1080|2160)[ip]?[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?(-?MA)?|Dolby( ?TrueHD)?|MP3|TrueHD|Atmos|EAC3|FLAC)[._ -](DivX|[HX][._ -]?264|[HX][._ -]?265|HEVC|MPEG2|XviD(HD)?|WMV|AV1)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'movieCheck: Title.year.resolution.source.acodec.vcodec.group', $echo, $type, $nameStatus, $show);
            }
            // Resolution.acodec.source.year
            elseif (preg_match('/\w[\-\w.\',;& ]+(480|720|1080|2160)[ip]?[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?(-?MA)?|Dolby( ?TrueHD)?|MP3|TrueHD|Atmos|EAC3|FLAC)[\-\w.\',;& ]+(BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[._ -]((19|20)\d\d)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'movieCheck: Title.resolution.acodec.eptitle.source.year.group', $echo, $type, $nameStatus, $show);
            }
            // Multi-language releases
            elseif (preg_match('/\w[\-\w.\',;& ]+(Brazilian|Chinese|Croatian|Danish|Deutsch|Dutch|Estonian|English|Finnish|Flemish|Francais|French|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish|MULTi)[._ -]((19|20)\d\d)[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?(-?MA)?|Dolby( ?TrueHD)?|MP3|TrueHD|Atmos|EAC3|FLAC)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'movieCheck: Title.language.year.acodec.src', $echo, $type, $nameStatus, $show);
            }
            // Generic movie with year and resolution (fallback)
            elseif (preg_match('/\w[\-\w.\',;& ]+((19|20)\d\d)[\-\w.\',;& ]+(480|720|1080|2160)[ip]?[\-\w.\',;& ]+(BluRay|BDRip|DVDRip|HDTV|WEB-?DL|WEB-?RIP)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'movieCheck: Title.year.res.source.group', $echo, $type, $nameStatus, $show);
            }
        }
    }

    /**
     * Look for a game name.
     *
     * Enhanced with support for:
     * - Modern platforms (PS5, Xbox Series X/S, Nintendo Switch)
     * - Modern scene groups
     * - DLC, updates, and patches
     *
     * @throws \Exception
     */
    public function gameCheck($release, bool $echo, string $type, $nameStatus, $show): void
    {

        $result = [];

        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            // Modern console releases (PS5, Xbox Series, Switch)
            if (preg_match('/\w[\-\w.\',;& ]+(NSW|PS[345P]|PSV|XBSX|XSX|XBOX[._ -]?SERIES[._ -]?[XS]|XBOX[._ -]?ONE|XBOX360?|WiiU?|Switch)[._ -](INTERNAL|PROPER|READNFO|READ[._ -]?NFO|MULTI\d{1,2})?[._ -]?[\-\w.\',;& ]+\-[A-Za-z0-9]+$/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'gameCheck: Modern console release', $echo, $type, $nameStatus, $show);
            }
            // Region-based game releases
            elseif (preg_match('/\w[\-\w.\',;& ]+(ASIA|DLC|EUR|GOTY|JPN|KOR|MULTI\d{1}|NTSCU?|PAL|RF|Region[._ -]?Free|USA|XBLA)[._ -](DLC[._ -]Complete|FRENCH|GERMAN|MULTI\d{1}|PROPER|PSN|READ[._ -]?NFO|UMD)?[._ -]?(GC|NDS|NGC|PS[345P]|PSP|PSV|Switch|NSW|Wii(U)?|XBOX(360|ONE|SERIES)?|XBSX)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'gameCheck: Videogames with region', $echo, $type, $nameStatus, $show);
            }
            // Scene group releases
            elseif (preg_match('/\w[\-\w.\',;& ]+(GC|NDS|NGC|PS[345P]|Switch|NSW|Wii(U)?|XBOX(360|ONE|SERIES)?|XBSX)[._ -](CODEX|DUPLEX|PLAZA|SKIDROW|RELOADED|CPY|EMPRESS|RAZOR1911|HOODLUM|DARKSiDERS|FLT|TiNYiSO|ANOMALY|iNSOMNi|OneUp|STRANGE|SWAG|SKY|SUXXORS)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'gameCheck: Console with scene group', $echo, $type, $nameStatus, $show);
            }
            // PC Games with scene groups
            elseif (preg_match('/\w[\-\w.\',;& ]+(PC|WIN(32|64)?|MAC(OSX?)?|LINUX)[._ -]?(CODEX|SKIDROW|RELOADED|CPY|EMPRESS|RAZOR1911|HOODLUM|DARKSiDERS|FLT|GOG|PROPHET|TiNYiSO|PLAZA|P2P|SiMPLEX|rG)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'gameCheck: PC game with scene group', $echo, $type, $nameStatus, $show);
            }
            // DLC and Update releases
            elseif (preg_match('/\w[\-\w.\',;& ]+(DLC|Update|Patch|Hotfix)[._ -](v?\d+[\.\d]*)?[._ -]?(CODEX|SKIDROW|RELOADED|PLAZA|EMPRESS|FLT|GOG|P2P|TiNYiSO)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'gameCheck: DLC/Update', $echo, $type, $nameStatus, $show);
            }
            // OUTLAWS group releases
            elseif (preg_match('/\w[\w.\',;-].+-OUTLAWS/i', $release->textstring, $result)) {
                $result = str_replace('OUTLAWS', 'PC GAME OUTLAWS', $result['0']);
                $this->updateRelease($release, $result['0'], 'gameCheck: PC Games -OUTLAWS', $echo, $type, $nameStatus, $show);
            }
            // ALiAS group releases
            elseif (preg_match('/\w[\w.\',;-].+\-ALiAS/i', $release->textstring, $result)) {
                $newResult = str_replace('-ALiAS', ' PC GAME ALiAS', $result['0']);
                $this->updateRelease($release, $newResult, 'gameCheck: PC Games -ALiAS', $echo, $type, $nameStatus, $show);
            }
            // GOG releases
            elseif (preg_match('/\w[\-\w.\',;& ]+[._ -]GOG[._ -]?(Classic|Galaxy)?[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'gameCheck: GOG release', $echo, $type, $nameStatus, $show);
            }
            // REPACK releases
            elseif (preg_match('/\w[\-\w.\',;& ]+[._ -](REPACK|RIP)[._ -](FitGirl|DODI|xatab|R\.G\.|Mechanics)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'gameCheck: Game REPACK', $echo, $type, $nameStatus, $show);
            }
        }
    }

    /**
     * Look for an app name.
     *
     * Enhanced with support for:
     * - Modern software patterns
     * - macOS/Linux releases
     * - Scene group naming conventions
     *
     * @throws \Exception
     */
    public function appCheck($release, bool $echo, string $type, $nameStatus, $show): void
    {

        $result = [];

        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            // Software with keygen/patch
            if (preg_match('/\w[\-\w.\',;& ]+(\d{1,10}|v\d+[\.\d]*|Linux|UNIX|MacOS)[._ -](RPM|DEB)?[._ -]?(X64|X86|ARM64)?[._ -]?(Incl|With)?[._ -]?(Keygen|Patch|Crack|Serial|License)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'appCheck: Apps with keygen/patch', $echo, $type, $nameStatus, $show);
            }
            // Windows freeware
            elseif (preg_match('/\w[\-\w.\',;& ]+\d{1,8}[._ -](winall|win32|win64|x64|x86)[._ -]?(freeware|portable|repack)?[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'appCheck: Windows apps', $echo, $type, $nameStatus, $show);
            }
            // macOS applications
            elseif (preg_match('/\w[\-\w.\',;& ]+(MacOS|Mac[._ -]?OS[._ -]?X|OSX)[._ -][\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'appCheck: macOS apps', $echo, $type, $nameStatus, $show);
            }
            // Linux applications
            elseif (preg_match('/\w[\-\w.\',;& ]+(Linux|Ubuntu|Debian|CentOS|RHEL|Fedora)[._ -](x64|x86|arm64)?[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'appCheck: Linux apps', $echo, $type, $nameStatus, $show);
            }
            // Adobe software
            elseif (preg_match('/\w[\-\w.\',;& ]*(Adobe|Photoshop|Illustrator|Premiere|After[._ -]?Effects|InDesign|Lightroom)[._ -][\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'appCheck: Adobe apps', $echo, $type, $nameStatus, $show);
            }
            // Microsoft software
            elseif (preg_match('/\w[\-\w.\',;& ]*(Microsoft|Office|Windows|Visual[._ -]?Studio)[._ -]\d{2,4}[._ -][\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'appCheck: Microsoft apps', $echo, $type, $nameStatus, $show);
            }
            // Generic software with version
            elseif (preg_match('/\w[\-\w.\',;& ]+[._ -]v?\d+[\.\d]+[._ -](Multilingual|MULTi|Portable|Repack|Cracked)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['0'], 'appCheck: Software with version', $echo, $type, $nameStatus, $show);
            }
        }
    }

    /**
     * TV.
     *
     *
     * @throws \Exception
     */
    public function nfoCheckTV($release, bool $echo, string $type, $nameStatus, $show): void
    {

        $result = [];

        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            if (preg_match('/:\s*.*[\\\\\/]([A-Z0-9].+?S\d+[.-_ ]?[ED]\d+.+?)\.\w{2,}\s+/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['1'], 'nfoCheck: Generic TV 1', $echo, $type, $nameStatus, $show);
            } elseif (preg_match('/(?:(\:\s{1,}))(.+?S\d{1,3}[.-_ ]?[ED]\d{1,3}.+?)(\s{2,}|\r|\n)/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['2'], 'nfoCheck: Generic TV 2', $echo, $type, $nameStatus, $show);
            }
        }
    }

    /**
     * Movies.
     *
     *
     * @throws \Exception
     */
    public function nfoCheckMov($release, bool $echo, string $type, $nameStatus, $show): void
    {

        $result = [];

        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            if (preg_match('/(?:((?!Source\s)\:\s{1,}))(.+?(19|20)\d\d.+?(BDRip|bluray|DVD(R|Rip)?|XVID).+?)(\s{2,}|\r|\n)/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['2'], 'nfoCheck: Generic Movies 1', $echo, $type, $nameStatus, $show);
            } elseif (preg_match('/(?:(\s{2,}))((?!Source).+?[\.\-_ ](19|20)\d\d.+?(BDRip|bluray|DVD(R|Rip)?|XVID).+?)(\s{2,}|\r|\n)/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['2'], 'nfoCheck: Generic Movies 2', $echo, $type, $nameStatus, $show);
            } elseif (preg_match('/(?:(\s{2,}))(.+?[\.\-_ ](NTSC|MULTi).+?(MULTi|DVDR)[\.\-_ ].+?)(\s{2,}|\r|\n)/i', $release->textstring, $result)) {
                $this->updateRelease($release, $result['2'], 'nfoCheck: Generic Movies 3', $echo, $type, $nameStatus, $show);
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function nfoCheckMus($release, bool $echo, string $type, $nameStatus, $show): void
    {

        $result = [];

        if (! $this->done && $this->relid !== (int) $release->releases_id && preg_match('/(?:\s{2,})(.+?-FM-\d{2}-\d{2})/i', $release->textstring, $result)) {
            $newName = str_replace('-FM-', '-FM-Radio-MP3-', $result['1']);
            $this->updateRelease($release, $newName, 'nfoCheck: Music FM RADIO', $echo, $type, $nameStatus, $show);
        }
    }

    /**
     * Title (year).
     *
     *
     * @throws \Exception
     */
    public function nfoCheckTY($release, bool $echo, string $type, $nameStatus, $show): void
    {

        $result = [];

        if (! $this->done && $this->relid !== (int) $release->releases_id && preg_match('/(\w[\-\w`~!@#$%^&*()_+={}|"<>?\[\]\\;\',.\/ ]+\s?\((19|20)\d\d\))/i', $release->textstring, $result) && ! preg_match('/\.pdf|Audio ?Book/i', $release->textstring)) {
            $releaseName = $result[0];
            if (preg_match('/(idiomas|lang|language|langue|sprache).*?\b(?P<lang>Brazilian|Chinese|Croatian|Danish|DE|Deutsch|Dutch|Estonian|ES|English|Englisch|Finnish|Flemish|Francais|French|FR|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)\b/i', $release->textstring, $result)) {
                switch ($result['lang']) {
                    case 'DE':
                        $result['lang'] = 'GERMAN';
                        break;
                    case 'Englisch':
                        $result['lang'] = 'ENGLISH';
                        break;
                    case 'FR':
                        $result['lang'] = 'FRENCH';
                        break;
                    case 'ES':
                        $result['lang'] = 'SPANISH';
                        break;
                    default:
                        break;
                }
                $releaseName .= '.'.$result['lang'];
            }

            if (preg_match('/(frame size|(video )?res(olution)?|video).*?(?P<res>(272|336|480|494|528|608|\(?640|688|704|720x480|810|816|820|1 ?080|1280( \@)?|1 ?920(x1080)?))/i', $release->textstring, $result)) {
                switch ($result['res']) {
                    case '272':
                    case '336':
                    case '480':
                    case '494':
                    case '608':
                    case '640':
                    case '(640':
                    case '688':
                    case '704':
                    case '720x480':
                        $result['res'] = '480p';
                        break;
                    case '1280x720':
                    case '1280':
                    case '1280 @':
                        $result['res'] = '720p';
                        break;
                    case '810':
                    case '816':
                    case '820':
                    case '1920':
                    case '1 920':
                    case '1080':
                    case '1 080':
                    case '1920x1080':
                        $result['res'] = '1080p';
                        break;
                    case '2160':
                        $result['res'] = '2160p';
                        break;
                }

                $releaseName .= '.'.$result['res'];
            } elseif (preg_match('/(largeur|width).*?(?P<res>(\(?640|688|704|720|1280( \@)?|1 ?920))/i', $release->textstring, $result)) {
                switch ($result['res']) {
                    case '640':
                    case '(640':
                    case '688':
                    case '704':
                    case '720':
                        $result['res'] = '480p';
                        break;
                    case '1280 @':
                    case '1280':
                        $result['res'] = '720p';
                        break;
                    case '1920':
                    case '1 920':
                        $result['res'] = '1080p';
                        break;
                    case '2160':
                        $result['res'] = '2160p';
                        break;
                }

                $releaseName .= '.'.$result['res'];
            }

            if (preg_match('/source.*?\b(?P<source>BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)\b/i', $release->textstring, $result)) {
                switch ($result['source']) {
                    case 'BD':
                        $result['source'] = 'Bluray.x264';
                        break;
                    case 'CAMRIP':
                        $result['source'] = 'CAM';
                        break;
                    case 'DBrip':
                        $result['source'] = 'BDRIP';
                        break;
                    case 'DVD R1':
                    case 'NTSC':
                    case 'PAL':
                    case 'VOD':
                        $result['source'] = 'DVD';
                        break;
                    case 'HD':
                        $result['source'] = 'HDTV';
                        break;
                    case 'Ripped ':
                        $result['source'] = 'DVDRIP';
                }

                $releaseName .= '.'.$result['source'];
            } elseif (preg_match('/(codec( (name|code))?|(original )?format|res(olution)|video( (codec|format|res))?|tv system|type|writing library).*?\b(?P<video>AVC|AVI|DBrip|DIVX|\(Divx|DVD|[HX][._ -]?264|MPEG-4 Visual|NTSC|PAL|WMV|XVID)\b/i', $release->textstring, $result)) {
                switch ($result['video']) {
                    case 'AVI':
                        $result['video'] = 'DVDRIP';
                        break;
                    case 'DBrip':
                        $result['video'] = 'BDRIP';
                        break;
                    case '(Divx':
                        $result['video'] = 'DIVX';
                        break;
                    case 'h264':
                    case 'h-264':
                    case 'h.264':
                        $result['video'] = 'H264';
                        break;
                    case 'MPEG-4 Visual':
                    case 'x264':
                    case 'x-264':
                    case 'x.264':
                        $result['video'] = 'x264';
                        break;
                    case 'NTSC':
                    case 'PAL':
                        $result['video'] = 'DVD';
                        break;
                }

                $releaseName .= '.'.$result['video'];
            }

            if (preg_match('/(audio( format)?|codec( name)?|format).*?\b(?P<audio>0x0055 MPEG-1 Layer 3|AAC( LC)?|AC-?3|\(AC3|DD5(.1)?|(A_)?DTS-?(HD)?|Dolby(\s?TrueHD)?|TrueHD|FLAC|MP3)\b/i', $release->textstring, $result)) {
                switch ($result['audio']) {
                    case '0x0055 MPEG-1 Layer 3':
                        $result['audio'] = 'MP3';
                        break;
                    case 'AC-3':
                    case '(AC3':
                        $result['audio'] = 'AC3';
                        break;
                    case 'AAC LC':
                        $result['audio'] = 'AAC';
                        break;
                    case 'A_DTS':
                    case 'DTS-HD':
                    case 'DTSHD':
                        $result['audio'] = 'DTS';
                }
                $releaseName .= '.'.$result['audio'];
            }
            $releaseName .= '-NoGroup';
            $this->updateRelease($release, $releaseName, 'nfoCheck: Title (Year)', $echo, $type, $nameStatus, $show);
        }
    }

    /**
     * Games.
     *
     *
     * @throws \Exception
     */
    public function nfoCheckG($release, bool $echo, string $type, $nameStatus, $show): void
    {

        $result = [];

        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            if (preg_match('/ALiAS|BAT-TEAM|FAiRLiGHT|Game Type|Glamoury|HI2U|iTWINS|JAGUAR|(LARGE|MEDIUM)ISO|MAZE|nERv|PROPHET|PROFiT|PROCYON|RELOADED|REVOLVER|ROGUE|ViTALiTY/i', $release->textstring)) {
                if (preg_match('/\w[\w.+&*\/\()\',;: -]+\(c\)[\-\w.\',;& ]+\w/i', $release->textstring, $result)) {
                    $releaseName = str_replace(['(c)', '(C)'], '(GAMES) (c)', $result['0']);
                    $this->updateRelease($release, $releaseName, 'nfoCheck: PC Games (c)', $echo, $type, $nameStatus, $show);
                } elseif (preg_match('/\w[\w.+&*\/()\',;: -]+\*ISO\*/i', $release->textstring, $result)) {
                    $releaseName = str_replace('*ISO*', '*ISO* (PC GAMES)', $result['0']);
                    $this->updateRelease($release, $releaseName, 'nfoCheck: PC Games *ISO*', $echo, $type, $nameStatus, $show);
                }
            }
        }
    }

    /**
     * Misc.
     *
     *
     * @throws \Exception
     */
    public function nfoCheckMisc($release, bool $echo, string $type, $nameStatus, $show): void
    {

        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            if (preg_match('/Supplier.+?IGUANA/i', $release->textstring)) {
                $releaseName = '';
                $result = [];
                if (preg_match('/\w[\-\w`~!@#$%^&*()+={}|:"<>?\[\]\\;\',.\/ ]+\s\((19|20)\d\d\)/i', $release->textstring, $result)) {
                    $releaseName = $result[0];
                } elseif (preg_match('/\s\[\*\] (English|Dutch|French|German|Spanish)\b/i', $release->textstring, $result)) {
                    $releaseName .= '.'.$result[1];
                } elseif (preg_match('/\s\[\*\] (DT?S [2567][._ -][0-2]( MONO)?)\b/i', $release->textstring, $result)) {
                    $releaseName .= '.'.$result[2];
                } elseif (preg_match('/Format.+(DVD([59R])?|[HX][._ -]?264)\b/i', $release->textstring, $result)) {
                    $releaseName .= '.'.$result[1];
                } elseif (preg_match('/\[(640x.+|1280x.+|1920x.+)\] Resolution\b/i', $release->textstring, $result)) {
                    if ($result[1] === '640x.+') {
                        $result[1] = '480p';
                    } elseif ($result[1] === '1280x.+') {
                        $result[1] = '720p';
                    } elseif ($result[1] === '1920x.+') {
                        $result[1] = '1080p';
                    }
                    $releaseName .= '.'.$result[1];
                }
                $result = $releaseName.'.IGUANA';
                $this->updateRelease($release, $result, 'nfoCheck: IGUANA', $echo, $type, $nameStatus, $show);
            }
        }
    }

    /**
     * Just for filenames.
     *
     * Enhanced file checking with support for:
     * - Modern video formats (4K, HDR, HEVC, etc.)
     * - Scene naming conventions
     * - RAR archive contents
     * - Various media types
     *
     * @throws \Exception
     */
    public function fileCheck($release, bool $echo, string $type, $nameStatus, $show): bool
    {

        $result = [];

        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            // Clean the filename for better matching
            $cleanedFilename = $this->cleanFilenameForMatching($release->textstring);

            switch (true) {
                // Scene TV release with group suffix
                case preg_match('/^(.+?(x264|x265|HEVC|XviD|H\.?264|H\.?265)\-[A-Za-z0-9]+)\\\\/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'], 'fileCheck: Scene release with group', $echo, $type, $nameStatus, $show);
                    break;
                // TVP group format
                case preg_match('/^(.+?(x264|XviD)\-TVP)\\\\/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'], 'fileCheck: TVP', $echo, $type, $nameStatus, $show);
                    break;
                // Generic TV - SxxExx format with quality/source info
                case preg_match('/^(\\\\|\/)?(.+(\\\\|\/))*(.+?S\d{1,3}[.-_ ]?E\d{1,3}(?:[.-_ ]?E\d{1,3})?[.-_ ].+?(?:720p|1080p|2160p|4K|HDTV|WEB-?DL|WEB-?RIP|BluRay|AMZN|HMAX|NF|DSNP).+?)\.(.+)$/iu', $release->textstring, $result):
                    $this->updateRelease($release, $result['4'], 'fileCheck: TV SxxExx with quality', $echo, $type, $nameStatus, $show);
                    break;
                // Generic TV - any SxxExx format
                case preg_match('/^(\\\\|\/)?(.+(\\\\|\/))*(.+?S\d{1,3}[.-_ ]?[ED]\d{1,3}.+)\.(.+)$/iu', $release->textstring, $result):
                    $this->updateRelease($release, $result['4'], 'fileCheck: Generic TV', $echo, $type, $nameStatus, $show);
                    break;
                // 4K/UHD Movies - modern formats
                case preg_match('/^(\\\\|\/)?(.+(\\\\|\/))*(.+?[\.\-_ ](19|20)\d\d[\.\-_ ].+?(2160p|4K|UHD).+?(HDR10?\+?|DV|Dolby[\.\-_ ]?Vision)?.+?(HEVC|x265|H\.?265).+?)\.(.+)$/iu', $release->textstring, $result):
                    $this->updateRelease($release, $result['4'], 'fileCheck: 4K/UHD Movie', $echo, $type, $nameStatus, $show);
                    break;
                // HD Movies with modern codecs
                case preg_match('/^(\\\\|\/)?(.+(\\\\|\/))*(.+?[\.\-_ ](19|20)\d\d[\.\-_ ].+?(720p|1080p).+?(BluRay|WEB-?DL|WEB-?RIP|BDRip|REMUX).+?(x264|x265|HEVC|H\.?264|H\.?265|AVC).+?)\.(.+)$/iu', $release->textstring, $result):
                    $this->updateRelease($release, $result['4'], 'fileCheck: HD Movie modern codec', $echo, $type, $nameStatus, $show);
                    break;
                // Standard HD Movies
                case preg_match('/^(\\\\|\/)?(.+(\\\\|\/))*(.+?([\.\-_ ]\d{4}[\.\-_ ].+?(BDRip|bluray|DVDRip|XVID|WEB-?DL|HDTV)).+)\.(.+)$/iu', $release->textstring, $result):
                    $this->updateRelease($release, $result['4'], 'fileCheck: Generic movie 1', $echo, $type, $nameStatus, $show);
                    break;
                case preg_match('/^([a-z0-9\.\-_]+(19|20)\d\d[a-z0-9\.\-_]+[\.\-_ ](720p|1080p|2160p|4K|BDRip|bluray|DVDRip|x264|x265|XviD|HEVC)[a-z0-9\.\-_]+)\.[a-z]{2,}$/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'], 'fileCheck: Generic movie 2', $echo, $type, $nameStatus, $show);
                    break;
                // Streaming service releases
                case preg_match('/^([A-Za-z0-9\.\-_]+[\.\-_ ](AMZN|ATVP|DSNP|HMAX|HULU|iT|NF|PMTP|PCOK|ROKU|STAN|TVNZ|VUDU)[\.\-_ ].+?(WEB-?DL|WEB-?RIP).+?)\.(.+)$/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'], 'fileCheck: Streaming service release', $echo, $type, $nameStatus, $show);
                    break;
                // Music releases
                case preg_match('/(.+?([\.\-_ ](CD|FM)|[\.\-_ ]\dCD|CDR|FLAC|SAT|WEB).+?(19|20)\d\d.+?)\\\\.+/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'], 'fileCheck: Generic music', $echo, $type, $nameStatus, $show);
                    break;
                case preg_match('/^(.+?(19|20)\d\d\-([a-z0-9]{3}|[a-z]{2,}|C4))\\\\/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'], 'fileCheck: music groups', $echo, $type, $nameStatus, $show);
                    break;
                // FLAC music releases
                case preg_match('/^(.+?[\.\-_ ](FLAC|MP3|AAC|OGG)[\.\-_ ].+?[\.\-_ ]\d{4}[\.\-_ ].+?\-[A-Za-z0-9]+)[\\\\\/.]/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'], 'fileCheck: Music with codec', $echo, $type, $nameStatus, $show);
                    break;
                // Movie with year in parentheses - AVI format
                case preg_match('/.+\\\\(.+\((19|20)\d\d\)\.avi)$/i', $release->textstring, $result):
                    $newName = str_replace('.avi', ' DVDRip XVID NoGroup', $result['1']);
                    $this->updateRelease($release, $newName, 'fileCheck: Movie (year) avi', $echo, $type, $nameStatus, $show);
                    break;
                // Movie with year in parentheses - ISO format
                case preg_match('/.+\\\\(.+\((19|20)\d\d\)\.iso)$/i', $release->textstring, $result):
                    $newName = str_replace('.iso', ' DVD NoGroup', $result['1']);
                    $this->updateRelease($release, $newName, 'fileCheck: Movie (year) iso', $echo, $type, $nameStatus, $show);
                    break;
                // Movie with year in parentheses - MKV format
                case preg_match('/.+\\\\(.+\((19|20)\d\d\)\.(mkv|mp4|m4v))$/i', $release->textstring, $result):
                    $newName = preg_replace('/\.(mkv|mp4|m4v)$/i', ' BDRip x264 NoGroup', $result['1']);
                    $this->updateRelease($release, $newName, 'fileCheck: Movie (year) mkv/mp4', $echo, $type, $nameStatus, $show);
                    break;
                // RAR file contents - look for release name in RAR path
                case preg_match('/^([A-Za-z0-9][\w.\-]+(?:[\.\-_ ][\w.\-]+)+)[\\\\\\/](?:CD\d|Disc\d|DVD\d|Subs?)?[\\\\\\/]?.+\.(rar|r\d{2,3}|zip|7z)$/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'], 'fileCheck: RAR archive path', $echo, $type, $nameStatus, $show);
                    break;
                // Scene release in RAR - common pattern: Release.Name-GROUP\release.name-group.rar
                case preg_match('/^([A-Za-z0-9][\w.\-]+\-[A-Za-z0-9]+)[\\\\\\/].+\.(rar|r\d{2,3})$/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'], 'fileCheck: Scene RAR release', $echo, $type, $nameStatus, $show);
                    break;
                // XXX Imagesets
                case preg_match('/^(.+?IMAGESET.+?)\\\\.+/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'], 'fileCheck: XXX Imagesets', $echo, $type, $nameStatus, $show);
                    break;
                // VIDEOOT releases
                case preg_match('/^VIDEOOT-[A-Z0-9]+\\\\([\w!.,& ()\[\]\'\`-]{8,}?\b.?)([\-_](proof|sample|thumbs?))*(\.part\d*(\.rar)?|\.rar|\.7z)?(\d{1,3}\.rev|\.vol.+?|\.mp4)/', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'].' XXX DVDRIP XviD-VIDEOOT', 'fileCheck: XXX XviD VIDEOOT', $echo, $type, $nameStatus, $show);
                    break;
                // XXX SDPORN
                case preg_match('/^.+?SDPORN/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['0'], 'fileCheck: XXX SDPORN', $echo, $type, $nameStatus, $show);
                    break;
                // R&C releases
                case preg_match('/\w[\-\w.\',;& ]+1080i[._ -]DD5[._ -]1[._ -]MPEG2-R&C(?=\.ts)$/i', $release->textstring, $result):
                    $result = str_replace('MPEG2', 'MPEG2.HDTV', $result['0']);
                    $this->updateRelease($release, $result, 'fileCheck: R&C', $echo, $type, $nameStatus, $show);
                    break;
                // NhaNc3 releases
                case preg_match('/\w[\-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[._ -](480|720|1080)[ip][._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -]nSD[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[._ -]NhaNC3[\-\w.\',;& ]+\w/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['0'], 'fileCheck: NhaNc3', $echo, $type, $nameStatus, $show);
                    break;
                // TVP releases (alternate pattern)
                case preg_match('/\wtvp-[\w.\-\',;]+((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[._ -](720p|1080p|xvid)(?=\.(avi|mkv))$/i', $release->textstring, $result):
                    $result = str_replace('720p', '720p.HDTV.X264', $result['0']);
                    $result = str_replace('1080p', '1080p.Bluray.X264', $result['0']);
                    $result = str_replace('xvid', 'XVID.DVDrip', $result['0']);
                    $this->updateRelease($release, $result, 'fileCheck: tvp', $echo, $type, $nameStatus, $show);
                    break;
                // LOL releases
                case preg_match('/\w[\-\w.\',;& ]+\d{3,4}\.hdtv-lol\.(avi|mp4|mkv|ts|nfo|nzb)/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['0'], 'fileCheck: Title.211.hdtv-lol.extension', $echo, $type, $nameStatus, $show);
                    break;
                // DL releases
                case preg_match('/\w[\-\w.\',;& ]+-S\d{1,2}[EX]\d{1,2}-XVID-DL\.avi/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['0'], 'fileCheck: Title-SxxExx-XVID-DL.avi', $echo, $type, $nameStatus, $show);
                    break;
                // Title - SxxExx - Episode title format
                case preg_match('/\S.*[\w.\-\',;]+\s\-\ss\d{2}[ex]\d{2}\s\-\s[\w.\-\',;].+\./i', $release->textstring, $result):
                    $this->updateRelease($release, $result['0'], 'fileCheck: Title - SxxExx - Eptitle', $echo, $type, $nameStatus, $show);
                    break;
                // Nintendo DS
                case preg_match('/\w.+?\)\.nds$/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['0'], 'fileCheck: ).nds Nintendo DS', $echo, $type, $nameStatus, $show);
                    break;
                // Nintendo 3DS
                case preg_match('/3DS_\d{4}.+\d{4} - (.+?)\.3ds/i', $release->textstring, $result):
                    $this->updateRelease($release, '3DS '.$result['1'], 'fileCheck: .3ds Nintendo 3DS', $echo, $type, $nameStatus, $show);
                    break;
                // Nintendo Switch
                case preg_match('/^(.+?)\[[\w]+\]\.(?:nsp|xci|nsz)$/i', $release->textstring, $result):
                    $this->updateRelease($release, trim($result['1']).' Switch', 'fileCheck: Nintendo Switch', $echo, $type, $nameStatus, $show);
                    break;
                // PlayStation/Xbox game releases
                case preg_match('/^(.+?[\.\-_ ](PS[345P]|PSV|XBOX360|XBOXONE|NSW)[\.\-_ ].+?\-[A-Za-z0-9]+)[\\\\\/.]/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'], 'fileCheck: Console game release', $echo, $type, $nameStatus, $show);
                    break;
                // EBooks
                case preg_match('/\w.+?\.(epub|mobi|azw3?|opf|fb2|prc|djvu|cb[rz])/i', $release->textstring, $result):
                    $result = str_replace('.'.$result['1'], ' ('.$result['1'].')', $result['0']);
                    $this->updateRelease($release, $result, 'fileCheck: EBook', $echo, $type, $nameStatus, $show);
                    break;
                // Audiobooks
                case preg_match('/^(.+?[\.\-_ ]Audiobook[\.\-_ ].+?)[\\\\\/.]/i', $release->textstring, $result):
                    $this->updateRelease($release, $result['1'], 'fileCheck: Audiobook', $echo, $type, $nameStatus, $show);
                    break;
                // Scene release from cleaned filename
                case preg_match('/^([A-Za-z0-9][\w.\-]+\-[A-Za-z0-9]{2,15})$/i', $cleanedFilename, $result) && preg_match(self::PREDB_REGEX, $cleanedFilename):
                    $this->updateRelease($release, $result['1'], 'fileCheck: Cleaned scene name', $echo, $type, $nameStatus, $show);
                    break;
                // Folder name fallback
                case preg_match('/\w+[\-\w.\',;& ]+$/i', $release->textstring, $result) && preg_match(self::PREDB_REGEX, $release->textstring):
                    $this->updateRelease($release, $result['0'], 'fileCheck: Folder name', $echo, $type, $nameStatus, $show);
                    break;
                default:
                    return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Clean a filename for better pattern matching.
     *
     * Removes common file extensions, path components, and normalizes the string.
     */
    protected function cleanFilenameForMatching(string $filename): string
    {
        // Extract filename from path
        if (preg_match('/[\\\\\/]([^\\\\\/]+)$/', $filename, $match)) {
            $filename = $match[1];
        }

        // Remove common extensions
        $filename = preg_replace('/\.(mkv|avi|mp4|m4v|wmv|mpg|mpeg|mov|ts|m2ts|vob|divx|flv|webm|nfo|sfv|nzb|srr|srs|rar|r\d{2,4}|zip|7z|par2?|vol\d+[\+\-]\d+|001|\d{3})$/i', '', $filename);

        // Remove sample/proof indicators
        $filename = preg_replace('/[\.\-_](sample|proof|subs?)[\.\-_]?/i', '', $filename);

        // Remove part/volume indicators
        $filename = preg_replace('/[\.\-_]?(part|vol|cd|dvd|disc|disk)\d+$/i', '', $filename);

        return trim($filename, " \t\n\r\0\x0B.-_");
    }

    /**
     * Look for a name based on mediainfo xml Unique_ID.
     *
     *
     *
     * @throws \Exception
     */
    public function uidCheck($release, $echo, $type, $nameStatus, $show): bool
    {

        if (! empty($release->uid) && ! $this->done && $this->relid !== (int) $release->releases_id) {
            $result = Release::fromQuery(sprintf(
                '
				SELECT r.id AS releases_id, r.size AS relsize, r.name AS textstring, r.searchname, r.fromname, r.predb_id
				FROM releases r
				LEFT JOIN media_infos ru ON ru.releases_id = r.id
				WHERE ru.releases_id IS NOT NULL
				AND ru.unique_id = %s
				AND ru.releases_id != %d
				AND (r.predb_id > 0 OR r.anidbid > 0 OR r.fromname = %s)',
                escapeString($release->uid),
                $release->releases_id,
                escapeString('nonscene@Ef.net (EF)')
            ));

            foreach ($result as $res) {
                $floor = round(($res->relsize - $release->relsize) / $res->relsize * 100, 1);
                if ($floor >= -10 && $floor <= 10) {
                    $this->updateRelease(
                        $release,
                        $res->searchname,
                        'uidCheck: Unique_ID',
                        $echo,
                        $type,
                        $nameStatus,
                        $show,
                        $res->predb_id
                    );

                    return true;
                }
            }
        }
        $this->_updateSingleColumn('proc_uid', self::PROC_UID_DONE, $release->releases_id);

        return false;
    }

    /**
     * Look for a name based on mediainfo xml Unique_ID.
     *
     *
     *
     * @throws \Exception
     */
    public function mediaMovieNameCheck($release, $echo, $type, $nameStatus, $show): bool
    {

        $newName = '';
        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            if (! empty($release->movie_name)) {
                if (preg_match(self::PREDB_REGEX, $release->movie_name, $hit)) {
                    $newName = $hit[1];
                } elseif (preg_match('/(.+),(\sRMZ\.cr)?$/i', $release->movie_name, $hit)) {
                    $newName = $hit[1];
                } else {
                    $newName = $release->movie_name;
                }
            }

            if ($newName !== '') {
                $this->updateRelease($release, $newName, 'MediaInfo: Movie Name', $echo, $type, $nameStatus, $show, $release->predb_id);

                return true;
            }
        }
        $this->_updateSingleColumn('proc_uid', self::PROC_UID_DONE, $release->releases_id);

        return false;
    }

    /**
     * Look for a name based on xxx release filename.
     *
     *
     *
     * @throws \Exception
     */
    public function xxxNameCheck($release, $echo, $type, $nameStatus, $show): bool
    {

        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            $result = Release::fromQuery(
                sprintf(
                    "
				SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
						rf.releases_id AS fileid, rel.id AS releases_id
					FROM releases rel
					INNER JOIN release_files rf ON (rf.releases_id = {$release->releases_id})
					WHERE (rel.isrenamed = %d OR rel.categories_id IN(%d, %d))
					AND rf.name LIKE %s",
                    self::IS_RENAMED_NONE,
                    Category::OTHER_MISC,
                    Category::OTHER_HASHED,
                    escapeString('%SDPORN%')
                )
            );

            foreach ($result as $res) {
                if (preg_match('/^.+?SDPORN/i', $res->textstring, $hit)) {
                    $this->updateRelease(
                        $release,
                        $hit['0'],
                        'fileCheck: XXX SDPORN',
                        $echo,
                        $type,
                        $nameStatus,
                        $show
                    );

                    return true;
                }
            }
        }
        $this->_updateSingleColumn('proc_files', self::PROC_FILES_DONE, $release->releases_id);

        return false;
    }

    /**
     * Look for a name based on .srr release files extension.
     *
     * SRR files (Scene Release Renamer) contain the original scene release name
     * and are highly reliable sources for name extraction.
     *
     * @throws \Exception
     */
    public function srrNameCheck($release, $echo, $type, $nameStatus, $show): bool
    {

        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            $result = Release::fromQuery(
                sprintf(
                    "
				    SELECT rf.name AS textstring, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id,
						rf.releases_id AS fileid, rel.id AS releases_id
					FROM releases rel
					INNER JOIN release_files rf ON (rf.releases_id = {$release->releases_id})
					WHERE (rel.isrenamed = %d OR rel.categories_id IN (%d, %d))
					AND (rf.name LIKE %s OR rf.name LIKE %s)",
                    self::IS_RENAMED_NONE,
                    Category::OTHER_MISC,
                    Category::OTHER_HASHED,
                    escapeString('%.srr'),
                    escapeString('%.srs')
                )
            );

            foreach ($result as $res) {
                // Extract release name from SRR filename
                $extractedName = null;

                // Try .srr extension first
                if (preg_match('/^(.+)\.srr$/i', $res->textstring, $hit)) {
                    $extractedName = $hit[1];
                }
                // Try .srs extension (Scene Release Signature)
                elseif (preg_match('/^(.+)\.srs$/i', $res->textstring, $hit)) {
                    $extractedName = $hit[1];
                }

                // Validate and clean the extracted name
                if ($extractedName !== null) {
                    // Remove any path components
                    if (preg_match('/[\\\\\/]([^\\\\\/]+)$/', $extractedName, $pathMatch)) {
                        $extractedName = $pathMatch[1];
                    }

                    // Ensure it looks like a valid scene release name
                    if (preg_match(self::PREDB_REGEX, $extractedName)) {
                        $this->updateRelease(
                            $release,
                            $extractedName,
                            'fileCheck: SRR extension',
                            $echo,
                            $type,
                            $nameStatus,
                            $show
                        );

                        return true;
                    }
                }
            }
        }
        $this->_updateSingleColumn('proc_srr', self::PROC_SRR_DONE, $release->releases_id);

        return false;
    }

    /**
     * Look for a name based on par2 hash_16K block.
     *
     *
     *
     * @throws \Exception
     */
    public function hashCheck($release, $echo, $type, $nameStatus, $show): bool
    {

        if (! $this->done && $this->relid !== (int) $release->releases_id) {
            $result = Release::fromQuery(sprintf(
                '
				SELECT r.id AS releases_id, r.size AS relsize, r.name AS textstring, r.searchname, r.fromname, r.predb_id
				FROM releases r
				LEFT JOIN par_hashes ph ON ph.releases_id = r.id
				WHERE ph.hash = %s
				AND ph.releases_id != %d
				AND (r.predb_id > 0 OR r.anidbid > 0)',
                escapeString($release->hash),
                $release->releases_id
            ));

            foreach ($result as $res) {
                $floor = round(($res->relsize - $release->relsize) / $res->relsize * 100, 1);
                if ($floor >= -5 && $floor <= 5) {
                    $this->updateRelease(
                        $release,
                        $res->searchname,
                        'hashCheck: PAR2 hash_16K',
                        $echo,
                        $type,
                        $nameStatus,
                        $show,
                        $res->predb_id
                    );

                    return true;
                }
            }
        }
        $this->_updateSingleColumn('proc_hash16k', self::PROC_HASH16K_DONE, $release->releases_id);

        return false;
    }

    /**
     * Look for a name based on rar crc32 hash.
     *
     *
     *
     * @throws \Exception
     */
    public function crcCheck($release, $echo, $type, $nameStatus, $show): bool
    {

        if (! $this->done && $this->relid !== (int) $release->releases_id && $release->textstring !== '') {
            $result = Release::fromQuery(
                sprintf(
                    '
				    SELECT rf.crc32, rel.categories_id, rel.name, rel.searchname, rel.fromname, rel.groups_id, rel.size as relsize, rel.predb_id as predb_id,
						rf.releases_id AS fileid, rel.id AS releases_id
					FROM releases rel
					LEFT JOIN release_files rf ON rf.releases_id = rel.id
					WHERE rel.predb_id > 0
					AND rf.crc32 = %s',
                    escapeString($release->textstring)
                )
            );

            foreach ($result as $res) {
                $floor = round(($res->relsize - $release->relsize) / $res->relsize * 100, 1);
                if ($floor >= -5 && $floor <= 5) {
                    $this->updateRelease(
                        $release,
                        $res->searchname,
                        'crcCheck: CRC32',
                        $echo,
                        $type,
                        $nameStatus,
                        $show,
                        $res->predb_id
                    );

                    return true;
                }
            }
        }
        $this->_updateSingleColumn('proc_crc32', self::PROC_CRC_DONE, $release->releases_id);

        return false;
    }

    /**
     * Resets NameFixer status variables for new processing.
     */
    public function reset(): void
    {
        $this->done = $this->matched = false;
    }

    private function preMatch(string $fileName): array
    {
        $result = preg_match('/(\d{2}\.\d{2}\.\d{2})+([\w\-.]+[\w]$)/i', $fileName, $hit);

        return [$result === 1, $hit[0] ?? ''];
    }

    /**
     * @throws \Exception
     */
    public function preDbFileCheck($release, bool $echo, string $type, $nameStatus, bool $show): bool
    {

        $this->_fileName = $release->textstring;
        $this->_cleanMatchFiles();
        $this->cleanFileNames();
        if (! empty($this->_fileName)) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $results = $this->elasticsearch->searchPreDb($this->_fileName);
                foreach ($results as $hit) {
                    if (! empty($hit)) {
                        $this->updateRelease($release, $hit['title'], 'PreDb: Filename match', $echo, $type, $nameStatus, $show, $hit['id']);

                        return true;
                    }
                }
            } else {
                $predbSearch = Arr::get($this->manticore->searchIndexes('predb_rt', $this->_fileName, ['filename', 'title']), 'data');
                if (! empty($predbSearch)) {
                    foreach ($predbSearch as $hit) {
                        if (! empty($hit)) {
                            $this->updateRelease($release, $hit['title'], 'PreDb: Filename match', $echo, $type, $nameStatus, $show);

                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function preDbTitleCheck($release, bool $echo, string $type, $nameStatus, bool $show): bool
    {

        $this->_fileName = $release->textstring;
        $this->_cleanMatchFiles();
        $this->cleanFileNames();
        if (! empty($this->_fileName)) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $results = $this->elasticsearch->searchPreDb($this->_fileName);
                foreach ($results as $hit) {
                    if (! empty($hit)) {
                        $this->updateRelease($release, $hit['title'], 'PreDb: Title match', $echo, $type, $nameStatus, $show, $hit['id']);

                        return true;
                    }
                }
            } else {
                $results = Arr::get($this->manticore->searchIndexes('predb_rt', $this->_fileName, ['title']), 'data');
                if (! empty($results)) {
                    foreach ($results as $hit) {
                        if (! empty($hit)) {
                            $this->updateRelease($release, $hit['title'], 'PreDb: Title match', $echo, $type, $nameStatus, $show);

                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Clean and normalize filenames for PreDB matching.
     *
     * Handles modern video formats, scene naming conventions, and various
     * file format indicators to produce cleaner release names.
     */
    private function cleanFileNames(): array|string|null
    {
        // Handle language/country suffixes and quality indicators at end of filename
        if (preg_match('/(\.[a-zA-Z]{2})?(\.4k|\.fullhd|\.hd|\.int|\.internal|\.\d+)?$/i', $this->_fileName, $hit)) {
            // Remove 2-letter country/language codes that appear before quality
            if (! empty($hit[1]) && preg_match('/\.[a-zA-Z]{2}\./i', $hit[1])) {
                // Only remove if it's not a valid scene group suffix
                if (! preg_match('/\-(en|de|fr|es|it|nl|pt|ru|pl|jp|kr|cn)$/i', $this->_fileName)) {
                    $this->_fileName = preg_replace('/\.[a-zA-Z]{2}\./i', '.', $this->_fileName);
                }
            }

            // Normalize quality indicators
            if (! empty($hit[2])) {
                $qualityMap = [
                    '/\.4k$/i' => '.2160p',
                    '/\.fullhd$/i' => '.1080p',
                    '/\.hd$/i' => '.720p',
                    '/\.int$/i' => '.INTERNAL',
                    '/\.internal$/i' => '.INTERNAL',
                    '/\.\d+$/' => '', // Remove trailing numbers (often file indices)
                ];

                foreach ($qualityMap as $pattern => $replacement) {
                    $this->_fileName = preg_replace($pattern, $replacement, $this->_fileName);
                }
            }

            // Remove leading group prefixes (common in some releases)
            if (preg_match('/^[a-zA-Z]{0,7}\./', $this->_fileName)) {
                // Only remove if it looks like a prefix (short, before the main name)
                // Don't remove if the whole name is short
                if (strlen($this->_fileName) > 15) {
                    $this->_fileName = preg_replace('/^[a-zA-Z]{0,5}\.(?=[A-Za-z0-9]+[\.\-_])/', '', $this->_fileName);
                }
            }
        }

        // Normalize UHD/HDR indicators for modern releases
        $modernNormalizations = [
            '/\.UHD\./i' => '.2160p.UHD.',
            '/\.HDR\./i' => '.HDR.',
            '/\.DV\./i' => '.DV.',
            '/\.Atmos\./i' => '.Atmos.',
            '/\.REMUX\./i' => '.REMUX.',
            '/\.COMPLETE\./i' => '.COMPLETE.',
            '/\.MULTi\./i' => '.MULTi.',
        ];

        foreach ($modernNormalizations as $pattern => $replacement) {
            if (preg_match($pattern, $this->_fileName) && ! preg_match('/' . preg_quote($replacement, '/') . '/', $this->_fileName)) {
                // Only add if not already present in normalized form
                $this->_fileName = preg_replace($pattern, $replacement, $this->_fileName);
            }
        }

        return $this->_fileName;
    }

    /**
     * @return string|string[]
     */
    private function escapeString($string): array|string
    {
        $from = ['+', '-', '=', '&&', '|', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':', '\\', '/'];
        $to = ["\+", "\-", "\=", "\&&", "\|", "\!", "\(", "\)", "\{", "\}", "\[", "\]", "\^", '\"', "\~", "\*", "\?", "\:", '\\\\', "\/"];

        return str_replace($from, $to, $string);
    }

    /**
     * Normalize a candidate title by stripping trailing part/vol/rNN tokens and trivial suffixes.
     */
    private function normalizeCandidateTitle(string $title): string
    {
        $t = trim($title);
        // Remove common video file extensions
        $t = preg_replace('/\.(mkv|avi|mp4|m4v|mpg|mpeg|wmv|flv|mov|ts|vob|iso|divx)$/i', '', $t) ?? $t;
        // Remove archive and metadata file extensions
        $t = preg_replace('/\.(par2?|nfo|sfv|nzb|rar|zip|7z|gz|tar|bz2|xz|r\d{2,3}|\d{3}|pkg|exe|msi|jpe?g|png|gif|bmp)$/i', '', $t) ?? $t;
        // Remove common trailing segment markers like .part01, .vol12+3, -r12, r12
        $t = preg_replace('/[.\-_ ](?:part|vol|r)\d+(?:\+\d+)?$/i', '', $t) ?? $t;
        // Collapse multiple spaces/underscores
        $t = preg_replace('/[\s_]+/', ' ', $t) ?? $t;

        // Trim stray punctuation
        return trim($t, " .-_\t\r\n");
    }

    /**
     * Heuristic filter to avoid poor renames (e.g., short or generic names) from untrusted sources.
     */
    private function isPlausibleReleaseTitle(string $title): bool
    {
        $t = trim($title);
        if ($t === '') {
            return false;
        }
        // Minimum length and token count
        if (strlen($t) < 12) {
            return false;
        }
        $wordCount = preg_match_all('/[A-Za-z0-9]{3,}/', $t);
        if ($wordCount < 2) {
            return false;
        }

        // Reject hashed/obfuscated names early
        if ($this->looksLikeHashedName($t)) {
            return false;
        }

        // Reject if it still ends with a segment marker
        if (preg_match('/(?:^|[.\-_ ])(?:part|vol|r)\d+(?:\+\d+)?$/i', $t)) {
            return false;
        }
        // Reject generic installer/setup filenames
        if (preg_match('/^(setup|install|installer|patch|update|crack|keygen)\d*[\s._-]/i', $t)) {
            return false;
        }
        // Acceptance criteria
        $hasGroupSuffix = (bool) preg_match('/[-.][A-Za-z0-9]{2,}$/', $t);
        $hasYear = (bool) preg_match('/\b(19|20)\d{2}\b/', $t);
        $hasQuality = (bool) preg_match('/\b(480p|720p|1080p|2160p|4k|webrip|web[ .-]?dl|bluray|bdrip|dvdrip|hdtv|hdrip|xvid|x264|x265|hevc|h\.?264|ts|cam|r5|proper|repack)\b/i', $t);
        $hasTV = (bool) preg_match('/\bS\d{1,2}[Eex]\d{1,3}\b/i', $t);
        $hasXXX = (bool) preg_match('/\bXXX\b/i', $t);

        // Accept if:
        // - Has a group suffix (e.g., -ETHEL or .NBQ)
        // - Has TV episode identifier with quality indicator (e.g., S03E14 + 720p)
        // - Has year with quality or TV identifier
        // - Has XXX tag
        // - Has quality indicator (standalone)
        // - Has TV episode identifier (standalone)
        if ($hasGroupSuffix || ($hasTV && $hasQuality) || ($hasYear && ($hasQuality || $hasTV)) || $hasXXX || $hasQuality || $hasTV) {
            return true;
        }

        return false;
    }

    /**
     * Detect if a string looks like a hashed/obfuscated name (random alphanumeric strings, UUIDs, etc.)
     */
    private function looksLikeHashedName(string $title): bool
    {
        $t = trim($title);

        // Remove common file extensions for analysis
        $cleaned = preg_replace('/\.(mkv|avi|mp4|m4v|mpg|mpeg|wmv|flv|mov|ts|vob|iso|divx|par2?|nfo|sfv|nzb|rar|r\d{2,3}|zip|7z|gz|tar|001)$/i', '', $t);
        // Remove common separators to get the core name
        $coreName = preg_replace('/[.\-_\s]+/', '', $cleaned);

        // Reject UUID patterns: 8-4-4-4-12 hex format
        if (preg_match('/^[a-f0-9]{8}-?[a-f0-9]{4}-?[a-f0-9]{4}-?[a-f0-9]{4}-?[a-f0-9]{12}$/i', $coreName)) {
            return true;
        }

        // Reject pure hex strings (MD5, SHA1, etc.) - at least 16 hex chars with no other content
        if (preg_match('/^[a-f0-9]{16,}$/i', $coreName)) {
            return true;
        }

        // Check for long alphanumeric strings that look random/obfuscated
        $coreLen = strlen($coreName);
        if ($coreLen >= 16 && preg_match('/^[a-zA-Z0-9]+$/', $coreName)) {
            // Count character type transitions (uppercase<->lowercase, letter<->digit)
            $transitions = 0;
            for ($i = 1; $i < $coreLen; $i++) {
                $prev = $coreName[$i - 1];
                $curr = $coreName[$i];
                $prevIsDigit = ctype_digit($prev);
                $currIsDigit = ctype_digit($curr);
                $prevIsUpper = ctype_upper($prev);
                $currIsUpper = ctype_upper($curr);

                // Count type transitions
                if ($prevIsDigit !== $currIsDigit) {
                    $transitions++;
                } elseif (!$prevIsDigit && !$currIsDigit && $prevIsUpper !== $currIsUpper) {
                    $transitions++;
                }
            }

            // Calculate transition rate
            $transitionRate = $transitions / ($coreLen - 1);

            // High transition rate (>0.35) suggests random/hashed content
            // Most legitimate release names have words that are consistent case
            if ($transitionRate > 0.35) {
                // Additional check: does it contain any recognizable media-related words?
                if (!preg_match('/\b(movie|film|series|episode|season|show|video|audio|music|album|dvd|bluray|hdtv|webrip|xvid|x264|x265|hevc|aac|mp3|flac|720p|1080p|2160p|4k|complete|proper|repack|dubbed|subbed|english|french|german|spanish|italian|rip|web|hdr|remux|disc|internal|retail)\b/i', $t)) {
                    return true;
                }
            }

            // Check for strings that look like random IDs (mixed case + digits, no real words)
            // Count consecutive same-type characters
            $maxConsecutiveLetters = 0;
            $currentConsecutive = 0;
            $lastWasLetter = false;

            for ($i = 0; $i < $coreLen; $i++) {
                $isLetter = ctype_alpha($coreName[$i]);
                if ($isLetter) {
                    if ($lastWasLetter) {
                        $currentConsecutive++;
                    } else {
                        $currentConsecutive = 1;
                    }
                    $maxConsecutiveLetters = max($maxConsecutiveLetters, $currentConsecutive);
                }
                $lastWasLetter = $isLetter;
            }

            // If long string has no word-like sequences (5+ consecutive letters), likely obfuscated
            // Real release names have words like "Movie", "Show", "Episode", "BluRay", etc.
            if ($coreLen >= 20 && $maxConsecutiveLetters < 5) {
                // But allow if it matches common patterns (like numbers at end for parts)
                if (!preg_match('/^[a-zA-Z]+\d{1,4}$/', $coreName)) {
                    return true;
                }
            }

            // Reject if no lowercase letters at all or no uppercase letters (unusual for real names)
            // combined with digits mixed in
            $hasLower = preg_match('/[a-z]/', $coreName);
            $hasUpper = preg_match('/[A-Z]/', $coreName);
            $hasDigit = preg_match('/\d/', $coreName);
            $digitCount = preg_match_all('/\d/', $coreName);

            // If digits make up significant portion of a long mixed-case string, likely obfuscated
            if ($coreLen >= 24 && $hasLower && $hasUpper && $hasDigit) {
                $digitRatio = $digitCount / $coreLen;
                if ($digitRatio >= 0.2 && $digitRatio <= 0.6 && $maxConsecutiveLetters < 6) {
                    return true;
                }
            }
        }

        // Reject common obfuscation patterns: random-looking prefix/suffix with numbers
        if (preg_match('/^[a-zA-Z]{1,3}\d{6,}[a-zA-Z]*$/i', $coreName) ||
            preg_match('/^[a-zA-Z0-9]{2,4}\d{8,}$/i', $coreName)) {
            return true;
        }

        return false;
    }
}
