<?php

declare(strict_types=1);

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

/**
 * Categorizer for Movie content including HD, SD, UHD, 3D, Blu-ray, DVD, etc.
 */
class MovieCategorizer extends AbstractCategorizer
{
    protected int $priority = 25;

    // Groups from Categorize.php
    protected const ANIME_GROUPS = [
        'alt.binaries.anime', 'alt.binaries.multimedia.anime.highspeed', 'alt.binaries.multimedia.anime.repost',
        'alt.binaries.multimedia.anime', 'alt.binaries.multimedia.erotica.anime', 'alt.binaries.anime.highspeed',
        'alt.binaries.anime.repost',
    ];

    protected const AUDIO_GROUPS = ['alt.binaries.sounds.music', 'alt.binaries.sounds.misc'];

    // Movie usenet groups
    protected const MOVIE_GROUPS = [
        'alt.binaries.movies',
        'alt.binaries.movies.divx',
        'alt.binaries.movies.xvid',
        'alt.binaries.dvd',
        'alt.binaries.dvdr',
        'alt.binaries.hdtv.x264',
        'alt.binaries.x264',
        'alt.binaries.bluray',
    ];

    public function getName(): string
    {
        return 'Movie';
    }

    /**
     * Check if the group is a movie group.
     */
    protected function isMovieGroup(string $groupName): bool
    {
        if (empty($groupName)) {
            return false;
        }

        return in_array($groupName, self::MOVIE_GROUPS) ||
               (bool) preg_match('/alt\.binaries\.(movies|dvd|bluray|x264)/i', $groupName);
    }

    public function shouldSkip(ReleaseContext $context): bool
    {
        // Skip if this looks like adult content
        if ($context->hasAdultMarkers()) {
            return true;
        }

        // An archive known to hold a PDF or ebook is never a film.
        if ($context->hasDocumentContent()) {
            return true;
        }

        // Don't match anything from Anime groups to Movies
        if ($context->groupName && in_array($context->groupName, self::ANIME_GROUPS)) {
            return true;
        }

        // Don't match anything from Audio groups to Movies unless it contains video-related strings to catch FLAC movies
        if ($context->groupName && in_array($context->groupName, self::AUDIO_GROUPS)) {
            if (! preg_match('/(720|1080|2160|4320)p|UHD|Blu-?ray|Remux|WEB-?DL|HEVC|h264|h265|x264/i', $context->releaseName)) {
                return true;
            }
        }

        // Don't put anything from Anime Tosho in Movies
        if (preg_match('/animetosho/i', $context->poster)) {
            return true;
        }

        // A standalone Sxx token identifies a full TV season even when year or
        // edition markers appear before the source and quality markers.
        if ($context->hasStandaloneSeasonToken()) {
            return true;
        }

        // Skip if it looks like a TV episode (S01E01) or season pack (S01.1080p)
        if (preg_match('/[._ -]S\d{1,3}[._ -]?(E\d|D\d|Complete|Full|1080|720|480|2160|WEB|HDTV|BluRay|NF|AMZN)/i', $context->releaseName)) {
            return true;
        }

        // Skip episode-only patterns (E01, E02) - common in anime
        if (preg_match('/[._ -]E\d{1,4}[._ -]/i', $context->releaseName)) {
            return true;
        }

        // Skip known anime release groups
        if (preg_match('/(?:^|[.\-_ \[])(URANiME|ANiHLS|HaiKU|ANiURL|SkyAnime|Erai-raws|LostYears|Vodes|SubsPlease|Judas|Ember|YuiSubs|ASW|Tsundere-Raws|Anime-Raws)(?:[.\-_ \]]|$)/i', $context->releaseName)) {
            return true;
        }

        // Skip TV-like patterns from Categorize.php
        if (preg_match('/s\d{1,3}[._ -]?[ed]\d{1,3}|auto(cad|desk)|divx[._ -]plus|[._ -]exe$|[._ -](jav|XXX)[._ -]|SWE6RUS|\wXXX(1080p|720p|DVD)|Xilisoft|\-BABA$|\.S[0-9]\d{1,3}\.|GP-MP3|SUPDUB|Formule\.?1\.\d{4}\.GP\d{2}\.[\w.]+\.DUTCH|Kwalificatie|Vrije\.Training(\.\d)?/i', $context->releaseName)) {
            return true;
        }

        // Skip daily TV show date patterns (e.g., Jimmy.Fallon.2020.05.14, Colbert.2024.01.15)
        // Date format: YYYY.MM.DD or YYYY-MM-DD surrounded by separators
        if (preg_match('/[._ -](19|20)\d{2}[._ -]\d{2}[._ -]\d{2}[._ -]/i', $context->releaseName)) {
            return true;
        }

        return false;
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;
        $groupName = $context->groupName;

        // Check if from movie group
        $isFromMovieGroup = $this->isMovieGroup($groupName);

        // Check if it looks like movie content
        if (! $this->looksLikeMovie($name) && ! $isFromMovieGroup) {
            return $this->noMatch();
        }

        // Try specific movie subcategories in order of specificity
        if ($context->categorizeForeign && ($result = $this->checkForeign($name))) {
            return $result;
        }

        if ($result = $this->checkX265($name)) {
            return $result;
        }

        if ($result = $this->checkUHD($name)) {
            return $result;
        }

        if ($result = $this->check3D($name)) {
            return $result;
        }

        if ($result = $this->checkBluRay($name)) {
            return $result;
        }

        if ($result = $this->checkDVD($name)) {
            return $result;
        }

        if ($context->catWebDL && ($result = $this->checkWebDL($name))) {
            return $result;
        }

        if ($result = $this->checkHD($name, $context->catWebDL)) {
            return $result;
        }

        if ($result = $this->checkSD($name)) {
            return $result;
        }

        if ($result = $this->checkOther($name)) {
            return $result;
        }

        // If from movie group but no specific match, categorize by resolution/format
        if ($isFromMovieGroup) {
            return $this->categorizeByResolution($name);
        }

        return $this->noMatch();
    }

