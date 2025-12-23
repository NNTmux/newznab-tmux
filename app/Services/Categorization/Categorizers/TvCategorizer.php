<?php

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

/**
 * Categorizer for TV content including HD, SD, UHD, Anime, Sports, Documentaries, etc.
 */
class TvCategorizer extends AbstractCategorizer
{
    protected int $priority = 20;

    public function getName(): string
    {
        return 'TV';
    }

    public function shouldSkip(ReleaseContext $context): bool
    {
        return $context->hasAdultMarkers();
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;

        if (!$this->looksLikeTV($name)) {
            return $this->noMatch();
        }

        if ($result = $this->checkAnime($name)) {
            return $result;
        }
        if ($result = $this->checkSport($name)) {
            return $result;
        }
        if ($result = $this->checkDocumentary($name)) {
            return $result;
        }
        if ($result = $this->checkForeign($context)) {
            return $result;
        }
        if ($result = $this->checkX265($name)) {
            return $result;
        }
        if ($context->catWebDL && ($result = $this->checkWebDL($name))) {
            return $result;
        }
        if ($result = $this->checkUHD($name)) {
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

        return $this->noMatch();
    }

    protected function looksLikeTV(string $name): bool
    {
        // Season + Episode pattern: S01E01, S01.E01, S1D1, etc.
        if (preg_match('/[._ -]s\d{1,3}[._ -]?(e|d(isc)?)\d{1,3}([._ -]|$)/i', $name)) {
            return true;
        }
        // Episode-only pattern: .E01., .E02., E01.1080p (common in anime)
        if (preg_match('/[._ -]E\d{1,4}[._ -]/i', $name)) {
            return true;
        }
        // Season pack with Complete/Full: S01.Complete, S01.Full
        if (preg_match('/[._ -]S\d{1,3}[._ -]?(Complete|COMPLETE|Full|FULL)/i', $name)) {
            return true;
        }
        // Season pack with resolution/quality: S01.1080p, S01.720p, S01.2160p, S02.WEB-DL
        if (preg_match('/[._ -]S\d{1,3}[._ -](480p|720p|1080[pi]|2160p|4K|UHD|WEB|HDTV|BluRay|NF|AMZN|DSNP|ATVP|HMAX)/i', $name)) {
            return true;
        }
        // Episode pattern: Episode 01, Ep.01, Ep 1
        if (preg_match('/\b(Episode|Ep)[._ -]?\d{1,4}\b/i', $name)) {
            return true;
        }
        // TV source markers
        if (preg_match('/\b(HDTV|PDTV|DSR|TVRip|SATRip|DTHRip)\b/i', $name)) {
            return true;
        }
        // Daily show pattern: Show.Name.2024.01.15 or Show.Name.2024-01-15
        if (preg_match('/[._ -](19|20)\d{2}[._ -]\d{2}[._ -]\d{2}[._ -]/i', $name)) {
            return true;
        }
        // Known anime release groups (should be treated as TV)
        if (preg_match('/(?:^|[.\-_ \[])(URANiME|ANiHLS|HaiKU|ANiURL|SkyAnime|Erai-raws|LostYears|Vodes|SubsPlease|Judas|Ember|EMBER|YuiSubs|ASW|Tsundere-Raws|Anime-Raws)(?:[.\-_ \]]|$)/i', $name)) {
            return true;
        }
        return false;
    }

    protected function checkAnime(string $name): ?CategorizationResult
    {
        if (preg_match('/[._ -]Anime[._ -]/i', $name)) {
            return $this->matched(Category::TV_ANIME, 0.95, 'anime_pattern');
        }
        // Known anime release groups - matches with brackets, dots, dashes, underscores, spaces, or at the start
        if (preg_match('/(?:^|[.\-_ \[])(URANiME|ANiHLS|HaiKU|ANiURL|SkyAnime|Erai-raws|LostYears|Vodes|SubsPlease|Judas|Ember|EMBER|YuiSubs|ASW|Tsundere-Raws|Anime-Raws)(?:[.\-_ \]]|$)/i', $name)) {
            return $this->matched(Category::TV_ANIME, 0.95, 'anime_group');
        }
        // Anime hash pattern: [GroupName] Title - 01 [ABCD1234]
        if (preg_match('/^\[.+\].*\d{2,3}.*\[[a-fA-F0-9]{8}\]/i', $name)) {
            return $this->matched(Category::TV_ANIME, 0.9, 'anime_hash');
        }
        // Japanese title pattern with "no" particle (e.g., Shuumatsu.no.Valkyrie, Shingeki.no.Kyojin)
        // Combined with episode-only pattern (E05 without season prefix) or roman numeral season
        if (preg_match('/[._ ]no[._ ]/i', $name) &&
            (preg_match('/[._ ](I{1,3}|IV|V|VI{0,3}|IX|X)[._ ]?E\d{1,4}[._ ]/i', $name) ||
             (preg_match('/[._ ]E\d{1,4}[._ ]/i', $name) && !preg_match('/[._ ]S\d{1,3}[._ ]?E\d/i', $name)))) {
            return $this->matched(Category::TV_ANIME, 0.9, 'anime_japanese_title');
        }
        // Episode pattern with known anime indicators
        if (preg_match('/[._ -]E\d{1,4}[._ -]/i', $name) &&
            preg_match('/\b(BluRay|BD|BDRip)\b/i', $name) &&
            !preg_match('/\bS\d{1,3}\b/i', $name)) {
            // Episode-only pattern with BluRay but no season - likely anime
            return $this->matched(Category::TV_ANIME, 0.8, 'anime_episode_bluray');
        }
        // Roman numeral season with episode-only pattern (common in anime)
        // e.g., Title.III.E05, Title.II.E12 - typically anime naming convention
        if (preg_match('/[._ ](I{1,3}|IV|V|VI{0,3}|IX|X)[._ ]E\d{1,4}[._ ]/i', $name) &&
            !preg_match('/[._ ]S\d{1,3}[._ ]?E\d/i', $name)) {
            return $this->matched(Category::TV_ANIME, 0.85, 'anime_roman_numeral_season');
        }
        return null;
    }

    protected function checkSport(string $name): ?CategorizationResult
    {
        if (preg_match('/\b(NFL|NBA|NHL|MLB|MLS|UFC|WWE|Boxing|F1|Formula[._ -]?1|NASCAR|PGA|Tennis|Golf|Soccer|Football|Cricket|Rugby)\b/i', $name) &&
            preg_match('/\d{4}|\b(Season|Week|Round|Match|Game)\b/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.85, 'sport');
        }
        return null;
    }

    protected function checkDocumentary(string $name): ?CategorizationResult
    {
        if (preg_match('/\b(Documentary|Docu[._ -]?Series|DOCU)\b/i', $name)) {
            return $this->matched(Category::TV_DOCU, 0.85, 'documentary');
        }
        return null;
    }

    protected function checkForeign(ReleaseContext $context): ?CategorizationResult
    {
        if (!$context->categorizeForeign) {
            return null;
        }
        if (preg_match('/(danish|flemish|Deutsch|dutch|french|german|hebrew|nl[._ -]?sub|dub(bed|s)?|\.NL|norwegian|swedish|swesub|spanish|Staffel)[._ -]|\(german\)|Multisub/i', $context->releaseName)) {
            return $this->matched(Category::TV_FOREIGN, 0.8, 'foreign_language');
        }
        return null;
    }

    protected function checkX265(string $name): ?CategorizationResult
    {
        if (preg_match('/(S\d+).*(x265).*(rmteam|MeGusta|HETeam|PSA|ONLY|H4S5S|TrollHD|ImE)/i', $name)) {
            return $this->matched(Category::TV_X265, 0.9, 'x265_group');
        }
        return null;
    }

    protected function checkWebDL(string $name): ?CategorizationResult
    {
        if (preg_match('/web[._ -]dl|web-?rip/i', $name)) {
            return $this->matched(Category::TV_WEBDL, 0.85, 'webdl');
        }
        return null;
    }

    protected function checkUHD(string $name): ?CategorizationResult
    {
        // Single episode UHD
        if (preg_match('/S\d+[._ -]?E\d+/i', $name) && preg_match('/2160p/i', $name)) {
            return $this->matched(Category::TV_UHD, 0.9, 'uhd_episode');
        }
        // Season pack UHD
        if (preg_match('/[._ -]S\d+[._ -].*2160p/i', $name)) {
            return $this->matched(Category::TV_UHD, 0.9, 'uhd_season');
        }
        // UHD with streaming service markers
        if (preg_match('/(S\d+).*(2160p).*(Netflix|Amazon|NF|AMZN).*(TrollUHD|NTb|VLAD|DEFLATE|POFUDUK|CMRG)/i', $name)) {
            return $this->matched(Category::TV_UHD, 0.9, 'uhd_streaming');
        }
        return null;
    }

    protected function checkHD(string $name, bool $catWebDL): ?CategorizationResult
    {
        if (preg_match('/1080([ip])|720p|bluray/i', $name)) {
            return $this->matched(Category::TV_HD, 0.85, 'hd_resolution');
        }
        if (!$catWebDL && preg_match('/web[._ -]dl|web-?rip/i', $name)) {
            return $this->matched(Category::TV_HD, 0.8, 'hd_webdl_fallback');
        }
        return null;
    }

    protected function checkSD(string $name): ?CategorizationResult
    {
        if (preg_match('/(360|480|576)p|Complete[._ -]Season|dvdr(ip)?|dvd5|dvd9|\.pdtv|SD[._ -]TV|TVRip|NTSC|BDRip|hdtv|xvid/i', $name)) {
            return $this->matched(Category::TV_SD, 0.8, 'sd_format');
        }
        if (preg_match('/(([HP])D[._ -]?TV|DSR|WebRip)[._ -]x264/i', $name)) {
            return $this->matched(Category::TV_SD, 0.8, 'sd_codec');
        }
        return null;
    }

    protected function checkOther(string $name): ?CategorizationResult
    {
        // Season + episode pattern
        if (preg_match('/[._ -]s\d{1,3}[._ -]?(e|d(isc)?)\d{1,3}([._ -]|$)/i', $name)) {
            return $this->matched(Category::TV_OTHER, 0.6, 'tv_other');
        }
        // Season pack pattern (S01, S02, etc.) with any quality marker
        if (preg_match('/[._ -]S\d{1,3}[._ -]/i', $name)) {
            return $this->matched(Category::TV_OTHER, 0.6, 'tv_season_pack');
        }
        return null;
    }
}
