<?php

namespace App\Services\AdditionalProcessing;

use App\Models\Predb;
use App\Models\ReleaseInform;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\NameFixing\Extractors\FileNameExtractor;
use App\Services\NameFixing\FileNameCleaner;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\NameFixing\SeasonPackDetector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Service for release renaming based on file names and PreDB matching.
 * Handles matching release names to PreDB entries using inner file names from archives.
 */
class ReleaseRenamingService
{
    public function __construct(
        private readonly ProcessingConfiguration $config,
        private readonly NameFixingService $nameFixer,
        private readonly ReleaseUpdateService $updateService
    ) {}

    /**
     * Perform filename-based checks and rename release if match found.
     *
     * @param  array<int, array<string, mixed>>  $fileData  Array of file information with names
     * @param  ReleaseProcessingContext  $context  The processing context
     * @param  array<string, mixed>  $dataSummary  Optional archive data summary for nested archives
     * @return bool True if rename was performed
     */
    public function fileNameChecks(array $fileData, ReleaseProcessingContext $context, array $dataSummary = []): bool
    {
        $rarFileName = Arr::pluck($fileData, 'name');

        // Check ReleaseInform table for pre-matched names
        // relOName matches case-insensitively; only an inform for this exact name applies.
        $informName = ReleaseInform::query()
            ->where(['relOName' => $context->release->searchname])
            ->limit(25)
            ->get()
            ->first(static fn (ReleaseInform $inform): bool => $inform->relOName === (string) $context->release->searchname);

        if ($informName !== null) {
            if (preg_match(ReleaseUpdateService::PREDB_REGEX, $informName->relPName, $hit)) {
                $preCheck = Predb::whereTitle($hit[0])->first();
                $context->release->preid = $preCheck !== null ? $preCheck->id : 0;
                $candidate = $preCheck->title ?? ucwords($hit[0], '.');
                $candidate = $this->normalizeCandidateTitle($candidate);

                if ($this->isPlausibleReleaseTitle($candidate)) {
                    $this->updateService->updateRelease(
                        $context->release,
                        $candidate,
                        'RarInfo FileName Match',
                        true,
                        'Filenames, ',
                        true,
                        true,
                        $context->release->preid
                    );
                    Log::channel('filename_rename')->info(
                        'RarInfo FileName Match: '.$context->release->searchname.' -> '.$candidate
                    );

                    return true;
                } elseif ($this->config->debugMode) {
                    Log::debug('RarInfo: Ignored low-quality candidate "'.$candidate.'" from ReleaseInform.');
                }
            } else {
                $this->updateService->updateRelease(
                    $context->release,
                    $informName->relPName,
                    'ReleaseInform FileName Match',
                    true,
                    'Filenames, ',
                    true,
                    true
                );
                Log::channel('filename_rename')->info(
                    'ReleaseInform FileName Match: '.$context->release->searchname.' -> '.$informName->relPName
                );

                return true;
            }
        }

        // Don't rename parity/PAR2 releases — they are support file releases
        if ($this->isParityRelease($context->release->searchname)) {
            return false;
        }

        // A season pack holds many episodes: name it after the season, never after one episode
        $names = $context->archiveFileNames !== [] ? $context->archiveFileNames : $this->listedFileNames($rarFileName, $dataSummary);
        $seasonPack = $context->seasonPack
            || $this->nameFixer->isSeasonPack((string) $context->release->searchname)
            || SeasonPackDetector::isMultiEpisodeSet($names);
        if ($seasonPack) {
            $handled = $this->seasonPackNameChecks($context, $names, 'RarInfo Season Pack Match');
            if ($handled !== null) {
                return $handled;
            }
            if ($this->isGoodSeasonPackName((string) $context->release->searchname)) {
                return false;
            }
        }

        // Check for specific trusted posters
        $trustedPosters = [
            'onlyencodes <oe@onlyencodes.cc>',
            'iVy <ivy@worldsbestencodes.yeah>',
            'found@nzbforyou.com',
        ];

        if (in_array($context->release->fromname, $trustedPosters)
            && preg_match(ReleaseUpdateService::PREDB_REGEX, $context->release->name, $match)
        ) {
            $candidate = ucwords($match[0], '.');
            $candidate = $this->normalizeCandidateTitle($candidate);

            if ($this->isPlausibleReleaseTitle($candidate)) {
                $this->updateService->updateRelease(
                    $context->release,
                    $candidate,
                    'PreDB Regex Match',
                    true,
                    'Filenames, ',
                    true,
                    true,
                    $context->release->preid
                );
                Log::channel('filename_rename')->info(
                    'PreDB Regex Match: '.$context->release->searchname.' -> '.$candidate
                );

                return true;
            } elseif ($this->config->debugMode) {
                Log::debug('PreDB Regex: Ignored low-quality candidate "'.$candidate.'" from poster name.');
            }
        }

        // Check inner file names from archive
        if (! empty($rarFileName) && isset($rarFileName[0])
            && ! ($seasonPack && $this->isEpisodeOnlyName($rarFileName[0]))
            && preg_match(ReleaseUpdateService::PREDB_REGEX, $rarFileName[0], $hit)
        ) {
            $preCheck = Predb::whereTitle($hit[0])->first();
            $context->release->preid = $preCheck !== null ? $preCheck->id : 0;
            $candidate = $preCheck->title ?? ucwords($hit[0], '.');
            $candidate = $this->normalizeCandidateTitle($candidate);

            if ($this->isPlausibleReleaseTitle($candidate)) {
                $this->updateService->updateRelease(
                    $context->release,
                    $candidate,
                    'RarInfo FileName Match',
                    true,
                    'Filenames, ',
                    true,
                    true,
                    $context->release->preid
                );
                Log::channel('filename_rename')->info(
                    'RarInfo FileName Match: '.$context->release->searchname.' -> '.$candidate
                );

                return true;
            } elseif ($this->config->debugMode) {
                Log::debug('RarInfo: Ignored low-quality candidate "'.$candidate.'" from inner file name.');
            }
        }

        // Check nested archive file names
        if (! empty($dataSummary['archives']) && ! empty($rarFileName) && isset($rarFileName[0])
            && ! empty($dataSummary['archives'][$rarFileName[0]]['file_list'])
        ) {
            $archiveData = $dataSummary['archives'][$rarFileName[0]]['file_list'];
            $archiveFileName = Arr::pluck($archiveData, 'name');

            if (! empty($archiveFileName) && isset($archiveFileName[0])
                && ! ($seasonPack && $this->isEpisodeOnlyName($archiveFileName[0]))
                && preg_match(ReleaseUpdateService::PREDB_REGEX, $archiveFileName[0], $match2)
            ) {
                $preCheck = Predb::whereTitle($match2[0])->first();
                $context->release->preid = $preCheck !== null ? $preCheck->id : 0;
                $candidate = $preCheck->title ?? ucwords($match2[0], '.');
                $candidate = $this->normalizeCandidateTitle($candidate);

                if ($this->isPlausibleReleaseTitle($candidate)) {
                    $this->updateService->updateRelease(
                        $context->release,
                        $candidate,
                        'RarInfo FileName Match',
                        true,
                        'Filenames, ',
                        true,
                        true,
                        $context->release->preid
                    );
                    Log::channel('filename_rename')->info(
                        'RarInfo FileName Match: '.$context->release->searchname.' -> '.$candidate
                    );

                    return true;
                } elseif ($this->config->debugMode) {
                    Log::debug('RarInfo: Ignored low-quality candidate "'.$candidate.'" from nested archive file name.');
                }
            }
        }

        return false;
    }