    /**
     * Fallback categorization based on resolution/format for movie group releases.
     */
    protected function categorizeByResolution(string $name): CategorizationResult
    {
        // Check for UHD/4K
        if (preg_match('/\b(2160p|4k|UHD)\b/i', $name)) {
            return $this->matched(Category::MOVIE_UHD, 0.65, 'fallback_uhd');
        }

        // Check for HD
        if (preg_match('/\b(1080p|720p)\b/i', $name)) {
            return $this->matched(Category::MOVIE_HD, 0.65, 'fallback_hd');
        }

        // Check for BluRay
        if (preg_match('/\b(BluRay|Blu-Ray|BD)\b/i', $name)) {
            return $this->matched(Category::MOVIE_BLURAY, 0.65, 'fallback_bluray');
        }

        // Check for DVD
        if (preg_match('/\b(DVD|DVDRip|DVDR)\b/i', $name)) {
            return $this->matched(Category::MOVIE_DVD, 0.65, 'fallback_dvd');
        }

        // Check for SD formats
        if (preg_match('/\b(480p|576p|XviD|DivX)\b/i', $name)) {
            return $this->matched(Category::MOVIE_SD, 0.65, 'fallback_sd');
        }

        // Default to MOVIE_OTHER
        return $this->matched(Category::MOVIE_OTHER, 0.5, 'fallback_movie');
    }

    /**
     * Check if release name looks like movie content.
     */
    protected function looksLikeMovie(string $name): bool
    {
        // Comprehensive patterns from Categorize.php
        if (preg_match('/[._ -]AVC|[BH][DR]RIP|(Tigole)|(Bluray|Blu-Ray)|BD[._ -]?(25|50)?|\bBR\b|Camrip|[._( -]\d{4}[._) -].+(720p|1080p|Cam|HDTS|2160p|10bit)|DIVX|[._ -]DVD[._ -]|DVD-?(5|9|R|Rip)|Untouched|VHSRip|XVID|[._ -](DTS|TVrip|webrip|WEB|WEBDL|WEB-DL)[._ -]|RetailSub$|\b(2160)p\b.*\b(Netflix|Amazon|NF|AMZN|Disney)\b/i', $name)) {
            return true;
        }

        // HALLMARK releases are typically movies
        if (preg_match('/\bHALLMARK\b/i', $name)) {
            return true;
        }

        return false;
    }

    protected function checkForeign(string $name): ?CategorizationResult
    {
        // Comprehensive foreign patterns from Categorize.php
        if (preg_match('/(NLsubs|danish|flemish|Deutsch|dutch|German|heb|hebrew|nl[._ -]?sub|dub(bed|s)?|\.NL|norwegian|swedish|swesub|spanish|catalan|Staffel)[._ -]|\(german\)|Multisub/i', $name)) {
            return $this->matched(Category::MOVIE_FOREIGN, 0.85, 'foreign_lang');
        }
        if (preg_match('/[._ -](French|TRUEFRENCH|VGQ|VFQ|VFF|VFI|VFB|VF2|VOSTFR|FRENCHSUB|SUBFRENCH)/i', $name)) {
            return $this->matched(Category::MOVIE_FOREIGN, 0.85, 'foreign_french');
        }
        if (stripos($name, 'Castellano') !== false) {
            return $this->matched(Category::MOVIE_FOREIGN, 0.8, 'foreign_castellano');
        }

        if (preg_match('/(720p|1080p|AC3|AVC|DIVX|DVD(5|9|RIP|R)|XVID)[._ -](Dutch|French|German|ITA)|\(?(Dutch|French|German|ITA)\)?[._ -](720P|1080p|AC3|AVC|DIVX|DVD(5|9|RIP|R)|WEB(-DL|-?RIP)|HD[._ -]|XVID)/i', $name)) {
            return $this->matched(Category::MOVIE_FOREIGN, 0.85, 'foreign_pattern');
        }

        return null;
    }

