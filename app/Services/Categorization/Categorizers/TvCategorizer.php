<?php

declare(strict_types=1);

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

/**
 * Categorizer for TV content including HD, SD, UHD, Anime, Sports, Documentaries, etc.
 */
class TvCategorizer extends AbstractCategorizer
{
    private const string SPORTS_MARKER_REGEX = '/\b(NFL|NBA|WNBA|NHL|MLB|MLS|EPL|UFC|WWE|AEW|Boxing|BKFC|Bellator|MMA|F1|Formula[._ -]?(?:[1-3]|E)|NASCAR|IndyCar|MotoGP|Porsc?he[._ -]?Supercup|PGA|Tennis|WTA|ATP|Golf|Soccer|Football|FIFA|UEFA|UCL|UEL|UECL|Concacaf|CONMEBOL|La[._ -]?Liga|Bundesliga|Serie[._ -]A|Ligue[._ -]1|Eredivisie|MOTD|NCAA|NRL|AFL|Cricket|Rugby|Olympics?|Olympic[._ -]?Games?|Paralympics?)\b/i';

    private const string SPORTS_EVENT_CONTEXT_REGEX = '/\d{4}|\b(Season|Week|Round|Match|Game|vs|Playoffs?|Finals?|Qualifying|Opening|Closing|Ceremony|Championship)\b/i';

    /**
     * Event names that are also film or book titles (`Wimbledon`, `World Cup`); they only count
     * as sport with fixture context and without a disc-rip source.
     */
    private const string SPORTS_AMBIGUOUS_EVENT_REGEX = '/\b(World[._ -]Cup|Champions[._ -]League|Europa[._ -]League|Conference[._ -]League|Copa[._ -](?:America|Libertadores|Sudamericana|del[._ -]Rey)|Wimbledon|Roland[._ -]Garros|Match[._ -]of[._ -]the[._ -]Day|Tour[._ -]de[._ -]France)\b/i';

    private const string SPORTS_FIXTURE_CONTEXT_REGEX = '/\b(?:vs?|Final|Semi-?finals?|QF|SF|R(?:16|32)|R\d|Round|Group|Leg|Matchday|MD\d|Highlights|Qualifying|Race)\b|(?:19|20)\d{2}[._ -]\d{2}[._ -]\d{2}|\b\d{2}[._ -]\d{2}[._ -](?:19|20)\d{2}\b/i';

    private const string DISC_SOURCE_REGEX = '/\b(?:Blu-?Ray|BDRip|BRRip|REMUX|DVDRip|DVD[59])\b/i';

    /**
     * Known anime release/fansub group tags. Matches when the tag appears
     * as a standalone token in the release name (bracketed, dotted, etc.).
     */
    private const string ANIME_GROUPS_REGEX = '/(?:^|[.\-_ \[])(URANiME|ANiHLS|HaiKU|ANiURL|SkyAnime|(?:Erai|New)-raws|LostYears|Vodes|SubsPlease|Judas|Ember|EMBER|YuiSubs|ASW|Tsundere-Raws|Anime-Raws|2jzgte|shiteater|Kekkan|SSA|MoyaiSubs|QCE|Nyanpasu|UCCUSS|NS|DKB|MTBB|NanDesuKa|smol|Cleo|ToshY|Kawaiika-Raws|Beatrice-Raws|Reaktor|FLE|Commie|HorribleSubs|Doki|FFF|UTW|Coalgirls|CoalGuys|Mezashite|Vivid|Kaylith|Underwater|gg|Chihiro|Hatsuyuki|Mazui|WhyNot|Migoto|Doutei|GJM|Asenshi|Inka-Subs|Akatsuki)(?:[.\-_ \]]|$)/i';

    protected int $priority = 20;

    // Anime groups from Categorize.php
    protected const ANIME_GROUPS = [
        'alt.binaries.anime', 'alt.binaries.multimedia.anime.highspeed', 'alt.binaries.multimedia.anime.repost',
        'alt.binaries.multimedia.anime', 'alt.binaries.multimedia.erotica.anime', 'alt.binaries.anime.highspeed',
        'alt.binaries.anime.repost',
    ];