    /**
     * Attempt to rename a release using raw (uncompressed) media file names from the NZB.
     *
     * For releases posted without archives (e.g. .mkv/.mp4 posted directly),
     * extracts a release name from the NZB subject file names. Skips obfuscated/hashed
     * file names. Detects multi-episode releases and collapses them to the series name.
     *
     * @param  ReleaseProcessingContext  $context  The processing context (must have rawMediaFileEntries populated)
     * @param  callable  $subjectExtractor  Function(string $subject, string $ext): string to extract filename from NZB subject
     * @return bool True if rename was performed
     */
    public function rawFileNameChecks(ReleaseProcessingContext $context, callable $subjectExtractor): bool
    {
        if (empty($context->rawMediaFileEntries)) {
            return false;
        }

        // Don't rename if already renamed
        if ((int) ($context->release->isrenamed ?? 0) === 1) {
            return false;
        }

        // Don't rename parity/PAR2 releases — they are support file releases
        // and renaming them strips the .vol/.par2 indicator, making them look
        // like the actual content release (which causes false duplicates)
        if ($this->isParityRelease($context->release->searchname)) {
            if ($this->config->debugMode) {
                Log::debug('Raw file rename: Skipping parity release "'.$context->release->searchname.'"');
            }

            return false;
        }

        $cleaner = new FileNameCleaner;
        $extractor = new FileNameExtractor($cleaner);

        // Collect clean filenames from raw media entries, skipping obfuscated ones
        $cleanNames = [];
        $largestFile = null;
        $largestSize = 0;

        foreach ($context->rawMediaFileEntries as $rawFile) {
            $title = $rawFile['title'];
            $extension = $rawFile['extension'];
            $fileSize = $rawFile['size'];

            $cleanName = $subjectExtractor($title, $extension);

            if ($cleanName === '' || $cleanName === 'unknown.'.$extension) {
                continue;
            }

            // Skip obfuscated/hashed filenames
            $baseName = preg_replace('/\.[a-z0-9]{2,5}$/i', '', $cleanName);
            if ($cleaner->looksLikeHashedName($baseName)) {
                if ($this->config->debugMode) {
                    Log::debug('Raw file rename: Skipping obfuscated filename "'.$cleanName.'"');
                }

                continue;
            }

            $cleanNames[] = $cleanName;

            // Track the largest file for best-candidate selection
            if ($fileSize > $largestSize) {
                $largestSize = $fileSize;
                $largestFile = $cleanName;
            }
        }

        if (empty($cleanNames)) {
            return false;
        }

        // Several episodes of one show (or a release named as a season) are a season pack
        $currentName = (string) $context->release->searchname;
        $seasonPack = $context->seasonPack
            || $this->nameFixer->isSeasonPack($currentName)
            || SeasonPackDetector::isMultiEpisodeSet($cleanNames);
        if ($seasonPack) {
            $handled = $this->seasonPackNameChecks($context, $cleanNames, 'Raw file: season pack');
            if ($handled !== null) {
                return $handled;
            }
            if ($this->isGoodSeasonPackName($currentName)) {
                return false;
            }
        }

        // Detect multi-episode release: multiple files sharing the same series prefix
        // but differing only in episode number (e.g. Show.S01E01.mkv, Show.S01E02.mkv)
        $candidate = $this->resolveMultiEpisodeCandidate($cleanNames, $largestFile, $cleaner);

        if ($seasonPack && $this->isEpisodeOnlyName($candidate)) {
            return false;
        }

        // Try FileNameExtractor patterns first
        $extractResult = $extractor->extractFromFile($candidate);
        if ($extractResult !== null) {
            $newName = $extractResult->newName;
            $newName = $this->normalizeCandidateTitle($newName);

            if ($this->isPlausibleReleaseTitle($newName)) {
                // Check PreDB for a matching title
                $preCheck = null;
                if (preg_match(ReleaseUpdateService::PREDB_REGEX, $newName, $hit)) {
                    $preCheck = Predb::whereTitle($hit[0])->first();
                }
                $preId = $preCheck !== null ? $preCheck->id : 0;
                $finalName = $preCheck->title ?? $newName;

                $this->updateService->updateRelease(
                    $context->release,
                    $finalName,
                    'Raw file: '.$extractResult->method,
                    true,
                    'Filenames, ',
                    true,
                    true,
                    $preId
                );
                Log::channel('filename_rename')->info(
                    'Raw file rename: '.$context->release->searchname.' -> '.$finalName
                );

                return true;
            } elseif ($this->config->debugMode) {
                Log::debug('Raw file rename: Ignored low-quality candidate "'.$newName.'" from extractor.');
            }
        }

        // Fallback: try PreDB matching on the cleaned base name
        $cleanedForMatch = $cleaner->cleanForMatching($candidate);
        if ($cleanedForMatch !== false && strlen($cleanedForMatch) >= 8) {
            if (preg_match(ReleaseUpdateService::PREDB_REGEX, $cleanedForMatch, $hit)) {
                $preCheck = Predb::whereTitle($hit[0])->first();
                if ($preCheck !== null && $preCheck->title !== $context->release->searchname) {
                    $this->updateService->updateRelease(
                        $context->release,
                        $preCheck->title,
                        'Raw file PreDB match',
                        true,
                        'Filenames, ',
                        true,
                        true,
                        $preCheck->id
                    );
                    Log::channel('filename_rename')->info(
                        'Raw file PreDB match: '.$context->release->searchname.' -> '.$preCheck->title
                    );

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolve the best candidate name from raw file entries.
     *
     * If multiple unique files share the same series prefix and contain distinct episode
     * numbers, this is a multi-episode release — collapse to the series-level name
     * (e.g. "Show.S01.720p.WEB-DL.x264-GROUP" instead of "Show.S01E01...").
     *
     * @param  array<string>  $cleanNames  The cleaned filenames
     * @param  string|null  $largestFile  The filename of the largest file
     * @return string The best candidate filename
     */
    private function resolveMultiEpisodeCandidate(array $cleanNames, ?string $largestFile, FileNameCleaner $cleaner): string
    {
        $uniqueCleanNames = [];
        foreach ($cleanNames as $name) {
            $uniqueCleanNames[strtolower($name)] ??= $name;
        }
        $cleanNames = array_values($uniqueCleanNames);

        if (count($cleanNames) === 1) {
            // Single file — check for multi-episode in one file (S01E01E02)
            $name = $cleanNames[0];
            if (preg_match('/^(.+?S\d{1,3})[.\-_ ]?E\d{1,3}(?:[.\-_ ]?E\d{1,3})+(.+)$/i', $name, $match)) {
                // Collapse multi-episode markers to just the season: Show.S01.rest
                return $match[1].$match[2];
            }

            return $name;
        }

        // Multiple files: collapse distinct episodes of one show to the season-level name
        $collapsed = SeasonPackDetector::seasonNameFromEpisodes($cleanNames);
        if ($collapsed !== null) {
            if ($this->config->debugMode) {
                Log::debug('Raw file rename: Multi-episode detected, collapsed to "'.$collapsed.'"');
            }

            return $collapsed;
        }

        // Files don't share episode pattern or have different series — use the largest file
        return $largestFile ?? $cleanNames[0];
    }

    /**
     * Rename a season pack to the season-level name derived from its file names.
     *
     * A pack that already carries a plausible season name keeps it unless PreDB holds
     * the derived title; an obfuscated or implausible name takes the derived name.
     *
     * @param  list<string>  $names  File names (or paths) listed from the release
     * @return bool|null True if renamed, false if not, null when the names carry no
     *                   episode or season-folder evidence to decide on
     */
    public function seasonPackNameChecks(ReleaseProcessingContext $context, array $names, string $method): ?bool
    {
        $currentName = (string) $context->release->searchname;
        $folder = SeasonPackDetector::seasonFolder($names);
        $episodes = array_values(array_filter(
            $names,
            static fn (string $name): bool => SeasonPackDetector::isEpisodeName(basename(str_replace('\\', '/', $name))),
        ));
        if ($folder === null && $episodes === []) {
            return null;
        }

        $candidate = $folder ?? SeasonPackDetector::seasonNameFromEpisodes($episodes);
        if ($candidate === null && ($context->seasonPack || SeasonPackDetector::isSeasonPack($currentName))) {
            $candidate = SeasonPackDetector::seasonNameFromEpisode($episodes[0]);
        }
        if ($candidate === null) {
            return false;
        }

        $candidate = $this->normalizeCandidateTitle($candidate);
        if (! SeasonPackDetector::isSeasonPack($candidate)) {
            return false;
        }

        $preCheck = Predb::whereTitle($candidate)->first();
        if ($this->isGoodSeasonPackName($currentName)
            && ($preCheck === null || strcasecmp((string) $preCheck->title, $currentName) === 0)
        ) {
            if ($this->config->debugMode) {
                Log::debug('Season pack: kept "'.$currentName.'", derived "'.$candidate.'" has no PreDB match');
            }

            return false;
        }

        if ($preCheck === null && ! $this->isPlausibleReleaseTitle($candidate)) {
            return false;
        }

        $finalName = $preCheck->title ?? $candidate;
        if (strcasecmp((string) $finalName, $currentName) === 0) {
            return false;
        }

        $context->release->preid = $preCheck->id ?? 0;
        $this->updateService->updateRelease(
            $context->release,
            (string) $finalName,
            $method,
            true,
            'Filenames, ',
            true,
            true,
            $context->release->preid
        );
        Log::channel('filename_rename')->info($method.': '.$currentName.' -> '.$finalName);

        return true;
    }

    /**
     * A plausible, non-obfuscated season-level name that file names alone should not replace.
     */
    private function isGoodSeasonPackName(string $name): bool
    {
        return SeasonPackDetector::isSeasonPack($name)
            && $this->isPlausibleReleaseTitle($name)
            && ! (new FileNameCleaner)->looksLikeHashedName($name);
    }

    private function isEpisodeOnlyName(string $name): bool
    {
        return SeasonPackDetector::isEpisodeName($name) && ! SeasonPackDetector::isSeasonPack($name);
    }

    /**
     * @param  array<int, mixed>  $rarFileNames
     * @param  array<string, mixed>  $dataSummary
     * @return list<string>
     */
    private function listedFileNames(array $rarFileNames, array $dataSummary): array
    {
        $names = array_map('strval', array_values(array_filter($rarFileNames, static fn (mixed $name): bool => \is_string($name) && $name !== '')));
        foreach ($dataSummary['archives'] ?? [] as $archive) {
            foreach ($archive['file_list'] ?? [] as $file) {
                if (isset($file['name']) && \is_string($file['name']) && $file['name'] !== '') {
                    $names[] = $file['name'];
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Normalize candidate title by trimming and standardizing.
     */
    public function normalizeCandidateTitle(string $title): string
    {
        // Remove leading/trailing whitespace and dots
        $title = trim($title, " \t\n\r\0\x0B.");

        // Convert multiple underscores/dots to single dot
        $title = preg_replace('/[_.]{2,}/', '.', $title);

        return $title;
    }

    /**
     * Check if a candidate title is plausible for a release name.
     * Filters out generic/spam/low-quality candidates.
     */
    public function isPlausibleReleaseTitle(string $candidate): bool
    {
        $t = trim($candidate);

        // Reject if too short
        if (strlen($t) < 5) {
            return false;
        }

        // Reject if contains only generic words
        $genericPatterns = [
            '/^(sample|cover|proof|subs?|readme|nfo|sfv|folder|cd\d*|disc?\d*|dvd\d*)$/i',
            '/^[a-f0-9]{32}$/i', // MD5 hashes
            '/^track\s*\d+$/i',
        ];

        foreach ($genericPatterns as $pattern) {
            if (preg_match($pattern, $t)) {
                return false;
            }
        }

        // Reject if starts with common spam prefixes
        if (preg_match('/^(setup|install|installer|patch|update|crack|keygen)\d*[\s._-]/i', $t)) {
            return false;
        }

        // Reject common generic/fake file names used by spam uploaders
        if ($this->isGenericFileName($t)) {
            return false;
        }

        // Acceptance criteria
        // Group suffix must be preceded by dash and be 2-15 chars (typical scene group naming)
        // Exclude common file extensions that could be mistaken for group names
        $hasGroupSuffix = (bool) preg_match('/-[A-Za-z0-9]{2,15}$/', $t) &&
            ! preg_match('/\.(exe|mp4|avi|mkv|rar|zip|nfo|sfv|par2?|jpg|png|gif|iso|mp3|flac|wav)$/i', $t);
        $hasYear = (bool) preg_match('/\b(19|20)\d{2}\b/', $t);
        $hasQuality = (bool) preg_match(
            '/\b(480p|720p|1080p|2160p|4k|webrip|web[ .-]?dl|bluray|bdrip|dvdrip|hdtv|hdrip|xvid|x264|x265|hevc|h\.?264|ts|cam|r5|proper|repack)\b/i',
            $t
        );
        $hasTV = (bool) preg_match('/\bS\d{1,2}[Eex]\d{1,3}\b/i', $t);
        $hasSeason = SeasonPackDetector::isSeasonPack($t);
        $hasXXX = (bool) preg_match('/\bXXX\b/i', $t);

        // Accept if:
        // - Has a group suffix (e.g., -ETHEL or .NBQ)
        // - Has TV episode or season identifier with quality indicator (e.g., S03E14 or S03 + 720p)
        // - Has year with quality, TV or season identifier
        // - Has XXX tag
        if ($hasGroupSuffix || (($hasTV || $hasSeason) && $hasQuality) || ($hasYear && ($hasQuality || $hasTV || $hasSeason)) || $hasXXX) {
            return true;
        }

        return false;
    }

    /**
     * Detect if a string looks like a generic/fake file name commonly used by spam uploaders.
     * These are typically generic executable names that have nothing to do with the actual content.
     */
    private function isGenericFileName(string $title): bool
    {
        $t = strtolower(trim($title));

        // Remove file extension for comparison
        $t = preg_replace('/\.(exe|mp4|avi|mkv|rar|zip|nfo|sfv|par2?|jpg|png|gif|iso|mp3|flac|wav)$/i', '', $t);
        $t = trim($t, ' .-_');

        // Common generic/fake executable names used by spam uploaders
        $genericNames = [
            'porn games',
            'videolan mp4 video player',
            'duke nukem 3d',
            'citor 3',
            'wallpaper viewer',
            'media player',
            'video player',
            'image viewer',
            'photo viewer',
            'game launcher',
            'installer',
            'setup',
            'readme',
            'sample',
            'cover',
            'proof',
            'erotic porn',
        ];

        foreach ($genericNames as $genericName) {
            if ($t === $genericName) {
                return true;
            }
        }

        // Reject patterns that look like generic executables with spaces
        // e.g., "Some Generic App.Exe", "Random Player.Exe"
        if (preg_match('/^[a-z0-9 ]{3,30}$/i', $t)) {
            // Check if it looks like "Word Word Word" pattern without release indicators
            $words = explode(' ', $t);
            $wordCount = count($words);

            // If it's 2-4 generic words with no year, quality, or group indicators, likely spam
            if ($wordCount >= 2 && $wordCount <= 5) {
                $hasReleaseIndicator = preg_match('/\b(19|20)\d{2}\b/', $t) ||
                    preg_match('/\b(480p|720p|1080p|2160p|4k|webrip|bluray|dvdrip|hdtv|xvid|x264|x265|hevc)\b/i', $t) ||
                    preg_match('/\bS\d{1,2}[Eex]\d{1,3}\b/i', $t);

                if (! $hasReleaseIndicator) {
                    // Check if all words are common English words (potential fake name)
                    $genericWords = ['the', 'a', 'an', 'and', 'or', 'for', 'to', 'in', 'on', 'at', 'by', 'with',
                        'player', 'viewer', 'game', 'games', 'porn', 'video', 'mp4', 'media', 'photo', 'image',
                        'wallpaper', 'launcher', 'app', 'application', 'tool', 'software', 'program', 'erotic',
                        'hot', 'sexy', 'free', 'new', 'best', 'full', 'pro', 'premium', 'ultimate', 'super',
                        '3d', 'hd', 'pro', 'lite', 'plus', 'max', 'ultra', 'standard', 'advanced', 'basic'];

                    $genericWordCount = 0;
                    foreach ($words as $word) {
                        if (in_array(strtolower($word), $genericWords) || strlen($word) <= 2) {
                            $genericWordCount++;
                        }
                    }

                    // If most words are generic, reject it
                    if ($genericWordCount >= $wordCount - 1) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if a release name indicates it is a PAR2/parity repair release.
     *
     * Parity releases (PAR2 volume sets, standalone .par2 files) are support files
     * and should NOT be renamed to look like the actual content release. Doing so
     * creates false duplicates and can cause the real content release to be deleted.
     *
     * Matches patterns like:
     *   - Title-GROUP.vol00-01          (PAR2 volume set without extension)
     *   - Title-GROUP.vol00+01.par2     (PAR2 volume set with extension)
     *   - Title-GROUP.par2              (standalone PAR2 file)
     *   - Title-GROUP.vol001+02.PAR2    (various volume numbering)
     */
    private function isParityRelease(string $name): bool
    {
        return (bool) preg_match('/\.(?:vol\d+[\-+]\d+(?:\.par2?)?|par2?)$/i', $name);
    }
}