    protected function checkX265(string $name): ?CategorizationResult
    {
        if (preg_match('/(\w+[\.-_\s]+).*(x265).*(Tigole|SESKAPiLE|CHD|IAMABLE|THREESOME|OohLaLa|DEFLATE|NCmt)/i', $name)) {
            return $this->matched(Category::MOVIE_X265, 0.9, 'x265_group');
        }

        return null;
    }

    protected function checkUHD(string $name): ?CategorizationResult
    {
        // Skip TV shows
        if (preg_match('/(S\d+).*(2160p).*(Netflix|Amazon|NF|AMZN).*(TrollUHD|NTb|VLAD|DEFLATE|CMRG)/i', $name)) {
            return null;
        }

        // Check for UHD indicators
        if (stripos($name, '2160p') !== false ||
            preg_match('/\b(UHD|Ultra[._ -]HD|4K)\b/i', $name) ||
            (preg_match('/\b(HDR|HDR10|HDR10\+|Dolby[._ -]?Vision)\b/i', $name) &&
             preg_match('/\b(HEVC|H\.?265|x265)\b/i', $name)) ||
            (stripos($name, 'UHD') !== false &&
             preg_match('/\b(BR|BluRay|Blu[._ -]?Ray)\b/i', $name))) {
            return $this->matched(Category::MOVIE_UHD, 0.9, 'uhd');
        }

        return null;
    }

    protected function check3D(string $name): ?CategorizationResult
    {
        if (preg_match('/[._ -]3D\s?[\.\-_\[ ](1080p|(19|20)\d\d|AVC|BD(25|50)|Blu[._ -]?ray|CEE|Complete|GER|MVC|MULTi|SBS|H(-)?SBS)[._ -]/i', $name)) {
            return $this->matched(Category::MOVIE_3D, 0.9, '3d');
        }

        return null;
    }

    protected function checkBluRay(string $name): ?CategorizationResult
    {
        if (preg_match('/bluray-|[._ -]bd?[._ -]?(25|50)|blu-ray|Bluray\s-\sUntouched|[._ -]untouched[._ -]/i', $name) &&
            ! preg_match('/SecretUsenet\.com$/i', $name)) {
            return $this->matched(Category::MOVIE_BLURAY, 0.9, 'bluray');
        }

        return null;
    }

    protected function checkDVD(string $name): ?CategorizationResult
    {
        // Exclude comic formats from Categorize.php
        if (preg_match('/c2c|cbr|cbz/i', $name)) {
            return null;
        }

        if (preg_match('/(dvd\-?r|[._ -]dvd|dvd9|dvd5|[._ -]r5)[._ -]/i', $name)) {
            return $this->matched(Category::MOVIE_DVD, 0.85, 'dvd');
        }

        return null;
    }

    protected function checkWebDL(string $name): ?CategorizationResult
    {
        if (preg_match('/web[._ -]dl|web-?rip/i', $name)) {
            return $this->matched(Category::MOVIE_WEBDL, 0.85, 'webdl');
        }

        return null;
    }

    protected function checkHD(string $name, bool $catWebDL): ?CategorizationResult
    {
        if (preg_match('/720p|1080p|AVC|VC1|VC-1|web-dl|wmvhd|x264|XvidHD|bdrip/i', $name)) {
            return $this->matched(Category::MOVIE_HD, 0.85, 'hd');
        }

        if (! $catWebDL && preg_match('/web[._ -]dl|web-?rip/i', $name)) {
            return $this->matched(Category::MOVIE_HD, 0.8, 'hd_webdl_fallback');
        }

        return null;
    }

    protected function checkSD(string $name): ?CategorizationResult
    {
        if (preg_match('/(divx|dvdscr|extrascene|dvdrip|\.CAM|HDTS(-LINE)?|vhsrip|xvid(vd)?)[._ -]/i', $name)) {
            return $this->matched(Category::MOVIE_SD, 0.8, 'sd');
        }

        return null;
    }

    protected function checkOther(string $name): ?CategorizationResult
    {
        if (preg_match('/[._ -]cam[._ -]/i', $name)) {
            return $this->matched(Category::MOVIE_OTHER, 0.6, 'cam');
        }

        return null;
    }
}