    // TV usenet groups
    protected const TV_GROUPS = [
        'alt.binaries.tv',
        'alt.binaries.hdtv',
        'alt.binaries.hdtv.x264',
        'alt.binaries.tvseries',
        'alt.binaries.multimedia.tv',
    ];

    public function getName(): string
    {
        return 'TV';
    }

    public function shouldSkip(ReleaseContext $context): bool
    {
        if ($context->hasAdultMarkers()) {
            return true;
        }
        // Reject hash-like release names that may accidentally match TV patterns
        if ($this->looksLikeHash($context->releaseName)) {
            return true;
        }
        // An archive holding a PDF or ebook is never a broadcast, whatever the uploader tag says.
        if ($context->hasDocumentContent()) {
            return true;
        }
        // Ignore generic multi-part archive segment names lacking TV markers
        if (preg_match('/\.7z\.\d{3}\b/i', $context->releaseName) &&
            ! preg_match('/S\d{1,4}[._ -]?(E|D)\d{1,4}|Season[._ -]?\d{1,3}|Complete[._ -]Season|E\d{1,4}|(19|20)\d{2}|720p|1080p|2160p|HDTV|BluRay|WEB[._ -]?(DL|RIP)/i', $context->releaseName)) {
            return true;
        }

        return false;
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;
        $groupName = $context->groupName;

        // Check if from anime or TV group
        $isFromAnimeGroup = $this->isAnimeGroup($groupName);
        $isFromTVGroup = $this->isTVGroup($groupName);

        // High-signal anime checks first. These can match release names that
        // don't follow standard TV S01E01 naming conventions (e.g. anime
        // fansub releases like "[Group] Show - 08 [1080p]").
        if ($result = $this->checkAnimeStrongSignals($name, $context)) {
            return $result;
        }

        if ($result = $this->checkSportSourceTag($name, $context)) {
            return $result;
        }

        if (! $this->looksLikeTV($context) && ! $isFromAnimeGroup && ! $isFromTVGroup) {
            return $this->noMatch();
        }

        // Check order matters for specificity
        if ($result = $this->checkOtherTV($name)) {
            return $result;
        }
        if ($context->categorizeForeign && ($result = $this->checkForeign($context))) {
            return $result;
        }

        if ($result = $this->checkAnime($name, $context)) {
            return $result;
        }
        if (! $context->hasBookSignals() && ($result = $this->checkSport($name))) {
            return $result;
        }
        if ($result = $this->checkDocumentary($name)) {
            return $result;
        }
        if ($result = $this->checkX265($name)) {
            return $result;
        }
        if ($result = $this->checkUHD($name)) {
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
        if ($result = $this->checkOther($context)) {
            return $result;
        }

        // Fallback based on group type
        if ($isFromAnimeGroup) {
            return $this->matched(Category::TV_ANIME, 0.7, 'fallback_anime_group');
        }

        // Default to TV_OTHER if it matched looksLikeTV or is from TV group
        return $this->matched(Category::TV_OTHER, 0.5, 'tv_default');
    }

    /**
     * Check if the group is an anime group.
     */
    protected function isAnimeGroup(string $groupName): bool
    {
        if (empty($groupName)) {
            return false;
        }

        return in_array($groupName, self::ANIME_GROUPS) ||
               (bool) preg_match('/anime/i', $groupName);
    }

    /**
     * Check if the group is a TV group.
     */
    protected function isTVGroup(string $groupName): bool
    {
        if (empty($groupName)) {
            return false;
        }

        return in_array($groupName, self::TV_GROUPS) ||
               (bool) preg_match('/alt\.binaries\.(hdtv|tv|tvseries)/i', $groupName);
    }

    /**
     * Check if the release name appears to be a hash/random string that should not be categorized as TV.
     */
    protected function looksLikeHash(string $name): bool
    {
        // Remove common usenet prefixes like "[01/16] - " and file extensions
        $cleanName = preg_replace('/^\[\d+\/\d+\]\s*-\s*"?|"?\s*yEnc.*$|\.(?:7z|rar|zip|par2?|nfo|sfv|nzb)(?:\.\d+)?$/i', '', $name);
        $cleanName = trim($cleanName, '"');

        // If the name is just one long alphanumeric string without proper separators, it's likely a hash
        if (preg_match('/^[a-zA-Z0-9]{16,}$/', $cleanName)) {
            // Exception: if it contains known TV markers with proper word boundaries, allow it
            if (preg_match('/\b(S\d{1,4}[._ -]?E\d{1,4}|Season|Episode|Complete|HDTV|BluRay|WEB[._ -]?DL)\b/i', $cleanName)) {
                return false;
            }

            return true;
        }

        // Check for release names that are mostly random characters without meaningful separators
        $separatorCount = preg_match_all('/[._ -]/', $cleanName);
        $alphanumCount = preg_match_all('/[a-zA-Z0-9]/', $cleanName);

        // If less than 10% of the name is separators AND there are no clear TV patterns, it's suspicious
        if ($alphanumCount > 15 && ($separatorCount / max($alphanumCount, 1)) < 0.05) {
            if (! preg_match('/S\d{1,4}[._ -]?(E|D)\d{1,4}|Season[._ -]?\d{1,3}|HDTV|BluRay|WEB[._ -]?(DL|RIP)|720p|1080p|2160p/i', $cleanName)) {
                return true;
            }
        }

        return false;
    }

    protected function looksLikeTV(ReleaseContext $context): bool
    {
        $name = $context->releaseName;

        // Standalone season token: S01, S02, etc. Quality markers may appear later.
        if ($context->hasStandaloneSeasonToken()) {
            return true;
        }

        // Main TV pattern check from Categorize.php - require proper word boundaries for \d+x\d+ pattern
        if (preg_match('/Daily[\-_\.]Show|Nightly News|^\[[a-zA-Z\.\-]+\].*[\-_].*\d{1,3}[\-_. ](([\[\(])(h264-)?\d{3,4}([pi])([\]\)])\s?(\[AAC\])?|\[[a-fA-F0-9]{8}\]|(8|10)BIT|hi10p)(\[[a-fA-F0-9]{8}\])?|(\d\d-){2}[12]\d{3}|[12]\d{3}(\.\d\d){2}|[._ -]\d+x\d+[._ -]|\.e\d{1,3}\.|s\d{1,4}[._ -]?[ed]\d{1,3}([ex]\d{1,3}|[\-.\w ])|[._ -](\dx\d\d|C4TV|Complete[._ -]Season|DSR|([DHPS])DTV|EP[._ -]?\d{1,3}|S\d{1,3}.+Extras|SUBPACK|Season[._ -]\d{1,2})([._ -]|$)|TVRIP|TV[._ -](19|20)\d\d|Troll(HD|UHD)/i', $name)
            && ! preg_match('/^(Defloration|MetArt|MetArtX|SexArt|TheLifeErotic|VivThomas|CzechVR|VRBangers|WankzVR|BadoinkVR|NaughtyAmerica)(.|\s)?\d{4}[._ -]\d{2}[._ -]\d{2}|[._ -](flac|imageset|mp3|xxx|XXX|porn|adult|sex)[._ -]|[ .]exe$|[._ -](shemale|transsexual|bisexual|siterip|JAV|JavHD)\b/i', $name)) {
            return true;
        }

        // Sports TV pattern
        if (preg_match('/[._ -]((19|20)\d\d[._ -]\d{1,2}[._ -]\d{1,2}[._ -]VHSRip|Indy[._ -]?Car|(iMPACT|Smoky[._ -]Mountain|Texas)[._ -]Wrestling|Moto[._ -]?GP|NSCS[._ -]ROUND|NECW[._ -]TV|(Per|Post)\-Show|PPV|WrestleMania|WCW|WEB[._ -]HD|WWE[._ -](Monday|NXT|RAW|Smackdown|Superstars|WrestleMania|^UEL))[._ -]/i', $name)) {
            return true;
        }

        // Season + Episode pattern: S01E01, S01.E01, S1D1, etc.
        // Also match when release name starts with S##E##
        if (preg_match('/^s\d{1,3}[._ -]?e\d{1,3}|[._ -]s\d{1,3}[._ -]?(e|d(isc)?)\d{1,3}([._ -]|$)/i', $name)) {
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
        // Sports events are TV-like even without SxxExx markers.
        if (! $context->hasBookSignals() && $this->isSportEvent($name)) {
            return true;
        }
        // Known anime release groups (should be treated as TV)
        if (preg_match(self::ANIME_GROUPS_REGEX, $name)) {
            return true;
        }

        return false;
    }

    /**
     * Strong, gate-bypassing anime signals: Anime Tosho poster, anime usenet
     * groups, and anime hash patterns. These can match even when the release
     * name doesn't look like a typical TV release.
     */
    protected function checkAnimeStrongSignals(string $name, ReleaseContext $context): ?CategorizationResult
    {
        // Anime Tosho poster (very strong signal)
        if ($context->poster !== '' && preg_match('/animetosho\.org/i', $context->poster)) {
            return $this->matched(Category::TV_ANIME, 0.98, 'anime_tosho_poster');
        }
        // Usenet group dedicated to anime (e.g. alt.binaries.*anime*)
        if ($context->groupName !== '' && preg_match('/alt\.binaries\..*anime/i', $context->groupName)) {
            return $this->matched(Category::TV_ANIME, 0.9, 'anime_group_name');
        }
        // Known anime fansub/release group tag
        if (preg_match(self::ANIME_GROUPS_REGEX, $name)) {
            return $this->matched(Category::TV_ANIME, 0.92, 'anime_group');
        }
        // Anime hash pattern: [GroupName] Title - 01 [ABCD1234]
        if (preg_match('/^\[.+\].*\d{2,3}.*\[[a-fA-F0-9]{8}\]/i', $name)) {
            return $this->matched(Category::TV_ANIME, 0.9, 'anime_hash');
        }

        return null;
    }

    /**
     * Check for TV Other (extras, subpacks, news)
     */
    protected function checkOtherTV(string $name): ?CategorizationResult
    {
        if (preg_match('/[._ -]S\d{1,3}.+(EP\d{1,3}|Extras|SUBPACK)[._ -]|News/i', $name)) {
            return $this->matched(Category::TV_OTHER, 0.8, 'tv_other_extras');
        }

        return null;
    }

    protected function checkAnime(string $name, ReleaseContext $context): ?CategorizationResult
    {
        // Check if from known anime groups
        if ($context->groupName && in_array($context->groupName, self::ANIME_GROUPS)) {
            return $this->matched(Category::TV_ANIME, 0.95, 'anime_group_usenet');
        }

        // Check for Anime Tosho poster
        if ($context->poster !== '' && preg_match('/animetosho\.org/i', $context->poster)) {
            return $this->matched(Category::TV_ANIME, 0.98, 'anime_tosho_poster');
        }
        if (preg_match('/[._ -]Anime[._ -]/i', $name)) {
            return $this->matched(Category::TV_ANIME, 0.95, 'anime_pattern');
        }
        // Anime pattern from Categorize.php
        if (preg_match('/^\[[a-zA-Z\.\-]+\].*[\-_].*\d{1,3}[\-_. ](([\[\(])((\d{1,4}x\d{1,4})|(h264-)?\d{3,4}([pi]))([\]\)])\s?(\[AAC\])?|\[[a-fA-F0-9]{8}\]|(8|10)BIT|hi10p)(\[[a-fA-F0-9]{8}\])?/i', $name)) {
            return $this->matched(Category::TV_ANIME, 0.95, 'anime_format');
        }

        // Known anime release groups - matches with brackets, dots, dashes, underscores, spaces, or at the start
        if (preg_match(self::ANIME_GROUPS_REGEX, $name)) {
            return $this->matched(Category::TV_ANIME, 0.95, 'anime_group');
        }

        // Specific anime titles from Categorize.php
        if (preg_match('/Sokudo|Ninja Kamui|Synduality Noir|Komyo|Yoohoo/i', $name)) {
            return $this->matched(Category::TV_ANIME, 0.9, 'anime_title');
        }

        // Anime hash pattern: [GroupName] Title - 01 [ABCD1234]
        if (preg_match('/^\[.+\].*\d{2,3}.*\[[a-fA-F0-9]{8}\]/i', $name)) {
            return $this->matched(Category::TV_ANIME, 0.9, 'anime_hash');
        }

        // Japanese title pattern with "no" particle (e.g., Shuumatsu.no.Valkyrie, Shingeki.no.Kyojin)
        // Combined with episode-only pattern (E05 without season prefix) or roman numeral season
        if (preg_match('/[._ ]no[._ ]/i', $name) &&
            (preg_match('/[._ ](I{1,3}|IV|V|VI{0,3}|IX|X)[._ ]?E\d{1,4}[._ ]/i', $name) ||
             (preg_match('/[._ ]E\d{1,4}[._ ]/i', $name) && ! preg_match('/[._ ]S\d{1,3}[._ ]?E\d/i', $name)))) {
            return $this->matched(Category::TV_ANIME, 0.9, 'anime_japanese_title');
        }
        // Episode pattern with known anime indicators
        if (preg_match('/[._ -]E\d{1,4}[._ -]/i', $name) &&
            preg_match('/\b(BluRay|BD|BDRip)\b/i', $name) &&
            ! preg_match('/\bS\d{1,3}\b/i', $name)) {
            // Episode-only pattern with BluRay but no season - likely anime
            return $this->matched(Category::TV_ANIME, 0.8, 'anime_episode_bluray');
        }
        // Roman numeral season with episode-only pattern (common in anime)
        // e.g., Title.III.E05, Title.II.E12 - typically anime naming convention
        if (preg_match('/[._ ](I{1,3}|IV|V|VI{0,3}|IX|X)[._ ]E\d{1,4}[._ ]/i', $name) &&
            ! preg_match('/[._ ]S\d{1,3}[._ ]?E\d/i', $name)) {
            return $this->matched(Category::TV_ANIME, 0.85, 'anime_roman_numeral_season');
        }

        return null;
    }

    protected function checkSport(string $name): ?CategorizationResult
    {
        // Exclusions for false positives
        if (preg_match('/Drive[. ]to[. ]Survive|RuPaul|Creampie|Picset|abook|90\.Day|Fiance/i', $name)) {
            return null;
        }

        // Comprehensive sports patterns from Categorize.php
        if (preg_match('/[._ -]?(Bellator|bundesliga|EPL|ESPN|FIA|la[._ -]liga|\bMMA\b|motogp|moto2|moto3|^NFL|MLB|NCAA|PGA|FIM|NJPW|red[._ -]bull|Sengoku|Strikeforce|supercup|uefa|UFC|wtcc|WWE|WWN)[._ -]?|UWA\.Elite\.Wrestling|Cage[._\s]Warriors/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.9, 'sport_league');
        }
        if (preg_match('/[._ -]?(DTM|FIFA|Formula[._ -](\d{1}|E)|Formula\d{1}|indycar|Rugby|NASCAR|NBA|NHL|NRL|netball[._ -]anz|ROH|SBK|Superleague|The[._ -]Ultimate[._ -]Fighter|TNA|V8[._ -]Supercars|WBA|WrestleMania)[._ -]|Road Racing|Supercross/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.9, 'sport_event');
        }
        if (preg_match('/[._ -]?(AFL|Grand Prix|ePrix|Indy[._ -]Car|(iMPACT|Smoky[._ -]Mountain|Texas)[._ -]Wrestling|Moto[._ -]?GP|NSCS[._ -]ROUND|NECW|PWX|Rugby|WCW)[._ -]/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.9, 'sport_racing');
        }
        if (preg_match('/[._ -]?(Horse)[._ -]Racing[._ -]/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.9, 'sport_horse_racing');
        }
        if (preg_match('/^AEW\.|^Match\.of\.the\.day\./i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.9, 'sport_match');
        }
        if (preg_match('/Formula[._ -]\d{1}|Formula\d{1}|F1[._ ]202\d|^WTA|Formel.[0-9]{1}|AFL.[0-9]{4}|Impact.Wrestling|PWX\.Wrestling|^WWE|^WWF|^EFL|^NFL|^NBA|^UEFA|^UFC|^MLB|^ATP|^UCL|Netball|^FIFA|^FIBA|WiNNiNG$|^Major.League.Wrestling|^NCAA|^PGA|^FIM|^NJPW|^MLS|^Major.League.Soccer|^UEL|^CSL/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.9, 'sport_prefix');
        }
        // Sports release groups
        if (preg_match('/[._ -](VERUM|GRiP$|Ebi|OVERTAKE$|LEViTATE|WiNNiNG|ADMIT|PYRAMiD|WoGSBAR|BaNHaMMER|HANDBOLL|DARKSPORT|TWOLEFTFEET|GAMETiME$|SPORTSNET)\b|SPORTSNET-/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.85, 'sport_group');
        }
        // Major sporting events
        if (preg_match('/US Open|U\.S\.Open\.Cup|US\.Open|Australian\.Open|Australian Open|Womens\.International\.Champions\.Cup|CJJW|U\.S\.Poker|US\.Poker|World[ .-]Series[ .-]Of|Masters\.Snooker|Alpine[ .-]Skiing/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.9, 'sport_major_event');
        }
        // Football (Soccer)
        if (preg_match('/Fussbal|Spieltag|Fussball|Bundesliga|^Liga\.(1|2)|^Serie\.A|^Ligue\.1|FA[. _-]Cup/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.9, 'sport_football');
        }
        // Sports Broadcasters
        if (preg_match('/Sporza|Studio[\s.-]Sport|Eurosport|Sportsdok|Ziggo|RGSport|BT[\s.]Sport|Essais[.\s]Libres|Ronde van Frankrijk/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.85, 'sport_broadcaster');
        }
        // Foreign GP
        if (preg_match('/GP[ .-](Italie|France|Frankrijk|Frankreich|Russie|Angleterre|Allemagne|USA|Bresil|Japon)|Les Specialistes F1/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.9, 'sport_gp');
        }
        // More Racing
        if (preg_match('/MXGP|Superbikes/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.9, 'sport_bikes');
        }
        // Olympics and Tours
        if (preg_match('/Olympics|Olympic[._ -]?Games|Tour de France|Olympic[._ -]?Highlights/i', $name)) {
            return $this->matched(Category::TV_SPORT, 0.9, 'sport_olympics');
        }
        if ($this->isSportEvent($name)) {
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
        if (! $context->categorizeForeign) {
            return null;
        }
        $name = $context->releaseName;

        // Exclusions for false positives
        if (preg_match('/Nevens[._ -]Portuguese|Martin[._ -]Compstons|Formula[- ._]?\d?/i', $name)) {
            return null;
        }

        // Comprehensive foreign patterns from Categorize.php
        if (preg_match('/[._ -](chinese|dk|fin|FRENCH|ger|heb|ita|jap|kor|nor|nordic|nl|pl|swe)[._ -]?(sub|dub)(ed|bed|s)?|<German>/i', $name)) {
            return $this->matched(Category::TV_FOREIGN, 0.85, 'foreign_subdub');
        }
        if (preg_match('/[._ -](French|TRUEFRENCH|VGQ|VFQ|VFF|VFI|VFB|VF2|VOSTFR|SUBFRENCH)/i', $name)) {
            return $this->matched(Category::TV_FOREIGN, 0.85, 'foreign_french');
        }
        if (preg_match('/[._ -](brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|german|greek|hebrew|icelandic|italian|ita|latin|mandarin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish).+(720p|1080p|Divx|DOKU|DUB(BED)?|DLMUX|NOVARIP|RealCo|Sub(bed|s)?|Web[._ -]?Rip|WS|Xvid|x264)[._ -]/i', $name)) {
            return $this->matched(Category::TV_FOREIGN, 0.85, 'foreign_lang_quality');
        }
        if (preg_match('/[._ -](720p|1080p|Divx|DOKU|DUB(BED)?|DLMUX|NOVARIP|RealCo|Sub(bed|s)?|WEB(-DL|-?RIP)|WS|Xvid).+(brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|ita|latin|mandarin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish|NLsubs)[._ -]/i', $name)) {
            return $this->matched(Category::TV_FOREIGN, 0.85, 'foreign_quality_lang');
        }
        if (preg_match('/(S\d\d[EX]\d\d|DOCU(MENTAIRE)?|TV)?[._ -](FRENCH|German|Dutch)[._ -](720p|1080p|dv([bd])r(ip)?|LD|HD\-?TV|TV[._ -]?RIP|x264|WEB(-DL|-?RIP))[._ -]/i', $name)) {
            return $this->matched(Category::TV_FOREIGN, 0.85, 'foreign_pattern');
        }
        if (preg_match('/[._ -]FastSUB|\.NL\.|nlvlaams|patrfa|RealCO|Seizoen|aflevering|slosinh|Videomann|seizoen|xslidian[._ -]|x264\-iZU|NORKiDS|HebTV/i', $name)) {
            return $this->matched(Category::TV_FOREIGN, 0.85, 'foreign_keyword');
        }
        if (preg_match('/(Survivor|Power[._ -]Couple)[._ -]Romania|Clanul|der[ .]sir|Weekoverzicht|Goede\.Tijden|Kwalificatie/i', $name)) {
            return $this->matched(Category::TV_FOREIGN, 0.85, 'foreign_show');
        }
        if (preg_match('/(FLEMISH|DUTCH)\.(2160|1080|720|540|480)[ip]|(UGDV)$/ui', $name)) {
            return $this->matched(Category::TV_FOREIGN, 0.85, 'foreign_flemish_dutch');
        }
        if (preg_match('/Formel[_ .]1|Fussbal/ui', $name)) {
            return $this->matched(Category::TV_FOREIGN, 0.8, 'foreign_german_sport');
        }

        // Legacy pattern
        if (preg_match('/(danish|flemish|Deutsch|dutch|french|german|hebrew|nl[._ -]?sub|dub(bed|s)?|\.NL|norwegian|swedish|swesub|spanish|Staffel)[._ -]|\(german\)|Multisub/i', $name)) {
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
        // Match WEB-DL, WEBRIP patterns with season/episode
        if (preg_match('/(S\d+).*.web[._-]?(dl|rip).*/i', $name)) {
            return $this->matched(Category::TV_WEBDL, 0.9, 'webdl_season');
        }

        // Match .WEB. followed by codec (h264, h265, x264, x265, HEVC, AVC)
        // e.g., "Show.S01E01.1080p.WEB.h265-GROUP" or "Show.S01E01.2160p.WEB.H265-GROUP"
        if (preg_match('/S\d+[._ -]?E\d+/i', $name) &&
            preg_match('/[._-]WEB[._-](h\.?26[45]|x\.?26[45]|HEVC|AVC)/i', $name)) {
            return $this->matched(Category::TV_WEBDL, 0.9, 'webdl_codec');
        }

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
        if (! $catWebDL && preg_match('/web[._ -]dl|web-?rip/i', $name)) {
            return $this->matched(Category::TV_HD, 0.8, 'hd_webdl_fallback');
        }

        return null;
    }

    protected function checkSD(string $name): ?CategorizationResult
    {
        if (preg_match('/(360|480|540|576)p|SDTV|Complete[._ -]Season|dvdr(ip)?|dvd5|dvd9|\.pdtv|SD[._ -]TV|TVRip|NTSC|BDRip|hdtv|xvid/i', $name)) {
            return $this->matched(Category::TV_SD, 0.8, 'sd_format');
        }
        if (preg_match('/(([HP])D[._ -]?TV|DSR|WebRip)[._ -]x264/i', $name)) {
            return $this->matched(Category::TV_SD, 0.8, 'sd_codec');
        }
        // Season/episode with SD markers
        if (preg_match('/s\d{1,3}[._ -]?[ed]\d{1,3}([ex]\d{1,3}|[\-.\w ])|\s\d{3,4}\s/i', $name) &&
            preg_match('/([HP])D[._ -]?TV|BDRip|WEB[._ -]x264/i', $name)) {
            return $this->matched(Category::TV_SD, 0.8, 'sd_season');
        }
        // Daily TV show with WEB.x264 format (e.g., Jimmy.Fallon.2020.05.14.Guest.WEB.x264-GRP)
        // These typically lack resolution info but are SD quality web releases
        if (preg_match('/[._ -](19|20)\d{2}[._ -]\d{2}[._ -]\d{2}[._ -]/i', $name) &&
            preg_match('/[._ -]WEB[._ -]x264/i', $name)) {
            return $this->matched(Category::TV_SD, 0.85, 'sd_daily_web');
        }

        return null;
    }

    protected function checkOther(ReleaseContext $context): ?CategorizationResult
    {
        $name = $context->releaseName;

        // Season + episode pattern (including releases that start with S##E##)
        if (preg_match('/^s\d{1,3}[._ -]?e\d{1,3}|[._ -]s\d{1,3}[._ -]?(e|d(isc)?)\d{1,3}([._ -]|$)/i', $name)) {
            return $this->matched(Category::TV_OTHER, 0.8, 'tv_other');
        }
        // Standalone season pack pattern (S01, S02, etc.)
        if ($context->hasStandaloneSeasonToken()) {
            return $this->matched(Category::TV_OTHER, 0.6, 'tv_season_pack');
        }
        // Daily TV show date pattern (e.g., Jimmy.Fallon.2020.05.14, Colbert.2024.01.15)
        // Give reasonable confidence for date-based TV shows
        if (preg_match('/[._ -](19|20)\d{2}[._ -]\d{2}[._ -]\d{2}[._ -]/i', $name)) {
            return $this->matched(Category::TV_OTHER, 0.75, 'tv_daily_date');
        }

        return null;
    }

    protected function isSportEvent(string $name): bool
    {
        if (preg_match(self::SPORTS_MARKER_REGEX, $name) === 1
            && preg_match(self::SPORTS_EVENT_CONTEXT_REGEX, $name) === 1) {
            return true;
        }

        return preg_match(self::SPORTS_AMBIGUOUS_EVENT_REGEX, $name) === 1
            && preg_match(self::SPORTS_FIXTURE_CONTEXT_REGEX, $name) === 1
            && preg_match(self::DISC_SOURCE_REGEX, $name) !== 1;
    }

    /**
     * NZBSPLIT uploaders file every post under a sport tag (`MLB__NZBSPLIT__...`), so the tag
     * decides even when the title itself names no league (`Playoffs.14-05-2026...`).
     * Confidence 0.95 stops later pipes; a mistagged episode or a magazine is left alone.
     */
    protected function checkSportSourceTag(string $name, ReleaseContext $context): ?CategorizationResult
    {
        if (! $context->hasSportSourceTag() || $context->hasBookSignals()) {
            return null;
        }

        if (preg_match('/\bS\d{1,3}[._ -]?E\d{1,3}\b/i', $name)) {
            return null;
        }

        return $this->matched(Category::TV_SPORT, 0.95, $this->checkSport($name)->matchedBy ?? 'nzbsplit_sport_tag');
    }
}
