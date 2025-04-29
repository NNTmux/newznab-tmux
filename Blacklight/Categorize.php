<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\Settings;
use App\Models\UsenetGroup;
use Exception;

/**
 * Categorizing of releases by name/group.
 *
 *
 * Class Categorize
 */
class Categorize
{
    /**
     * Temporary category while we sort through the name.
     */
    protected int $tmpCat = Category::OTHER_MISC;

    protected bool $categorizeForeign;

    protected bool $catWebDL;

    /**
     * Release name to sort through.
     */
    public string $releaseName;

    /**
     * Release poster to sort through.
     */
    public string $poster;

    /**
     * Group id of the releasename we are sorting through.
     */
    public string|int $groupId;

    public string $groupName;

    /**
     * Categorize constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        $this->categorizeForeign = (bool) Settings::settingValue('categorizeforeign');
        $this->catWebDL = (bool) Settings::settingValue('catwebdl');
    }

    /**
     * Determine the most appropriate category for a release based on name and group.
     *
     * @param int|string $groupId  The usenet group ID
     * @param  string  $releaseName  The name of the release to categorize
     * @param  string  $poster  The person/entity who posted the release
     * @param  bool  $debug  Whether to include debug information in the return value
     * @return array The categorization result with category ID and optional debug info
     *
     * @throws \Exception
     */
    public function determineCategory(int|string $groupId, string $releaseName = '', string $poster = '', bool $debug = false): array
    {
        // Initialize properties
        $this->releaseName = $releaseName;
        $this->groupId = $groupId;
        $this->poster = $poster;
        $this->groupName = UsenetGroup::whereId($this->groupId)->value('name') ?? '';
        $this->tmpCat = Category::OTHER_MISC;

        // Store original category for debugging
        $originalCategory = $this->tmpCat;
        $matchedBy = 'default';

        // Define categorization methods in priority order
        // More specific categories are checked first, then fall back to broader categories
        $categorizationMethods = [
            // Check by group name first (can provide specific category info)
            'byGroupName' => 'Group Name',
            // Check XXX categories
            'isXxx' => 'Adult Content',

            // Check media categories in order of specificity
            'isTV' => 'TV Content',
            'isMovie' => 'Movie Content',

            // Check other content types
            'isBook' => 'Book Content',
            'isMusic' => 'Music Content',

            // Check digital content categories
            'isPC' => 'PC Software/Games',
            'isConsole' => 'Console Games',

            // Check for miscellaneous file types last (as fallback)
            'isMisc' => 'Miscellaneous/Hash Detection',
        ];

        // Process each categorization method in priority order
        foreach ($categorizationMethods as $method => $description) {
            // Skip empty method names (allows easy commenting out for testing)
            if (empty($method)) {
                continue;
            }

            // Call the method and if it returns true, capture which method matched
            if ($this->{$method}()) {
                $matchedBy = $description;
                break;
            }
        }

        // Prepare result array
        $result = ['categories_id' => $this->tmpCat];

        // Add debug information if requested
        if ($debug) {
            $result['debug'] = [
                'original_category' => $originalCategory,
                'final_category' => $this->tmpCat,
                'matched_by' => $matchedBy,
                'release_name' => $this->releaseName,
                'group_name' => $this->groupName,
            ];
        }

        return $result;
    }

    /**
     * Determine category based on the Usenet group name.
     *
     * @return bool True if categorization was successful, false otherwise
     */
    public function byGroupName(): bool
    {
        switch (true) {
            case preg_match('/alt\.binaries\.erotica([.]\w+)?/i', $this->groupName):
                if ($this->isXxx()) {
                    return true;
                }
                $this->tmpCat = Category::XXX_OTHER;

                return true;
            case preg_match('/alt\.binaries\.podcast$/i', $this->groupName):
                $this->tmpCat = Category::MUSIC_PODCAST;

                return true;
            case preg_match('/alt\.binaries\.music\.(\w+)?/i', $this->groupName):
                if ($this->isMusic()) {
                    return true;
                }
                $this->tmpCat = Category::MUSIC_OTHER;

                return true;
            default:
                return false;
        }
    }

    //
    // Beginning of functions to determine category by release name.
    //

    public function isTV(): bool
    {
        if (preg_match('/Daily[\-_\.]Show|Nightly News|^\[[a-zA-Z\.\-]+\].*[\-_].*\d{1,3}[\-_. ](([\[\(])(h264-)?\d{3,4}([pi])([\]\)])\s?(\[AAC\])?|\[[a-fA-F0-9]{8}\]|(8|10)BIT|hi10p)(\[[a-fA-F0-9]{8}\])?|(\d\d-){2}[12]\d{3}|[12]\d{3}(\.\d\d){2}|\d+x\d+|\.e\d{1,3}\.|s\d{1,4}[._ -]?[ed]\d{1,3}([ex]\d{1,3}|[\-.\w ])|[._ -](\dx\d\d|C4TV|Complete[._ -]Season|DSR|([DHPS])DTV|EP[._ -]?\d{1,3}|S\d{1,3}.+Extras|SUBPACK|Season[._ -]\d{1,2})([._ -]|$)|TVRIP|TV[._ -](19|20)\d\d|Troll(HD|UHD)/i', $this->releaseName)
            && ! preg_match('/^(Defloration|MetArt|MetArtX|SexArt|TheLifeErotic|VivThomas|CzechVR|VRBangers|WankzVR|BadoinkVR|NaughtyAmerica)(.|\s)?\d{4}[._ -]\d{2}[._ -]\d{2}|[._ -](flac|imageset|mp3|xxx|XXX|porn|adult|sex)[._ -]|[ .]exe$|[._ -](shemale|transsexual|bisexual|siterip|JAV|JavHD)\b/i', $this->releaseName)) {
            switch (true) {
                case $this->isOtherTV():
                case $this->categorizeForeign && $this->isForeignTV():
                case $this->isSportTV():
                case $this->isDocumentaryTV():
                case $this->isTVx265():
                case $this->isUHDTV():
                case $this->catWebDL && $this->isWEBDL():
                case $this->isAnimeTV():
                case $this->isHDTV():
                case $this->isSDTV():
                case $this->isOtherTV2():
                    return true;
                default:
                    $this->tmpCat = Category::TV_OTHER;

                    return true;
            }
        }

        if (preg_match('/[._ -]((19|20)\d\d[._ -]\d{1,2}[._ -]\d{1,2}[._ -]VHSRip|Indy[._ -]?Car|(iMPACT|Smoky[._ -]Mountain|Texas)[._ -]Wrestling|Moto[._ -]?GP|NSCS[._ -]ROUND|NECW[._ -]TV|(Per|Post)\-Show|PPV|WrestleMania|WCW|WEB[._ -]HD|WWE[._ -](Monday|NXT|RAW|Smackdown|Superstars|WrestleMania))[._ -]/i', $this->releaseName)) {
            if ($this->isSportTV()) {
                return true;
            }
            $this->tmpCat = Category::TV_OTHER;

            return true;
        }

        return false;
    }

    public function isOtherTV(): bool
    {
        if (preg_match('/[._ -]S\d{1,3}.+(EP\d{1,3}|Extras|SUBPACK)[._ -]|News/i', $this->releaseName)
            // special case for "Have.I.Got.News.For.You" tv show
            && ! preg_match('/[._ -]Got[._ -]News[._ -]For[._ -]You/i', $this->releaseName)
        ) {
            $this->tmpCat = Category::TV_OTHER;

            return true;
        }

        return false;
    }

    public function isForeignTV(): bool
    {
        switch (true) {
            case preg_match('/[._ -](chinese|dk|fin|french|ger?|heb|ita|jap|kor|nor|nordic|nl|pl|swe)[._ -]?(sub|dub)(ed|bed|s)?|<German>/i', $this->releaseName):
            case preg_match('/[._ -](brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|ita|latin|mandarin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish).+(720p|1080p|Divx|DOKU|DUB(BED)?|DLMUX|NOVARIP|RealCo|Sub(bed|s)?|Web[._ -]?Rip|WS|Xvid|x264)[._ -]/i', $this->releaseName):
            case preg_match('/[._ -](720p|1080p|Divx|DOKU|DUB(BED)?|DLMUX|NOVARIP|RealCo|Sub(bed|s)?|WEB(-DL|-?RIP)|WS|Xvid).+(brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|ita|latin|mandarin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)[._ -]/i', $this->releaseName):
            case preg_match('/(S\d\d[EX]\d\d|DOCU(MENTAIRE)?|TV)?[._ -](FRENCH|German|Dutch)[._ -](720p|1080p|dv([bd])r(ip)?|LD|HD\-?TV|TV[._ -]?RIP|x264|WEB(-DL|-?RIP))[._ -]/i', $this->releaseName):
            case preg_match('/[._ -]FastSUB|NL|nlvlaams|patrfa|RealCO|Seizoen|slosinh|Videomann|Vostfr|xslidian[._ -]|x264\-iZU/i', $this->releaseName):
                $this->tmpCat = Category::TV_FOREIGN;

                return true;
            default:
                return false;
        }
    }

    public function isSportTV(): bool
    {
        switch (true) {
            case preg_match('/[._ -]?(Bellator|bundesliga|EPL|ESPN|FIA|la[._ -]liga|MMA|motogp|NFL|MLB|NCAA|PGA|FIM|NJPW|red[._ -]bull|.+race|Sengoku|Strikeforce|supercup|uefa|UFC|wtcc|WWE)[._ -]/i', $this->releaseName):
            case preg_match('/[._ -]?(DTM|FIFA|formula[._ -]1|indycar|Rugby|NASCAR|NBA|NHL|NRL|netball[._ -]anz|ROH|SBK|Superleague|The[._ -]Ultimate[._ -]Fighter|TNA|V8[._ -]Supercars|WBA|WrestleMania)[._ -]/i', $this->releaseName):
            case preg_match('/[._ -]?(AFL|Grand Prix|Indy[._ -]Car|(iMPACT|Smoky[._ -]Mountain|Texas)[._ -]Wrestling|Moto[._ -]?GP|NSCS[._ -]ROUND|NECW|Poker|PWX|Rugby|WCW)[._ -]/i', $this->releaseName):
            case preg_match('/[._ -]?(Horse)[._ -]Racing[._ -]/i', $this->releaseName):
            case preg_match('/[._ -](VERUM|GRiP|Ebi|OVERTAKE|LEViTATE|WiNNiNG|ADMIT)/i', $this->releaseName):
                $this->tmpCat = Category::TV_SPORT;

                return true;
            default:
                return false;
        }
    }

    public function isDocumentaryTV(): bool
    {
        if (preg_match('/[._ -](Docu|Documentary)[._ -]/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_DOCU;

            return true;
        }

        return false;
    }

    public function isWEBDL(): bool
    {
        if (preg_match('/(S\d+).*.web[._-]?(dl|rip).*/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_WEBDL;

            return true;
        }

        return false;
    }

    public function isAnimeTV(): bool
    {
        if (preg_match('/[._ -]Anime[._ -]|^\[[a-zA-Z\.\-]+\].*[\-_].*\d{1,3}[\-_. ](([\[\(])((\d{1,4}x\d{1,4})|(h264-)?\d{3,4}([pi]))([\]\)])\s?(\[AAC\])?|\[[a-fA-F0-9]{8}\]|(8|10)BIT|hi10p)(\[[a-fA-F0-9]{8}\])?/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_ANIME;

            return true;
        }
        if (preg_match('/(ANiHLS|HaiKU|ANiURL)/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_ANIME;

            return true;
        }

        return false;
    }

    public function isHDTV(): bool
    {
        if (preg_match('/1080([ip])|720p|bluray/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_HD;

            return true;
        }
        if (! $this->catWebDL && preg_match('/web[._ -]dl|web-?rip/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_HD;

            return true;
        }

        return false;
    }

    public function isUHDTV(): bool
    {
        if (preg_match('/(S\d+).*(2160p).*(Netflix|Amazon|NF|AMZN).*(TrollUHD|NTb|VLAD|DEFLATE|POFUDUK|CMRG)/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_UHD;

            return true;
        }

        return false;
    }

    public function isSDTV(): bool
    {
        switch (true) {
            case preg_match('/(360|480|576)p|Complete[._ -]Season|dvdr(ip)?|dvd5|dvd9|\.pdtv|SD[._ -]TV|TVRip|NTSC|BDRip|hdtv|xvid/i', $this->releaseName):
            case preg_match('/(([HP])D[._ -]?TV|DSR|WebRip)[._ -]x264/i', $this->releaseName):
            case preg_match('/s\d{1,3}[._ -]?[ed]\d{1,3}([ex]\d{1,3}|[\-.\w ])|\s\d{3,4}\s/i', $this->releaseName) && preg_match('/([HP])D[._ -]?TV|BDRip|WEB[._ -]x264/i', $this->releaseName):
                $this->tmpCat = Category::TV_SD;

                return true;
            default:
                return false;
        }
    }

    public function isOtherTV2(): bool
    {
        if (preg_match('/[._ -]s\d{1,3}[._ -]?(e|d(isc)?)\d{1,3}([._ -]|$)/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_OTHER;

            return true;
        }

        return false;
    }

    public function isTVx265(): bool
    {
        if (preg_match('/(S\d+).*(x265).*(rmteam|MeGusta|HETeam|PSA|ONLY|H4S5S|TrollHD|ImE)/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_X265;

            return true;
        }

        return false;
    }

    //  Movies.

    public function isMovie(): bool
    {
        if (preg_match('/[._ -]AVC|[BH][DR]RIP|(Bluray|Blu-Ray)|BD[._ -]?(25|50)?|\bBR\b|Camrip|[._ -]\d{4}[._ -].+(720p|1080p|Cam|HDTS|2160p)|DIVX|[._ -]DVD[._ -]|DVD-?(5|9|R|Rip)|Untouched|VHSRip|XVID|[._ -](DTS|TVrip|webrip|WEBDL|WEB-DL)[._ -]|\b(2160)p\b.*\b(Netflix|Amazon|NF|AMZN|Disney)\b/i', $this->releaseName) && ! preg_match('/s\d{1,3}[._ -]?[ed]\d{1,3}|auto(cad|desk)|divx[._ -]plus|[._ -]exe$|[._ -](jav|XXX)[._ -]|SWE6RUS|\wXXX(1080p|720p|DVD)|Xilisoft|\.S[0-9]\d{1,3}\./i', $this->releaseName)) {
            return match (true) {
                $this->categorizeForeign && $this->isMovieForeign(), $this->isMovieDVD(), $this->isMovieX265(), $this->isMovieUHD(), $this->catWebDL && $this->isMovieWEBDL(), $this->isMovieSD(), $this->isMovie3D(), $this->isMovieBluRay(), $this->isMovieHD(), $this->isMovieOther() => true,
                default => false,
            };
        }

        return false;
    }

    public function isMovieForeign(): bool
    {
        switch (true) {
            case preg_match('/(danish|flemish|Deutsch|dutch|french|german|heb|hebrew|nl[._ -]?sub|dub(bed|s)?|\.NL|norwegian|swedish|swesub|spanish|Staffel)[._ -]|\(german\)|Multisub/i', $this->releaseName):
            case stripos($this->releaseName, 'Castellano') !== false:
            case preg_match('/(720p|1080p|AC3|AVC|DIVX|DVD(5|9|RIP|R)|XVID)[._ -](Dutch|French|German|ITA)|\(?(Dutch|French|German|ITA)\)?[._ -](720P|1080p|AC3|AVC|DIVX|DVD(5|9|RIP|R)|WEB(-DL|-?RIP)|HD[._ -]|XVID)/i', $this->releaseName):
                $this->tmpCat = Category::MOVIE_FOREIGN;

                return true;
            default:
                return false;
        }
    }

    public function isMovieDVD(): bool
    {
        if (preg_match('/(dvd\-?r|[._ -]dvd|dvd9|dvd5|[._ -]r5)[._ -]/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_DVD;

            return true;
        }

        return false;
    }

    public function isMovieSD(): bool
    {
        if (preg_match('/(divx|dvdscr|extrascene|dvdrip|\.CAM|HDTS(-LINE)?|vhsrip|xvid(vd)?)[._ -]/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_SD;

            return true;
        }

        return false;
    }

    public function isMovie3D(): bool
    {
        if (preg_match('/[._ -]3D\s?[\.\-_\[ ](1080p|(19|20)\d\d|AVC|BD(25|50)|Blu[._ -]?ray|CEE|Complete|GER|MVC|MULTi|SBS|H(-)?SBS)[._ -]/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_3D;

            return true;
        }

        return false;
    }

    public function isMovieBluRay(): bool
    {
        if (preg_match('/bluray-|[._ -]bd?[._ -]?(25|50)|blu-ray|Bluray\s-\sUntouched|[._ -]untouched[._ -]/i', $this->releaseName)
            && ! preg_match('/SecretUsenet\.com$/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_BLURAY;

            return true;
        }

        return false;
    }

    public function isMovieHD(): bool
    {
        if (preg_match('/720p|1080p|AVC|VC1|VC-1|web-dl|wmvhd|x264|XvidHD|bdrip/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_HD;

            return true;
        }
        if ($this->catWebDL === false && preg_match('/web[._ -]dl|web-?rip/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_HD;

            return true;
        }

        return false;
    }

    public function isMovieUHD(): bool
    {
        // Skip TV shows that match the streaming service pattern
        if (preg_match('/(S\d+).*(2160p).*(Netflix|Amazon|NF|AMZN).*(TrollUHD|NTb|VLAD|DEFLATE|CMRG)/i', $this->releaseName)) {
            return false;
        }

        // Check for common UHD indicators
        if (stripos($this->releaseName, '2160p') !== false ||
            preg_match('/\b(UHD|Ultra[._ -]HD|4K)\b/i', $this->releaseName) ||
            (preg_match('/\b(HDR|HDR10|HDR10\+|Dolby[._ -]?Vision)\b/i', $this->releaseName) &&
             preg_match('/\b(HEVC|H\.?265|x265)\b/i', $this->releaseName)) ||
            (stripos($this->releaseName, 'UHD') !== false &&
             preg_match('/\b(BR|BluRay|Blu[._ -]?Ray)\b/i', $this->releaseName))) {

            $this->tmpCat = Category::MOVIE_UHD;

            return true;
        }

        return false;
    }

    public function isMovieOther(): bool
    {
        if (preg_match('/[._ -]cam[._ -]/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_OTHER;

            return true;
        }

        return false;
    }

    public function isMovieWEBDL(): bool
    {
        if (preg_match('/web[._ -]dl|web-?rip/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_WEBDL;

            return true;
        }

        return false;
    }

    public function isMovieX265(): bool
    {
        if (preg_match('/(\w+[\.-_\s]+).*(x265).*(Tigole|SESKAPiLE|CHD|IAMABLE|THREESOME|OohLaLa|DEFLATE|NCmt)/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_X265;

            return true;
        }

        return false;
    }

    //  PC.

    public function isPC(): bool
    {
        return match (true) {
            $this->isPhone(), $this->isMac(), $this->isPCGame(), $this->isISO(), $this->is0day() => true,
            default => false,
        };
    }

    public function isPhone(): bool
    {
        switch (true) {
            case preg_match('/[^a-z0-9](IPHONE|ITOUCH|IPAD)[._ -]/i', $this->releaseName):
                $this->tmpCat = Category::PC_PHONE_IOS;
                break;
            case preg_match('/[._ -]?(ANDROID)[._ -]/i', $this->releaseName):
                $this->tmpCat = Category::PC_PHONE_ANDROID;
                break;
            case preg_match('/[^a-z0-9](symbian|xscale|wm5|wm6)[._ -]/i', $this->releaseName):
                $this->tmpCat = Category::PC_PHONE_OTHER;
                break;
            default:
                return false;
        }

        return true;
    }

    public function isISO(): bool
    {
        switch (true) {
            case preg_match('/[._ -]([a-zA-Z]{2,10})?iso[ _.-]|[\-. ]([a-z]{2,10})?iso$/i', $this->releaseName):
            case preg_match('/[._ -](DYNAMiCS|INFINITESKILLS|UDEMY|kEISO|PLURALSIGHT|DIGITALTUTORS|TUTSPLUS|OSTraining|PRODEV|CBT\.Nuggets|COMPRISED)/i', $this->releaseName):
                $this->tmpCat = Category::PC_ISO;

                return true;
            default:
                return false;
        }
    }

    public function is0day(): bool
    {
        switch (true) {
            case preg_match('/[._ -]exe$|[._ -](utorrent|Virtualbox)[._ -]|\b0DAY\b|incl.+crack| DRM$|>DRM</i', $this->releaseName):
            case preg_match('/[._ -]((32|64)bit|converter|i\d86|key(gen|maker)|freebsd|GAMEGUiDE|hpux|irix|linux|multilingual|Patch|Pro v\d{1,3}|portable|regged|software|solaris|template|unix|win2kxp2k3|win64|win(2k|32|64|all|dows|nt(2k)?(xp)?|xp)|win9x(me|nt)?|x(32|64|86))[._ -]/i', $this->releaseName):
            case preg_match('/\b(Adobe|auto(cad|desk)|-BEAN|Cracked|Cucusoft|CYGNUS|Divx[._ -]Plus|\.(deb|exe)|DIGERATI|FOSI|-FONT|Key(filemaker|gen|maker)|Lynda\.com|lz0|MULTiLANGUAGE|Microsoft\s*(Office|Windows|Server)|MultiOS|-(iNViSiBLE|SPYRAL|SUNiSO|UNION|TE)|v\d{1,3}.*?Pro|[._ -]v\d{1,3}[._ -]|\(x(64|86)\)|Xilisoft)\b/i', $this->releaseName):
                $this->tmpCat = Category::PC_0DAY;

                return true;
            default:
                return false;
        }
    }

    public function isMac(): bool
    {
        if (preg_match('/(\b|[._ -])mac([\.\s])?osx(\b|[\-_. ])/i', $this->releaseName)) {
            $this->tmpCat = Category::PC_MAC;

            return true;
        }

        return false;
    }

    public function isPCGame(): bool
    {
        if (preg_match('/[^a-z0-9](0x0007|ALiAS|BACKLASH|BAT|CLONECD|CPY|FAS(DOX|iSO)|FLT([._ -]|COGENT)|FLT(DOX)?|PC GAMES?|\(?(Game([sz])|GAME([SZ]))\)? ?(\(([Cc])\))|GENESIS|-GOG|-HATRED|HI2U|INLAWS|JAGUAR|MAZE|MONEY|OUTLAWS|PPTCLASSiCS|PC Game|PROPHET|RAiN|Razor1911|RELOADED|DEViANCE|PLAZA|RiTUELYPOGEiOS|[rR][iI][pP]-[uU][nN][lL][eE][aA][sS][hH][eE][dD]|Steam(\b)?Rip|SKIDROW|TiNYiSO|CODEX|SiMPLEX)[^a-z0-9]?/', $this->releaseName)) {
            $this->tmpCat = Category::PC_GAMES;

            return true;
        }

        if ($this->checkPoster('/<PC@MASTER\.RACE>/i', $this->poster, Category::PC_GAMES)) {
            return true;
        }

        return false;
    }

    //	XXX.

    public function isXxx(): bool
    {
        return match (true) {
            $this->isXxxVr(), $this->isXxxClipHD(), $this->isXxxPack(), $this->isXxxClipSD(), $this->isXxxSD(), $this->isXxxUHD(), $this->catWebDL && $this->isXxxWEBDL(), $this->isXxx264(), $this->isXxxXvid(), $this->isXxxImageset(), $this->isXxxWMV(), $this->isXxxDVD(), $this->isXxxOther() => true,
            default => false,
        };
    }

    public function isXxx264(): bool
    {
        if (preg_match('/720p|1080(hd|[ip])|[xh][^a-z0-9]?264/i', $this->releaseName) &&
            ! preg_match('/\bwmv\b|S\d{1,2}E\d{1,2}|\d+x\d+/i', $this->releaseName) &&
            stripos($this->releaseName, 'SDX264XXX') === false &&
            preg_match('/\bXXX\b|a\.b\.erotica|BangBros|ClubSeventeen|Cum(ming|shot)|Defloration|Err?oticax?|JoyMii|MetArt|MetArtX|Nubiles|Porn(o|lation)?|SexArt|TheLifeErotic|Tushy|Vixen|VivThomas|X-Art|JAV|lesb(ians?|os?)|NaughtyAmerica|RealityKings|Brazzers|WowGirls/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_X264;

            return true;
        }

        // Add explicit check for adult content before matching web-dl/web-rip
        if ($this->catWebDL === false &&
            preg_match('/web[._ -]dl|web-?rip/i', $this->releaseName) &&
            preg_match('/\bXXX\b|a\.b\.erotica|BangBros|BangBros18|ClubSeventeen|Cum(ming|shot)|Defloration|Err?oticax?|JoyMii|MetArt|MetArtX|Nubiles|Porn(o|lation)?|SexArt|TheLifeErotic|Tushy|Vixen|VivThomas|X-Art|JAV Uncensored|lesb(ians?|os?)|mastur(bation|e?bate)|nympho?|OLDER ANGELS|Brazzers|NaughtyAmerica|RealityKings|sexontv|slut|Squirt|Transsexual|WowGirls|Playboy/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_X264;

            return true;
        }

        return false;
    }

    public function isXxxUHD(): bool
    {
        if (preg_match('/XXX.+(2160p)+[\w\-.]+(M[PO][V4]-(KTR|GUSH|FaiLED|SEXORS|hUSHhUSH|YAPG|WRB|NBQ|FETiSH))/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_UHD;

            return true;
        }

        return false;
    }

    public function isXxxClipHD(): bool
    {
        // First check for specific adult content to exclude that's not clips
        // Refined to exclude only if these words are standalone or at the beginning
        if (preg_match('/^(Complete|Pack|Collection|Anthology|Siterip|SiteRip|Website\.Rip|WEBRip)\b|\b(Complete|Pack|Collection|Anthology)\b.+(Pack|Set|of|[0-9]{2,})/i', $this->releaseName)) {
            return false;
        }

        if (preg_match('/\b(S\d{1,2}E\d{1,2}|S\d{1,2}|Season\s\d{1,2}|E\d{1,2})\b/i', $this->releaseName) ||
            preg_match('/\b(Rick\.And\.Morty|Game\.Of\.Thrones|Walking\.Dead|Breaking\.Bad|Stranger\.Things)\b/i', $this->releaseName)) {
            return false;
        }

        // Adult keywords commonly found in titles
        $adultKeywords = 'Anal|Ass|BBW|BDSM|Blow|Boob|Bukkake|Casting|Couch|Cock|Compilation|Creampie|Cum|Dick|Dildo|Facial|Fetish|Fuck|Gang|Hardcore|Homemade|Horny|Interracial|Lesbian|MILF|Masturbat|Nympho|Oral|Orgasm|Penetrat|Pornstar|POV|Pussy|Riding|Seduct|Sex|Shaved|Slut|Squirt|Suck|Swallow|Threesome|Tits|Titty|Toy|Virgin|Whore';

        // Known adult studios
        $knownStudios = 'Brazzers|NaughtyAmerica|RealityKings|Bangbros|BangBros18|TeenFidelity|PornPros|SexArt|WowGirls|Vixen|Blacked|Tushy|Deeper|Bellesa|Defloration|MetArt|MetArtX|TheLifeErotic|VivThomas|JoyMii|Nubiles|NubileFilms|FamilyStrokes|X-Art|Babes|Twistys|WetAndPuffy|WowPorn|MomsTeachSex|Mofos|BangBus|Passion-HD|EvilAngel|DorcelClub|Private|Hustler|CherryPimps|HuCows|TransSensual|SexMex|FamilyTherapy|ATKGirlfriends';

        // Match performer name pattern with descriptive title, spelled-out month name, and HD resolution
        if (preg_match('/^([A-Z][a-z]+)(\.|\s)([A-Z][a-z]+)(\.|\s)([A-Z][a-z]+)?(\.|\s)([A-Z][a-z]+)?(\.|\s)?(January|February|March|April|May|June|July|August|September|October|November|December)[._ -](\d{1,2})[_.-]*(\d{4})[._ -]?(720p|1080p|2160p|HD|4K)/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        // Match studio name + performer names + descriptive title + HD resolution (without requiring date)
        if (preg_match('/^('.$knownStudios.')\.([A-Z][a-z]+)(\.([A-Z][a-z]+))?(\.and\.|\.&\.)([A-Z][a-z]+)(\.([A-Z][a-z]+))?\.([A-Z][a-z]+).*?(720p|1080p|2160p|HD|4K)/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        // Rest of the existing patterns remain unchanged
        if (preg_match('/^('.$knownStudios.')\.([A-Z][a-z]+)(\.([A-Z][a-z]+))?\.([A-Z][a-z]+\.)+.*?(720p|1080p|2160p|HD|4K)/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        // Match studio name + YY.MM.DD + model name + XXX identifier + HD resolution
        if (preg_match('/^([A-Z][a-zA-Z0-9]+)\.(\d{2})\.(\d{2})\.(\d{2})\.([A-Z][a-z]+)(\.([A-Z][a-z]+))?.*?(XXX|Porn|Sex|Adult).*?(720p|1080p|2160p|HD|4K)/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        // Match releases with month name in date format and HD resolution
        if (preg_match('/^([A-Z][a-z]+)[._ -]([A-Z][a-z]+).*?(January|February|March|April|May|June|July|August|September|October|November|December)[._ -](\d{1,2})[_._ -](\d{4})[._ -]?(720p|1080p|2160p|HD|4K)/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        // Match releases with model name, descriptive title with adult keywords, date and HD resolution
        if (preg_match('/^([A-Z][a-z]+)(\.|\s)([A-Z][a-z]+).*?('.$adultKeywords.').*?(\d{2})\.(\d{2})\.(\d{4}|20\d{2}).*?(720p|1080p|2160p|4k|HD)/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        // Match common date formats found in adult content with HD resolution
        if (preg_match('/([A-Z][a-z]+)(\.|\s)([A-Z][a-z]+).*?(\d{2})[\.\-](\d{2})[\.\-](20\d{2}|\d{2}).*?(720p|1080p|2160p|HD|4K)/i', $this->releaseName) &&
            preg_match('/('.$adultKeywords.')/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        // Rest of the existing patterns remain unchanged
        if (preg_match('/^([A-Z][a-zA-Z0-9]+)\.(20\d\d)\.(\d{2})\.(\d{2})\.[A-Z][a-z]/i', $this->releaseName) &&
            ! preg_match('/\b(S\d{2}E\d{2}|Documentary|Series)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        if (preg_match('/^([A-Z][a-zA-Z0-9]+)\.(\d{2})\.(\d{2})\.(\d{2})\./i', $this->releaseName) &&
            ! preg_match('/\b(S\d{2}E\d{2}|Documentary|Series)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        if (preg_match('/^([A-Z][a-zA-Z0-9]+)(\.Com)?\.\.(\d{2})\.(\d{2})\.(\d{2})\./i', $this->releaseName) &&
            ! preg_match('/\b(S\d{2}E\d{2}|Documentary|Series)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        if (preg_match('/\b(Scene[._-]?\d+|MILF|Anal|Hardcore|Sex|Porn|XXX|Explicit|Adult).*?(720p|1080p|2160p|HD|4K)\b|\b(720p|1080p|2160p|HD|4K).*?(Scene[._-]?\d+|MILF|Anal|Hardcore|Sex|Porn|XXX)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        if (preg_match('/^('.$knownStudios.'|[A-Z][a-zA-Z0-9]{2,})[._ -]+(?:\d{4}|\d{2})[\.\-_ ]\d{2}[\.\-_ ]\d{2,4}[._ -]/i', $this->releaseName) &&
            ! preg_match('/\b(S\d{2}E\d{2}|Documentary|Series)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        if (preg_match('/^([A-Z][a-zA-Z0-9]+)\b.*\d{4}[._ -]\d{2}[._ -]\d{2}/i', $this->releaseName) &&
            preg_match('/(720p|1080p|1440p|2160p|HD|4K)/i', $this->releaseName) &&
            ! preg_match('/\b(S\d{2}E\d{2}|Documentary|Series)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        if (preg_match('/^([A-Z][a-zA-Z0-9]+)[._ -]+\d{4}[._ -]\d{2}[._ -]\d{2}[._ -]([A-Z][a-z]+[._ -][A-Z][a-z]+|[A-Z][a-z]+)/i', $this->releaseName) &&
            ! preg_match('/\b(S\d{2}E\d{2}|Documentary|Series)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        if (preg_match('/^([A-Z][a-zA-Z0-9]+)\.(\d{4}|\d{2})[\.\-_ ](\d{2})[\.\-_ ](\d{2})(\.[A-Z][\w]+)?/i', $this->releaseName) &&
            ! preg_match('/\b(S\d{2}E\d{2}|Documentary|Series)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        if (preg_match('/\b(XXX|MILF|Anal|Sex|Porn)[._ -]+(720p|1080p|2160p|HD|4K)\b|\b(720p|1080p|2160p|HD|4K)[._ -]+(XXX|MILF|Anal|Sex|Porn)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        if (preg_match('/^[\w\-.]+(\d{2}\.\d{2}\.\d{2}).+(720|1080)+[\w\-.]+(M[PO][V4]-(KTR|GUSH|FaiLED|SEXORS|hUSHhUSH|YAPG|TRASHBIN|WRB|NBQ|FETiSH))/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;

            return true;
        }

        return false;
    }

    public function isXxxWMV(): bool
    {
        // First check for formats that should NOT be categorized as WMV
        if (preg_match('/\b(720p|1080p|2160p|x264|x265|h264|h265|hevc|XviD|MP4-|\.mp4)[._ -]/i', $this->releaseName) ||
            stripos($this->releaseName, 'SDX264XXX') !== false) {
            return false;
        }

        // Check for explicit WMV indicators
        if (preg_match('/(
            # Explicit WMV format mentions
            \b(WMV|Windows\s?Media\s?Video)\b|
            # WMV file extensions
            \b\w+\.wmv\b|[._ -]wmv[._ -]|\.wmv$|
            # WMV scene release groups
            \b(WMV-SEXORS|KTR-wmv|FaiLED-wmv|wmv-PORNO)\b|
            # WMV specific sizes
            \b(wmv|windows\s?media)[._ -]\d+(\.\d+)?\s?(mb|gb)\b
            )/ix', $this->releaseName)) {
            $this->tmpCat = Category::XXX_WMV;

            return true;
        }

        // Check for older legacy formats often associated with WMV
        if (preg_match('/(
            # Older video formats commonly used with WMV
            \b(wm9|wmvhd)\b|
            # Legacy scene patterns for WMV
            \b(REALMEDIA|DIVX-WMVHD)\b|
            # Additional reliable WMV identifiers
            (WMAZ|WMAS|Windows-Media|MS-Video)
            )/ix', $this->releaseName)) {
            $this->tmpCat = Category::XXX_WMV;

            return true;
        }

        // Original pattern but much more restricted to avoid false positives
        if (preg_match('/[^a-z0-9](wmv)[^a-z0-9]/i', $this->releaseName) &&
            ! preg_match('/\b(mp4|xvid|webm|mkv|avi)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_WMV;

            return true;
        }

        return false;
    }

    public function isXxxXvid(): bool
    {
        if (preg_match('/(b[dr]|dvd)rip|detoxication|divx|nympho|pornolation|swe6|tesoro|xvid/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_XVID;

            return true;
        }

        return false;
    }

    public function isXxxDVD(): bool
    {
        if (preg_match('/dvdr[^i]|dvd[59]/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_DVD;

            return true;
        }

        return false;
    }

    public function isXxxVr(): bool
    {
        switch (true) {
            case preg_match('/OnlyFans/i', $this->releaseName):
                return false;
            case preg_match('/^[\w\-.]+(\d{2}\.\d{2}\.\d{2}).+(VR(180|360))+.*/i', $this->releaseName):
            case preg_match('/^VR(Hush|\.?(Cosplay|Spy|Conk|Porn|Latina|Bangers|KM|Mansion|Intimacy|oomed|Allure))|^VirtualReal|^Virtual(Taboo|Porn)|iStripper|SLROriginals|XSinsVR|NaughtyAmericaVR|WetVR|VRStars|SexBabesVR|BaDoinkVR|WankzVR|VRBangers|StripzVR|RealJamVR|TmwVRnet|MilfVR|KinkVR|CzechVR|HoloGirlsVR|VR Porn|\[VR\][.\s]Pack|BIBIVR|VRCosplayX|CzechVRFetish/i', $this->releaseName):
            case preg_match('/GearVR|Oculus|Quest[123]?|PSVR|Vive|Index|Pimax|Reverb|RiftS|SexLikeReal|3584p|^.*VR[\. -_]+?/i', $this->releaseName):
            case preg_match('/.+\.VR(180|360)\.(3584|3840|3072)p/i', $this->releaseName):
            case preg_match('/^SLR.+(VR|LR_180|LR-180|3072p)/i', $this->releaseName):
            case preg_match('/^SLR_SLR|^REQUEST\.SLR/i', $this->releaseName):
            case preg_match('/180x180_3dh|8K[\. _]VR|5K[\. _]VR|4K[\. _]VR/i', $this->releaseName):
                $this->tmpCat = Category::XXX_VR;

                return true;
            default:
                return false;
        }
    }

    public function isXxxImageset(): bool
    {
        if (preg_match('/IMAGESET|PICTURESET|ABPEA/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_IMAGESET;

            return true;
        }

        return false;
    }

    public function isXxxPack(): bool
    {
        if (preg_match('/[ .]PACK[ .]/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_PACK;

            return true;
        }

        return false;
    }

    public function isXxxOther(): bool
    {
        // If nothing else matches, then try these words.
        if (preg_match('/[._ -]Brazzers|Creampie|[._ -]JAV[._ -]|North\.Pole|^Nubiles|She[._ -]?Male|Transsexual|OLDER ANGELS/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_OTHER;

            return true;
        }

        return false;
    }

    public function isXxxClipSD(): bool
    {
        switch (true) {
            case $this->checkPoster('/anon@y[.]com/i', $this->poster, Category::XXX_CLIPSD):
            case $this->checkPoster('/@md-hobbys[.]com/i', $this->poster, Category::XXX_CLIPSD):
            case $this->checkPoster('/oz@lot[.]com/i', $this->poster, Category::XXX_CLIPSD):
                return true;
            case preg_match('/(iPT\sTeam|KLEENEX)/i', $this->releaseName):
            case stripos($this->releaseName, 'SDPORN') !== false:
                $this->tmpCat = Category::XXX_CLIPSD;

                return true;
            default:
                return false;
        }
    }

    public function isXxxSD(): bool
    {
        if (preg_match('/SDX264XXX|XXX\.HR\./i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_SD;

            return true;
        }

        return false;
    }

    public function isXxxWEBDL(): bool
    {
        // First check if this is a TV show to exclude it
        if (preg_match('/\b(S\d{1,2}E\d{1,2}|S\d{1,2}|Season\s\d{1,2}|E\d{1,2})\b/i', $this->releaseName) ||
            preg_match('/\b(Rick\.And\.Morty|Game\.Of\.Thrones|Walking\.Dead|Breaking\.Bad|Stranger\.Things)\b/i', $this->releaseName)) {
            return false;
        }

        // Adult keywords commonly found in titles
        $adultKeywords = 'Anal|Ass|BBW|BDSM|Blow|Boob|Bukkake|Casting|Couch|Cock|Compilation|Creampie|Cum|Dick|Dildo|Facial|Fetish|Fuck|Gang|Hardcore|Homemade|Horny|Interracial|Lesbian|MILF|Masturbat|Nympho|Oral|Orgasm|Penetrat|Pornstar|POV|Pussy|Riding|Seduct|Sex|Shaved|Slut|Squirt|Suck|Swallow|Threesome|Tits|Titty|Toy|Virgin|Whore';

        // Known adult studios
        $knownStudios = 'Brazzers|NaughtyAmerica|RealityKings|Bangbros|TeenFidelity|PornPros|SexArt|WowGirls|Vixen|Blacked|Tushy|Deeper|Bellesa|Defloration|MetArt|TheLifeErotic|VivThomas|JoyMii|Nubiles|NubileFilms|FamilyStrokes|X-Art|Babes|Twistys|WetAndPuffy|WowPorn|MomsTeachSex|Mofos|BangBus|Passion-HD|EvilAngel|DorcelClub';

        // Check for web-dl/webrip and require adult content keywords
        if (preg_match('/web[._ -]dl|web-?rip/i', $this->releaseName) &&
            (preg_match('/('.$adultKeywords.')/i', $this->releaseName) ||
             preg_match('/('.$knownStudios.')/i', $this->releaseName) ||
             preg_match('/\b(XXX|Porn|Adult|JAV|Hentai)\b/i', $this->releaseName))) {
            $this->tmpCat = Category::XXX_WEBDL;

            return true;
        }

        return false;
    }

    //	Console.

    public function isConsole(): bool
    {
        return match (true) {
            $this->isGameNDS(), $this->isGame3DS(), $this->isGamePS3(), $this->isGamePS4(), $this->isGamePSP(), $this->isGamePSVita(), $this->isGameWiiWare(), $this->isGameWiiU(), $this->isGameWii(), $this->isGameNGC(), $this->isGameXBOX360DLC(), $this->isGameXBOX360(), $this->isGameXBOXONE(), $this->isGameXBOX(), $this->isGameOther() => true,
            default => false,
        };
    }

    public function isGameNDS(): bool
    {
        // First check if the release name suggests Nintendo DS content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:NDS|nintendo\s+ds)|\b(?:nds|NDS)\b|nintendo.+(?<!3)(?:nds|ndsi)\b/i', $this->releaseName)) {
            // Check for region codes, version indicators, or ROM collections
            if (preg_match('/\((DE|DSi(?: Enhanced)?|_NDS-|EUR|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA)\)/i', $this->releaseName) ||
                preg_match('/\b(EUR|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA|ROMs?(et)?)\b/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_NDS;

                return true;
            }
        }

        return false;
    }

    public function isGame3DS(): bool
    {
        // First check if the release name suggests Nintendo 3DS content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:3DS|nintendo\s+3ds)|\b(?:3ds)\b|nintendo.+3ds|(?<!max\.)[_\.-]3DS(?![_\.-]max)/i', $this->releaseName)) {
            // Verify with region codes, version indicators, or other game-specific markers
            if (preg_match('/\((DE|EUR|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA|ASIA)\)/i', $this->releaseName) ||
                preg_match('/\b(EUR|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA|ASIA|ROMs?(et)?)\b/i', $this->releaseName) ||
                preg_match('/\b(CIA|3DS[_\.-]?ROM|eShop|Region\s*Free)\b/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_3DS;

                return true;
            }
        }

        return false;
    }

    public function isGameNGC(): bool
    {
        // First check if the release name suggests Nintendo GameCube content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:NGC|Nintendo\s+GameCube)|\b(?:GameCube)\b|[\._-]N?G(AME)?C(UBE)?[-_\.]/i', $this->releaseName)) {
            // Check for region codes or known GameCube release groups
            if (preg_match('/[(\_\-](?:DE|EUR?|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA?)[\)\_]/i', $this->releaseName) ||
                preg_match('/\b(?:EUR?|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA?|ROMs?(et)?)\b/i', $this->releaseName) ||
                preg_match('/-(?:(?:STAR|DEATH|STINKY|MOON|HOLY|G)?CUBE(?:SOFT)?|DARKFORCE|DNL|GP|ICP|iNSOMNIA|JAY|LaKiTu|METHS|NOMIS|QUBiSM|PANDORA|REACT0R|SUNSHiNE|SAVEPOiNT|SYNDiCATE|WAR3X|WRG)/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_OTHER;

                return true;
            }
        }

        return false;
    }

    public function isGamePS3(): bool
    {
        // First check if the release name suggests PlayStation 3 content
        if (preg_match('/(?:^|[^a-zA-Z0-9e])(?:PS3|PlayStation\s+3)|\b(?:PS3)\b|[\._-]PS3[\._-]/i', $this->releaseName)) {
            // Verify with region codes, game-specific markers, or known PS3 release groups
            if (preg_match('/\b(?:ANTiDOTE|APATHY|AGENCY|Caravan|DUPLEX|DLC|EUR?|Googlecus|GOTY|iNSOMNi|JAP|JPN|KONDIOS|MULTi|NRP|NTSC|PAL|PSN|SPLiT|STRiKE|USA?|ZRY)\b/i', $this->releaseName) ||
                preg_match('/\[PS3\]|\-HR/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PS3;

                return true;
            }
        }

        return false;
    }

    public function isGamePS4(): bool
    {
        // Common PS4 game edition indicators
        $editionPatterns = '(?:Gold|Deluxe|Complete|Definitive|GOTY|Game\s?of\s?the\s?Year|Digital|Standard|Ultimate|Special|Premium|Legacy|Collector\'?s?|Limited|Anniversary|Remastered|Collection)';

        // Most common PS4 release groups
        $releaseGroups = '(?:ANTiDOTE|AGENCY|APATHY|Caravan|COMPLEX|DUPLEX|DARKSiDERS|DODI|FALLEN|GC|HAREM|HRENO|iNLAWS|iNSOMNi|INTERNAL|KEPLER|LEMON|MarvTM|MULTi\d+|OPOISSO|PARADOX|PKG|PRELUDE|PROTOKOL|REGION1|REGION4|RELOADED|RESPAWN|REVENGE|SiMPLEX|SKIDROW|SPLiT|STRiKE|TKC|WaYsTeD|ZRY)';

        // Check for PS4 at start of filename with underscores
        if (preg_match('/^PS4[_\.\-]/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_PS4;

            return true;
        }

        // Check for CUSA pattern which is specific to PS4 games
        if (preg_match('/CUSA\d{5}/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_PS4;

            return true;
        }

        // Direct check for the PS4-DUPLEX pattern at the end of the name
        if (preg_match('/\.PS4-DUPLEX$/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_PS4;

            return true;
        }

        // First check if the release name suggests PlayStation 4 content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:PS4|PlayStation\s*4)|\bPS4\b|[_\.\-]PS4[_\.\-]/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_PS4;

            return true;
        }

        // Check for files with Game_Full_psgames which indicates PS4 game
        if (preg_match('/Game_Full_psgames/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_PS4;

            return true;
        }

        // Rest of the existing method...

        return false;
    }

    public function isGamePSP(): bool
    {
        // First check if the release name suggests PlayStation Portable content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:PSP|PlayStation\s+Portable)|\b(?:PSP)\b|[\._-]PSP[\._-]/i', $this->releaseName)) {
            // Verify with region codes, game-specific markers, or known PSP release groups
            if (preg_match('/\b(?:BAHAMUT|Caravan|EBOOT|EMiNENT|EUR?|EvoX|GAME|GHS|Googlecus|HandHeld|JAP|JPN|KLOTEKLAPPERS|KOR|NTSC|PAL|USA?)\b/i', $this->releaseName) ||
                preg_match('/\b(?:Dynarox|HAZARD|ITALIAN|KLB|KuDoS|LIGHTFORCE|MiRiBS|POPSTATiON|(PLAY)?ASiA|PSN|PSX2?PSP|SUXXORS|UMD(RIP)?|YARR)\b/i', $this->releaseName) ||
                preg_match('/\b(?:CSO|ISO)\b|\-HR|[._-](?:v\d+\.\d+)|\.(PSP)$/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PSP;

                return true;
            }
        }

        return false;
    }

    public function isGamePSVita(): bool
    {
        // First check if the release name suggests PlayStation Vita content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:PS ?Vita|PlayStation\s+Vita|PSV)|\b(?:PSVita|PSVITA)\b|[\._-](?:PSV|Vita)[\._-]/i', $this->releaseName)) {
            // Verify with region codes, game-specific markers, or known PS Vita release groups
            if (preg_match('/\b(?:ANTiDOTE|APATHY|Caravan|DUPLEX|DLC|EUR?|GAME|GOTY|GRiDLOCK|iNSOMNi|JAP|JPN|KONDIOS|MULTi|NTSC|PAL|PSN|SPLiT|STRiKE|USA?|VENOM|VPK)\b/i', $this->releaseName) ||
                preg_match('/\b(?:3\.60|3\.65|3\.68|Vitamin|NoNpDrm|MaiDump|UNDUB|PCSE\d{5}|PCSB\d{5}|PCSG\d{5}|PCSH\d{5})\b/i', $this->releaseName) ||
                preg_match('/\[PSVita\]|\(PSV\)|\.PSV$|Vita[._-]?(ROM|GAME|ISO)/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PSVITA;

                return true;
            }
        }

        return false;
    }

    public function isGameWiiWare(): bool
    {
        // First check if the release name suggests WiiWare content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:WiiWare|Wii\s+Ware)|\b(?:WiiWare)\b|[\._-]Wii[\._-]?(?:Ware)|(?:Console|DLC|VC)[._ -]WII|WII[._ -](?:Console|DLC|VC)|WII[._ -].+(?:Console|DLC|VC)|(?:Console|DLC|VC).+[._ -]WII/i', $this->releaseName)) {
            // Verify with region codes, game-specific markers, or known WiiWare release groups
            if (preg_match('/\b(?:PROPER|READNFO|UPDATE|REPACK|WiiERD|DNi|JAP|JPN|USA?|EUR?|PAL|NTSC|iNSOMNi|MULTi|LOADER|VENOM|WBFS|WII\d+|NRP|WWII|VORTEX|DiSONiK|DNi|DRYB)\b/i', $this->releaseName) ||
                preg_match('/\b(?:VC|Virtual[._ -]Console|WiiShop|Shop[._ -]Channel|WAD|IOS\d+|eShop)\b/i', $this->releaseName) ||
                preg_match('/\[Wii\]|\(Wii\)|Wii\.Point|NintendoWare|CLASSIC/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_WIIWARE;

                return true;
            }
        }

        return false;
    }

    public function isGameWiiU(): bool
    {
        // First check if the release name suggests Wii U content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Wii\s*U|WiiU)|\b(?:WiiU)\b|[\._-]WiiU[\._-]|Nintendo[\._-]WiiU/i', $this->releaseName)) {
            // Verify with region codes, game-specific markers, or known Wii U release groups
            if (preg_match('/\b(?:ANTiDOTE|APATHY|ALMoST|AMBITION|Allstars|BAHAMUT|BiOSHOCK|Caravan|CLiiCHE|DMZ|DNi|DRYB|DLC|EUR?|GAME|HaZMaT|iCON|JPN|JAP|KOR|LaKiTu|LoCAL|LOADER|MARVEL|MULTi|NAGGERS|OneUp|NTSC|PAL|PLAYME|PONS|PROMiNENT|ProCiSiON|PROPER|QwiiF|RANT|REV0|Scrubbed|SUNSHiNE|SUSHi|TMD|USA?|VORTEX|ZARD|ZER0)\b/i', $this->releaseName) ||
                preg_match('/\b(?:WUD|WUX|WUP-[A-Z0-9]+|eShop|LOADIINE|WUDUMP|CONSOLE-WiiU|WiiU-\w+|UPDATE|vWii|WiiVC|Virtual\s*Console)\b/i', $this->releaseName) ||
                preg_match('/\[WiiU\]|\(WiiU\)|Wii\.U|Nintendo\.WiiU|15GB\+?|RETAiL|Loadiine|CFW|CEMU|NUS|Installable/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_WIIU;

                return true;
            }
        }

        // Fallback to original pattern matching for better backward compatibility
        switch (true) {
            case preg_match('/[._ -](Allstars|BiOSHOCK|dumpTruck|DNi|iCON|JAP|NTSC|PAL|ProCiSiON|PROPER|RANT|REV0|SUNSHiNE|SUSHi|TMD|USA?)$/i', $this->releaseName):
            case preg_match('/[._ -](APATHY|BAHAMUT|DMZ|ERD|GAME|JPN|LoCAL|MULTi|NAGGERS|OneUp|PLAYME|PONS|Scrubbed|VORTEX|ZARD|ZER0)$/i', $this->releaseName):
            case preg_match('/[._ -](ALMoST|AMBITION|Caravan|CLiiCHE|DRYB|HaZMaT|KOR|LOADER|MARVEL|PROMiNENT|LaKiTu|LOCAL|QwiiF|RANT)$/i', $this->releaseName):
                $this->tmpCat = Category::GAME_WIIU;

                return true;
            default:
                return false;
        }
    }

    public function isGameWii(): bool
    {
        // First check if the release name suggests Nintendo Wii content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Wii|Nintendo\s+Wii)|\b(?:Wii)\b|[\._-]Wii[\._-]|Nintendo[\._-]Wii/i', $this->releaseName)) {
            // Verify with region codes, game-specific markers, or known Wii release groups
            if (preg_match('/\b(?:ANTiDOTE|APATHY|ALMoST|AMBITION|Allstars|BAHAMUT|BiOSHOCK|Caravan|CLiiCHE|DMZ|DNi|DRYB|EUR?|GAME|GCN|GCP|HaZMaT|iCON|JAP|JPN|KOR|LaKiTu|LoCAL|LOADER|MARVEL|MULTi|NAGGERS|OneUp|NTSC|PAL|PLAYME|PONS|PROMiNENT|ProCiSiON|PROPER|QwiiF|RANT|REV0|Scrubbed|SUNSHiNE|SUSHi|TMD|USA?|VORTEX|WBFS|WiiERD|ZARD|ZER0)\b/i', $this->releaseName) ||
                preg_match('/\b(?:ISO|WBFS|CSO|NKit|RVZ|NAND|WAD|IOS\d+|cIOS|MODCHIP|Homebrew|DOLPHIN|vWii)\b/i', $this->releaseName) ||
                preg_match('/\[Wii\]|\(Wii\)|Nintendo\.Wii|Wii\.Game|RVZ-[A-Z0-9]+|WII-\w+|READNFO|WiiGamerZ|Wii-Backup/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_WII;

                return true;
            }
        }

        // Fallback to original pattern matching for backward compatibility
        switch (true) {
            case preg_match('/[._ -](Allstars|BiOSHOCK|dumpTruck|DNi|iCON|JAP|NTSC|PAL|ProCiSiON|PROPER|RANT|REV0|SUNSHiNE|SUSHi|TMD|USA?)/i', $this->releaseName):
            case preg_match('/[._ -](APATHY|BAHAMUT|DMZ|ERD|GAME|JPN|LoCAL|MULTi|NAGGERS|OneUp|PLAYME|PONS|Scrubbed|VORTEX|ZARD|ZER0)/i', $this->releaseName):
            case preg_match('/[._ -](ALMoST|AMBITION|Caravan|CLiiCHE|DRYB|HaZMaT|KOR|LOADER|MARVEL|PROMiNENT|LaKiTu|LOCAL|QwiiF|RANT)/i', $this->releaseName):
                $this->tmpCat = Category::GAME_WII;

                return true;
            default:
                return false;
        }
    }

    public function isGameXBOX360DLC(): bool
    {
        // First check if the release name suggests Xbox 360 DLC content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:DLC|XBLA|Add[._ -]?On|Expansion|Content).*(?:Xbox360|XBOX360|X360)|\b(?:Xbox360|XBOX360|X360).*(?:DLC|XBLA|Add[._ -]?On|Expansion|Content)\b|[\._-](?:DLC|XBLA)[\._-]/i', $this->releaseName)) {
            // Verify with region codes, game-specific markers, or known Xbox 360 DLC release groups
            if (preg_match('/\b(?:COMPLEX|REPACK|READNFO|REGION|FREE|RGH|JTAG|ARCADE|MARKETPLACE|LIVE|XBLA|Games[._ -]On[._ -]Demand|GOD|FULL|iNT|JPN|JAP|RF|NTSC|PAL|Region[._ -]Free|USA?|ASIA|EUR?|KOR|WAVE\d+|XGD\d|SWAG|CCCLX|DAGGER)\b/i', $this->releaseName) ||
                preg_match('/\b(?:TU\d+|Patch|Update|v\d+\.\d+|Package|Addon|MAP[._ -]PACK|Character[._ -]Pack|Skin[._ -]Pack|Unlock|Premium|Season[._ -]Pass|Episode|Part|Pack\d+)\b/i', $this->releaseName) ||
                preg_match('/\[DLC\]|\(DLC\)|Xbox[._ -]360[._ -]DLC|MSPOINTS|Microsoft[._ -]Points|\d{4}[._ -]MS[._ -]Points|XBL[._ -]Arcade/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_XBOX360DLC;

                return true;
            }
        }

        // Check standalone XBLA releases
        if (preg_match('/\bXBLA[._ -](?!x360|xbox360)|\b(?:Xbox360|XBOX360|X360)[._ -]Arcade|\bXbox[._ -]LIVE[._ -]Arcade\b/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_XBOX360DLC;

            return true;
        }

        return false;
    }

    public function isGameXBOX360(): bool
    {
        // First check if the release name suggests Xbox 360 content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Xbox360|XBOX360|X360)|\b(?:Xbox360|XBOX360|X360)\b|[\._-](?:Xbox360|XBOX360|X360)[\._-]/i', $this->releaseName)) {
            // Verify with region codes, game-specific markers, or known Xbox 360 release groups
            if (preg_match('/\b(?:Allstars|ASiA|CCCLX|COMPLEX|DAGGER|GLoBAL|iMARS|JAP|JPN|MULTi|NTSC|PAL|REPACK|RRoD|RF|SWAG|USA?|REGION|FREE|WAVE\d+|XGD\d|SPARE|JTAG|iNT|FULL|MARVEL|GOD|SPARE)\b/i', $this->releaseName) ||
                preg_match('/\b(?:DAMNATION|GERMAN|GOTY|iTA|KINECT|MUX360|RANT|SPANISH|VATOS|XBOX360|WiiERD|XBLA|Region[._ -]Free|RGH|ISO|COMPLEX)\b/i', $this->releaseName) ||
                preg_match('/\[XBOX360\]|\(XBOX360\)|XBOX[._-]360[._-]|TU\d+|Patch|Update|v\d+\.\d+|\.(iso|xex|xbla)$/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_XBOX360;

                return true;
            }
        }

        // Check for X360 specific patterns for backward compatibility
        if (preg_match('/\bx360\b|[\._-]x360[\._-]/i', $this->releaseName)) {
            if (preg_match('/\b(?:Allstars|ASiA|CCCLX|COMPLEX|DAGGER|GLoBAL|iMARS|JAP|JPN|MULTi|NTSC|PAL|REPACK|RRoD|RF|SWAG|USA?)\b/i', $this->releaseName) ||
                preg_match('/\b(?:DAMNATION|GERMAN|GOTY|iNT|iTA|JTAG|KINECT|MARVEL|MUX360|RANT|SPARE|SPANISH|VATOS|XGD)\b/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_XBOX360;

                return true;
            }
        }

        return false;
    }

    public function isGameXBOXONE(): bool
    {
        // First check if the release name suggests Xbox One content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:XboxOne|XBOX\s*One|XBONE|XB1)|\b(?:XboxOne|XBOX\s*One|XBONE|XB1)\b|[\._-](?:XboxOne|XBOX\s*One|XBONE|XB1)[\._-]/i', $this->releaseName)) {
            // Verify with region codes, game-specific markers, or known Xbox One release groups
            if (preg_match('/\b(?:ANYiSO|AVENGED|CODEX|COMPLEX|DAGGER|DLCS|DODI|EUR?|FitGirl|FULLGAME|Googlecus|GOTY|iNLAWS|JAP|JPN|MULTi|NTSC|PAL|REPACK|RF|RSGTACTICS|SKIDROW|TiNYiSO|USA?|WaYsTeD|XBLA|XGD4)\b/i', $this->releaseName) ||
                preg_match('/\b(?:Enhanced\s?for\s?Xbox|Optimized\s?for\s?Series|SmartDelivery|Console\s?Exclusive|Xbox\s?Play\s?Anywhere|Game\s?Preview|Game\s?Pass|RETAIL|INTERNAL|REDEVEiL|HOODLUM|BALiSTIC)\b/i', $this->releaseName) ||
                preg_match('/\b(?:CUSA\d{5}|Update\s?[0-9.]+|Season\s?Pass|Premium\s?Edition|Definitive\s?Edition|Complete\s?Edition|Deluxe\s?Edition|READNFO|ISO|PKG|NSP)\b/i', $this->releaseName) ||
                preg_match('/\[XBOX\s?(ONE|1)\]|\(XBOX\s?(ONE|1)\)|\d{5}\.[0-9]{2}[\._-]|Microsoft[\._-]Store/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_XBOXONE;

                return true;
            }
        }

        // Check for Xbox Series S|X content which uses same category
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Xbox\s?Series[._ -]?[SX]|XSX|XSS)|\b(?:Xbox\s?Series[._ -]?[SX]|XSX|XSS)\b|[\._-](?:Xbox\s?Series[._ -]?[SX]|XSX|XSS)[\._-]/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_XBOXONE;

            return true;
        }

        // Legacy detection for backward compatibility
        if (preg_match('/XBOXONE|XBOX\.ONE|XBOX[._-]?ONE/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_XBOXONE;

            return true;
        }

        return false;
    }

    public function isGameXBOX(): bool
    {
        // First check if the release name suggests original Xbox content (while excluding 360/One/Series)
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:XBOX|X-BOX)(?!(?:360|ONE|Series|One))\b|\b(?:XBOX)\b(?!(?:360|ONE|Series|One))|[\._-]XBOX[\._-](?!(?:360|ONE|Series|One))/i', $this->releaseName)) {
            // Verify with region codes, game-specific markers, or known original Xbox release groups
            if (preg_match('/\b(?:USA?|PAL|NTSC|JPN|JAP|RF|REGION|FREE|EUR?|ASiA|Allstars|iNT|MULTi|REPACK|PROPER|READNFO|DVD[59]?|RETAIL|ISO|RIP|GOTY|UNCUT|GERMAN|FRENCH|SPANiSH|iTALiAN|DUTCH|SWEDiSH|DANiSH|FiNNiSH|NORWEGIAN|RUSSiAN)\b/i', $this->releaseName) ||
                preg_match('/\b(?:XPG|ProjectX|DAGGER|STRANGE|SWAG|DEMONZ85|PROTOCOL|ICONCLAS|DNL|DRTL|RiNGERS|ORiGiNAL|Empire|Protocol|iMARS|GOTY|Caravan|PROPHETS|1TM|WaLMaRT|Eroticl1|LaKiTu|FLS)\b/i', $this->releaseName) ||
                preg_match('/\[XBOX\]|\(XBOX\)|XBOX[._-]([^3]|$)|\.(iso|xbe)$/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_XBOX;

                return true;
            }
        }

        // Legacy detection for backward compatibility, but with better exclusion of other Xbox platforms
        if (preg_match('/\bXBOX\b/i', $this->releaseName) &&
            ! preg_match('/\b(XBOX\s?360|XBOX\s?ONE|XBONE|XB1|Xbox\s?Series|XSX|XSS)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_XBOX;

            return true;
        }

        return false;
    }

    public function isGameOther(): bool
    {
        // First check if the release name suggests retro/other console content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:PS[1X]|PS2|SNES|NES|SEGA(?:\s+(?:Genesis|CD|Saturn|32X|Master\s+System))?|GB[AC]?|GameBoy(?:\s+(?:Advance|Color))?|Game\s*Boy(?:\s+(?:Advance|Color))?|Dreamcast|Saturn|Atari(?:\s+(?:Jaguar|2600|5200|7800|Lynx))?|3DO|Neo\s*Geo|N64|Nintendo\s*64|PCEngine|TurboGrafx|Intellivision|Colecovision)|\b(?:PS[1X]|PS2|SNES|NES|MAME|N64)\b|[\._-](?:PS[1X]|PS2|SNES|NES|N64)[\._-]/i', $this->releaseName)) {
            // Verify with region codes, game-specific markers, or known retro release patterns
            if (preg_match('/\b(?:EUR?|FR|GAME|HOL|ISO|JP|JPN|NL|NTSC|PAL|KS|USA?|ROMS?(et)?|ROM\s+Collection|RIP|Full\s+Set|Redump|No\s+Intro|TOSEC|GoodSet|EverDrive|Collection|Classics|Anthology|Trilogy|Compilation|Complete|Rev\s+[A-Z])\b/i', $this->releaseName) ||
                preg_match('/\b(?:BIOS|Beetle|RetroArch|Emulator|MultiDisc|Arcade|OpenEmu|RetroPie|Recalbox|Lakka|Batocera|Hyperspin|LaunchBox|MAME|Collection|Goodset|Trurip|Verified|ReDump|PROPER|iNTERNAL|Venom|Caravan|WRG)\b/i', $this->releaseName) ||
                preg_match('/\(([CP]|\d{2,})\)|\.(bin|chd|cue|gcm|gdi|iso|img|mdf|nrg|z64|v64|n64|md|smc|smd|fig|gb|gbc|gba|nes|sfc|gen)$/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_OTHER;

                return true;
            }
        }

        // Legacy detection for backward compatibility
        if (preg_match('/\b(PS(1|One|X)|PS2|PlayStation\s+(1|2|One)|SNES|Super\s+Nintendo|NES|Nintendo\s+Entertainment\s+System|SEGA\s+(GENESIS|CD|SATURN|32X)|GB([AC])?|GameBoy(\s+(Advance|Color))?|Game\s*Boy(\s+(Advance|Color))?|Dreamcast|SEGA\s+Saturn|Atari(\s+Jaguar)?|3DO|Neo\s*Geo|N64|Nintendo\s*64)\b/i', $this->releaseName) &&
            preg_match('/\b(EUR|FR|GAME|HOL|ISO|JP|JPN|NL|NTSC|PAL|KS|USA|ROMS?(et)?)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_OTHER;

            return true;
        }

        return false;
    }

    //	Music.

    public function isMusic(): bool
    {
        return match (true) {
            $this->isMusicVideo(), $this->isAudiobook(), $this->isMusicLossless(), $this->isMusicMP3(), $this->isMusicPodcast(),$this->isMusicOther() => true,
            default => false,
        };
    }

    public function isMusicForeign(): bool
    {
        // Skip processing if foreign categorization is disabled
        if (! $this->categorizeForeign) {
            return false;
        }

        // Organized language pattern with word boundaries
        // Full language names
        $fullLanguages = 'arabic|brazilian|bulgarian|cantonese|chinese|croatian|czech|danish|deutsch|dutch|estonian|'.
                        'flemish|finnish|french|german|greek|hebrew|hungarian|icelandic|indian|iranian|italian|'.
                        'japanese|korean|latin|latvian|lithuanian|macedonian|mandarin|nordic|norwegian|persian|'.
                        'polish|portuguese|romanian|russian|serbian|slovak|slovenian|spanish|spanisch|swedish|'.
                        'thai|turkish|ukrainian|vietnamese';

        // Common language codes and abbreviations
        $langCodes = 'ar|bg|bl|cs|cz|da|de|dk|el|es|et|fi|fr|ger|gr|heb|hr|hu|hun|is|it|ita|jp|jap|ko|kor|lt|lv|'.
                    'mk|nl|no|pl|pt|ro|rs|ru|se|sk|sl|sr|sv|th|tr|ua|vi|zh';

        // Italian with year pattern
        $italianWithYear = 'it(a|\s+19|\s+20\d\d)';

        // Combined pattern with improved word boundaries
        if (preg_match('/(?:^|[\s\.\-_])(?:'.$fullLanguages.'|'.$langCodes.'|'.$italianWithYear.')(?:$|[\s\.\-_])/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_FOREIGN;

            return true;
        }

        return false;
    }

    public function isAudiobook(): bool
    {
        // First check if the release name suggests audiobook content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Audiobook|Audio\s*Book|Talking\s*Book|ABEE|Audible)|\b(?:Audiobook|Audio\s*Book|A\s*Book)\b|[\._-](?:Audiobook|AB)[\._-]/i', $this->releaseName)) {
            // Verify with audiobook-specific markers
            if (preg_match('/\b(?:Unabridged|Abridged|Narrated|Narrator|Chapter|MP3|M4A|M4B|AAC|Read\s+By|Reader|Retail|Complete|SAGA|Tantor|Blackstone|Brilliance|GraphicAudio|Macmillan|Penguin|Random\s+House|Hachette|Harper|Podium|Audible|Originals)\b/i', $this->releaseName) ||
                preg_match('/\d+\s*CDs|\d+\s*Hours|\d+\s*Hrs|Spoken\s+Word|Audiofy|\b(?:MPEG|FLR|SPX|CBR|DAISY|UB|GAB|ATBR)\b/i', $this->releaseName) ||
                preg_match('/\.(mp3|m4a|m4b|aac|flac|ogg|wma)$/i', $this->releaseName)) {
                $this->tmpCat = Category::MUSIC_AUDIOBOOK;

                return true;
            }
        }

        // Check for known audiobook publishing patterns
        if (preg_match('/(?:[\(_\[])(?:Audiobook|AB|Unabridged)(?:[\)_\]])/i', $this->releaseName) ||
            preg_match('/Read\s+By\s+[A-Z][a-z]+\s+[A-Z][a-z]+/i', $this->releaseName) ||
            preg_match('/\b(?:Audiobook|AB)\s+(?:Collection|Series|Compilation)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_AUDIOBOOK;

            return true;
        }

        // Legacy detection for backward compatibility
        if (preg_match('/(Audiobook|Audio.?Book)/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_AUDIOBOOK;

            return true;
        }

        return false;
    }

    public function isMusicVideo(): bool
    {
        // First check if the release name suggests music video content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Music\s*Video|Concert|Live\s*Show|Tour|Festival|Performan[cs]e|MV|MTV)|\b(?:MVID|MVid)\b|[\._-](?:MV|MVID)[\._-]/i', $this->releaseName)) {
            // Verify with music video specific markers
            if (preg_match('/\b(?:720p|1080[pi]|2160p|BDRip|BluRay|DVDRip|HDTV|WebRip|WEB-DL|x264|x265|h264|h265|XviD|AVC|HEVC|AMVC|MBLURAY)\b/i', $this->releaseName) ||
                preg_match('/\b(?:Live|Unplugged|Acoustic|World\s*Tour|in\s*Concert|Official\s*Video|Music\s*Collection|Bootleg|Remastered|Directors\s*Cut|Documentary|Recorded\s*Live)\b/i', $this->releaseName) ||
                preg_match('/\.(mkv|mp4|avi|ts|m2ts|mpg|mpeg|mov|wmv|vob|m4v)$/i', $this->releaseName)) {
                if ($this->isMusicForeign()) {
                    return true;
                }
                $this->tmpCat = Category::MUSIC_VIDEO;

                return true;
            }
        }

        // Artist/band pattern with year and video format
        if (preg_match('/^[A-Z0-9][A-Za-z0-9\.\s\&\'\(\)\-]+\s+\-\s+[A-Z0-9][A-Za-z0-9\.\s\&\'\(\)\-]+(\s+(19|20)\d\d)?\s+\d+p\b/i', $this->releaseName) ||
            preg_match('/^[A-Z0-9][A-Za-z0-9\.\s\&\'\(\)\-]+\s+\-\s+[A-Z0-9][A-Za-z0-9\.\s\&\'\(\)\-]+\s+\[?(720p|1080[pi]|2160p|Bluray|x264|x265)\]?/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_VIDEO;

            return true;
        }

        // Check for common music video release naming patterns
        if (preg_match('/\b(?:JAM|CLASSiC|DiRFiX|MBLURAY|NTSC|PAL|REMASTERED|UMV|VEVO|UHDTV|JUSTiCE|DTS|DTSDD|DTSHD|MBluRay)\b.*(?:720p|1080[pi]|2160p|x264|x265|h264|h265)/i', $this->releaseName) &&
            ! preg_match('/\b(?:SEASON|EPISODE|S\d+E\d+|HDTV|TV[\s\.\-_]SHOW)\b/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_VIDEO;

            return true;
        }

        // Legacy detection for backward compatibility
        if (preg_match('/(720P|x264)\-(19|20)\d\d\-[a-z0-9]{1,12}/i', $this->releaseName) ||
            preg_match('/[a-z0-9]{1,12}-(19|20)\d\d-(720P|x264)/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_VIDEO;

            return true;
        }

        return false;
    }

    public function isMusicLossless(): bool
    {
        // First check if the release name suggests lossless audio content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:FLAC|APE|WAV|ALAC|DSD|DSF|AIFF|PCM|Lossless)|\b(?:FLAC|APE|WAV|ALAC|DSD|DSF|AIFF|PCM)\b|[\._-](?:FLAC|APE|WAV|ALAC|DSD|AIFF)[\._-]/i', $this->releaseName)) {
            // Verify with lossless-specific markers
            if (preg_match('/\b(?:24[Bb]it|96kHz|192kHz|Hi[- ]?Res|HD[- ]?Tracks|Vinyl[- ]?Rip|CD[- ]?Rip|WEB[- ]?Rip|Decca|Deutsche[- ]?Grammophon|ECM|Nonesuch|HDtracks|7Digital|Qobuz|Tidal|Master[- ]?Quality|MQA|SACD)\b/i', $this->releaseName) ||
                preg_match('/\b(?:Bowers[- ]?&[- ]?Wilkins|B&W|Society[- ]?of[- ]?Sound|Blue[- ]?Coast|Reference[- ]?Recordings|MA[- ]?Recordings|2L|ATMA|BIS|Channel[- ]?Classics|Harmonia[- ]?Mundi)\b/i', $this->releaseName) ||
                preg_match('/\.(flac|ape|wav|aiff|dsf|dff|m4a|tak)$/i', $this->releaseName)) {

                if ($this->isMusicForeign()) {
                    return true;
                }
                $this->tmpCat = Category::MUSIC_LOSSLESS;

                return true;
            }
        }

        // Check for artist-title format with FLAC keywords
        if (preg_match('/^[a-zA-Z0-9_]+(_|\s+)-(_|\s+)[a-zA-Z0-9_\s]+_(19|20)\d\d.*-FLAC/i', $this->releaseName) ||
            preg_match('/_(19|20)\d\d.*FLAC.*_\d{2}_/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_LOSSLESS;

            return true;
        }

        // Check for double FLAC patterns (common in some releases)
        if (preg_match('/-FLAC-[a-zA-Z0-9]+-FLAC/i', $this->releaseName) ||
            preg_match('/-FLAC.*FLAC_/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_LOSSLESS;

            return true;
        }

        // Check for specific FLAC release patterns
        if (preg_match('/\[(19|20)\d\d\][._ -]\[FLAC\]|([\(\[])flac([\)\]])|FLAC\-(19|20)\d\d\-[a-z0-9]{1,12}|\.flac"|(19|20)\d\d\sFLAC|[._ -]FLAC.+(19|20)\d\d[._ -]| FLAC$/i', $this->releaseName) ||
            preg_match('/\d{3,4}kbps[._ -]FLAC|\[FLAC\]|\(FLAC\)|FLACME|FLAC[._ -]\d{3,4}(kbps)?|WEB[._ -]FLAC/i', $this->releaseName)) {

            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_LOSSLESS;

            return true;
        }

        // Check for other lossless formats
        if (preg_match('/\b(?:APE|Monkey\'s[._ -]Audio|WavPack|WV|TAK|TTA|ALAC|Apple[._ -]Lossless)\b|\.(ape|wv|tak|tta)$/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_LOSSLESS;

            return true;
        }

        // Check for lossless release groups and scene tags
        if (preg_match('/\b(?:DYNAMIC|EOS|TFM|DFA|CODEC|PERFECT|ENSLAVE|YARD|FLACKED|DEMOGORGE|PmK|DiTCH|DATA-FLACx)\b/i', $this->releaseName) &&
            ! preg_match('/\b(?:mp3|320|256|192|128|CBR|VBR)\b/i', $this->releaseName)) {

            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_LOSSLESS;

            return true;
        }

        return false;
    }

    public function isMusicMP3(): bool
    {
        // First check if the release name suggests MP3 audio content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:MP3|320kbps|256kbps|192kbps|128kbps|CBR|VBR)|\b(?:MP3)\b|[\._-](?:MP3)[\._-]|\.mp3$/i', $this->releaseName)) {
            // Verify with MP3-specific bitrate markers
            if (preg_match('/\b(?:320|256|192|128)[._-]?kbps|\b(?:320|256|192|128)[._-]?K|\((?:320|256|192|128)\)|\[(?:320|256|192|128)\]|(?:320|256|192|128)[._-]?CBR|V0|V2|VBR|MP3\s*\-\s*\d{3}kbps/i', $this->releaseName) ||
                preg_match('/\b(?:CD[._-]?Rip|Web[._-]?Rip|WEB|iTunes[._-]?(Plus|Match|Rip)?|AmazonRip|Spotify[._-]?Rip|M3U|ID3|EDM|Dance|House|Bootleg|Remix|MPEG|Exclu|Proper|Repack|RETAIL)\b/i', $this->releaseName) ||
                preg_match('/\.(m3u|mp3)"|rip(?:192|256|320)|[._-]FM[._-].+MP3/i', $this->releaseName)) {

                if ($this->isMusicForeign()) {
                    return true;
                }
                $this->tmpCat = Category::MUSIC_MP3;

                return true;
            }
        }

        // Check for MP3 scene release patterns
        if (preg_match('/^[a-zA-Z0-9]{1,12}[._-](19|20)\d\d[._-][a-zA-Z0-9]{1,12}$|[a-z0-9]{1,12}\-(19|20)\d\d\-[a-z0-9]{1,12}/i', $this->releaseName) ||
            preg_match('/\b(?:DEMONiC|SiRE|SPiKE|MiNDTRiP|AMRC|btl|TrT|RKS|UPE|hbZ|HB|UMT|TBM|VAG|MAHOU|PMSF|RNS|SPK)\b[._-](?!FLAC|APE|WAV|ALAC|DSD)/i', $this->releaseName)) {

            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_MP3;

            return true;
        }

        // Check for MP3 source indicators with year patterns
        if (preg_match('/[._-](?:CDR|SBD|WEB|SAT|FM|DAB)[._-]+(19|20)\d\d([._-]|$)|[._-](19|20)\d\d[._-]+(?:CDR|SBD|WEB|SAT|FM|DAB)([._-]|$)/i', $this->releaseName) ||
            preg_match('/\-web-(19|20)\d\d([\.\s$])|[._-](SAT|SBD|WEB)[._-]+(19|20)\d\d([._-]|$)|[._-](19|20)\d\d[._-]+(?:SAT|WEB)([._-]|$)/i', $this->releaseName)) {

            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_MP3;

            return true;
        }

        // Check for CD collection and album indicators
        if (preg_match('/\s\dCDs|FIH\_INT|\(320\)\.|\-\((Bootleg|Promo)\)|\-\sMP3\s(19|20)\d\d|\(vbr\)/i', $this->releaseName) ||
            preg_match('/\s(19|20)\d\d\s([a-z0-9]{3}|[a-z]{2,})$|\-(19|20)\d\d\-(C4|MTD)([\s\.])|NMR\s\d{2,3}\skbps| MP3$/i', $this->releaseName)) {

            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_MP3;

            return true;
        }

        // Check for MP3 recording specifications
        if (preg_match('/[\.\-\(\[_ ]\d{2,3}k[\.\-\)\]_ ]|\((192|256|320)\)|(320|cd|eac|vbr)[._-]+mp3|(cd|eac|mp3|vbr)[._-]+320/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_MP3;

            return true;
        }

        return false;
    }

    public function isMusicOther(): bool
    {
        // First check for various compilation, VA, and multi-artist indicators
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Compilation|Various[._ -]Artists|OST|Soundtrack|B-Sides|Greatest[._ -]Hits|Anthology)|\b(?:VA|V\.A|Bonus[._ -]Track|Discography|Box[._ -]Set)\b|[\._-](?:VA|OST|Bootleg)[\._-]/i', $this->releaseName)) {
            if (! $this->isMusicForeign()) {
                $this->tmpCat = Category::MUSIC_OTHER;
            }

            return true;
        }

        // Check for specific music formats/releases not covered by MP3 or Lossless categories
        if (preg_match('/(?:\d)[._ -](?:CD|Albums|LP)[._ -](?:Set|Compilation)|CD[._ -](Collection|Box|SET)|(\d)-?CD[._ -]|Disc[._ -]\d+[._ -](?:of|OF)[._ -]\d+/i', $this->releaseName) ||
            preg_match('/Vinyl[._ -](?:24[._ -]96|2496|Collection|RIP)|WEB[._ -](?:Single|Album)|EP[._ -]\d{4}|\bEP\b.+(?:19|20)\d\d|Live[._ -](?:at|At|@)/i', $this->releaseName) ||
            preg_match('/\b(?:Bootleg|Remastered|Anniversary[._ -]Edition|Deluxe[._ -]Edition|Special[._ -]Edition|Collectors[._ -]Edition)\b/i', $this->releaseName)) {

            if (! $this->isMusicForeign()) {
                $this->tmpCat = Category::MUSIC_OTHER;
            }

            return true;
        }

        // Check for labels, music series, and DJ mixes
        if (preg_match('/\b(?:Ministry[._ -]of[._ -]Sound|Hed[._ -]Kandi|Cream|Fabric[._ -]Live|Back[._ -]?2[._ -]?Back|Ultra[._ -]Music|Euphoria|Sensual[._ -]Chill|Top[._ -]Hits)\b/i', $this->releaseName) ||
            preg_match('/\b(?:DJ[._ -]Mix|Mixed[._ -]By|Tiesto[._ -]Club|D\.O\.M|NMR|pure_fm|Radio[._ -]Show|Reggaeton|Club[._ -]Hits|Summer[._ -](?:Set|Mix))\b/i', $this->releaseName)) {

            if (! $this->isMusicForeign()) {
                $this->tmpCat = Category::MUSIC_OTHER;
            }

            return true;
        }

        // Original patterns for backward compatibility
        if (preg_match('/(19|20)\d\d\-(C4)$|[._ -]\d?CD[._ -](19|20)\d\d|\(\d\-?CD\)|\-\dcd\-|\d[._ -]Albums|Albums.+(EP)|Bonus.+Tracks|Box.+?CD.+SET|Discography|D\.O\.M|Greatest\sSongs|Live.+(Bootleg|Remastered)|Music.+Vol|([\(\[\s])NMR([\)\]\s])|Promo.+CD|Reggaeton|Tiesto.+Club|Vinyl\s2496|\WV\.A\.|^\(VA\s|^VA[._ -]/i', $this->releaseName)) {
            if (! $this->isMusicForeign()) {
                $this->tmpCat = Category::MUSIC_OTHER;
            }

            return true;
        }

        // Format/edition patterns for backward compatibility
        if (preg_match('/\(pure_fm\)|-+\(?(2lp|cd[ms]([\-_ .][a-z]{2})?|cover|ep|ltd_ed|mix|original|ost|.*?(edit(ion)?|remix(es)?|vinyl)|web)\)?-+((19|20)\d\d|you$)/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_OTHER;

            return true;
        }

        return false;
    }

    public function isMusicPodcast(): bool
    {
        // First check if the release name explicitly indicates podcast content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Podcast|Pod[._ -]?cast|Pod[._ -]Show)|\b(?:Podcast)\b|[\._-](?:POD)[\._-]/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_PODCAST;

            return true;
        }

        // Check for common podcast naming patterns with episode numbers/dates
        if (preg_match('/(?:EP?[._ -]?\d+|Episode[._ -]?\d+|S\d+[._ -]?EP?[._ -]?\d+|[._ -](?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[._ -]\d{1,2}[._ -]\d{4})[._ -](?:Interview|Show|Talk|Discussion|Podcast)/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_PODCAST;

            return true;
        }

        // Check for popular podcast networks/distributors
        if (preg_match('/\b(?:NPR|BBC[._ -]Sounds|Gimlet|Wondery|Stitcher|iHeart[._ -]?Radio|Spotify[._ -]?Original|Audible[._ -]?Original|Joe[._ -]Rogan|RadioLab|This[._ -]American[._ -]Life|Serial|Pod[._ -]?Save[._ -]America|The[._ -]Daily)\b/i', $this->releaseName) &&
            preg_match('/\b(?:Podcast|Episode|EP?[._ -]?\d+|Show|Interview|Discussion|Talk)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_PODCAST;

            return true;
        }

        // Check for podcast recording formats and encoding descriptors
        if (preg_match('/\b(?:MP3|AAC|M4A|OPUS|WAV|FLAC)[._ -](?:Podcast|Talk[._ -]Show)\b/i', $this->releaseName) ||
            preg_match('/\b(?:Podcast|Talk[._ -]Show)[._ -](?:MP3|AAC|M4A|OPUS|WAV|FLAC)\b/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_PODCAST;

            return true;
        }

        // Legacy pattern for backward compatibility
        if (preg_match('/podcast/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_PODCAST;

            return true;
        }

        return false;
    }

    //	Books.

    public function isBook(): bool
    {
        return match (true) {
            $this->isComic(), $this->isTechnicalBook(), $this->isMagazine(), $this->isBookOther(), $this->isEBook() => true,
            default => false,
        };
    }

    public function isBookForeign(): bool
    {
        // Skip processing if foreign categorization is disabled
        if (! $this->categorizeForeign) {
            return false;
        }

        // Full language names
        $fullLanguages = 'arabic|brazilian|bulgarian|cantonese|chinese|croatian|czech|danish|deutsch|dutch|estonian|'.
                         'finnish|flemish|french|german|greek|hebrew|hungarian|icelandic|italian|japanese|korean|'.
                         'latin|mandarin|nordic|norwegian|polish|portuguese|romanian|russian|serbian|slovenian|'.
                         'spanish|swedish|thai|turkish|ukrainian|vietnamese';

        // Common language codes and abbreviations
        $langCodes = 'ar|bg|cn|cs|cz|da|de|dk|el|es|et|fi|fr|ger|gr|heb|hr|hu|is|it|ita|jp|kr|lt|lv|'.
                    'nl|no|pl|pt|ro|rs|ru|se|sk|sl|spa|swe|tr|tw|ua|vn';

        // Combined pattern with improved word boundaries
        if (preg_match('/(?:^|[\s\.\-_])(?:'.$fullLanguages.'|'.$langCodes.')(?:$|[\s\.\-_])/i', $this->releaseName)) {
            $this->tmpCat = Category::BOOKS_FOREIGN;

            return true;
        }

        // Check for language indicators in common ebook formats
        if (preg_match('/\b(?:'.$fullLanguages.')\b.*?(?:epub|mobi|pdf|azw3|fb2|cbr|cbz|djvu)/i', $this->releaseName) ||
            preg_match('/(?:epub|mobi|pdf|azw3|fb2|cbr|cbz|djvu).*?\b(?:'.$fullLanguages.')\b/i', $this->releaseName)) {
            $this->tmpCat = Category::BOOKS_FOREIGN;

            return true;
        }

        // Legacy pattern for backward compatibility
        if (preg_match('/[ \-\._](brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|ita|latin|mandarin|nordic|norwegian|polish|portuguese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)[._ -]/i', $this->releaseName)) {
            $this->tmpCat = Category::BOOKS_FOREIGN;

            return true;
        }

        return false;
    }

    public function isComic(): bool
    {
        // Check for comic file formats and common identifiers
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:CBR|CBZ|Comics?|Comic[._ -]Book|Graphic[._ -]Novel)|\b(?:CBR|CBZ|C2C)\b|\.(?:cbr|cbz)$|[\(\[](?:c2c|cbr|cbz)[\)\]]/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_COMICS;
            }

            return true;
        }

        // Check for popular comic publishers and imprints
        if (preg_match('/\b(?:Marvel|DC[._ -]Comics|Image[._ -]Comics|Dark[._ -]Horse|IDW|Vertigo|Wildstorm|Dynamite|Valiant|Archie|Top[._ -]Cow|Boom[._ -]Studios|Oni[._ -]Press)\b/i', $this->releaseName) &&
            preg_match('/\b(?:Comics?|Annual|Special|Issue|Vol|Volume|Chapter|No\.\d+|\#\d+|TPB|Trade[._ -]Paperback)\b/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_COMICS;
            }

            return true;
        }

        // Check for manga, manhwa, and other international comics
        if (preg_match('/\b(?:Manga|Manhwa|Manhua|Webtoon|Doujinshi|Tankobon|Weekly[._ -]Jump|Shonen|Shojo|Seinen|Josei)\b/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_COMICS;
            }

            return true;
        }

        // Check for series naming patterns and digital release indicators
        if (preg_match('/(?:Comic[._ -]Collection|TPB|Digital[._ -](?:Comic|Edition)|(?:Complete|Collected)[._ -](?:Series|Edition|Works)|(?:Omnibus|Compendium))/i', $this->releaseName) ||
            preg_match('/\b(?:DC[._ -](?:Adventures|Universe)|Total[._ -]Marvel|Digital[._ -](?:Son|Zone)|Covers[._ -]Digital)\b/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_COMICS;
            }

            return true;
        }

        // Legacy pattern for backward compatibility
        if (preg_match('/[\. ](cbr|cbz)|[\( ]c2c|cbr|cbz[\) ]|comix|^\(comic|[\.\-_\(\[ ]comics?[._ -]|comic.+book|covers.+digital|DC.+(Adventures|Universe)|digital.+(son|zone)|Graphic.+Novel|[\.\-_h ]manga|Total[._ -]Marvel/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_COMICS;
            }

            return true;
        }

        return false;
    }

    public function isTechnicalBook(): bool
    {
        // Publishers and imprints
        $publishers = 'Apress|Addison[._ -]Wesley|AK[._ -]Peters|Birkhauser|Cengage|CRC[._ -]Press|Focal[._ -]Press|'.
                     'For[._ -]Dummies|Head[._ -]First|Manning|MIT[._ -]Press|Morgan[._ -]Kaufmann|No[._ -]Starch|OReilly|'.
                     'Packt|Peachpit|Pragmatic|Prentice[._ -]Hall|Que|Sams|Springer|Sybex|Syngress|Vieweg|Wiley|Wrox';

        // Technical subjects and disciplines
        $subjects = 'Algorithms|Analysis|Algebra|Architecture|Artificial[._ -]Intelligence|Assembly|Blockchain|'.
                   'Calculus|Chemistry|Circuits|Computer[._ -]Science|Cryptography|Cyber[._ -]Security|Data[._ -]Mining|'.
                   'Data[._ -]Science|Database|Deep[._ -]Learning|DevOps|Electronics|Engineer(ing|s)|'.
                   'Hacking|Information[._ -]Security|Linear[._ -]Algebra|Machine[._ -]Learning|Mathematics|'.
                   'Network(ing|s)|Physics|Programming|Quantum|Robotics|Security|Statistics|'.
                   'System[._ -]Administration|Web[._ -]Development';

        // Programming languages and frameworks
        $programming = 'Ajax|Angular(JS)?|Assembly|AWS|Azure|Bash|C(\+\+|#)?|CSS|Django|Docker|Express|'.
                      'Flutter|GCP|Git|Go(lang)?|GraphQL|HTML|Java(Script)?|jQuery|JSON|Kotlin|Kubernetes|'.
                      'Laravel|Linux|MATLAB|Node(js)?|Objective[._ -]C|Perl|PHP|PowerShell|Python|React(JS)?|'.
                      'Ruby(\s+on\s+Rails)?|Rust|Scala|Shell|Spring|SQL|Swift|Terraform|TypeScript|Vue(js)?|XML|YAML';

        // Software and platforms
        $software = 'Adobe|Android|AutoCAD|Blender|Dreamweaver|Excel|Firebase|GIMP|GitHub|Google[._ -]Cloud|'.
                   'Hadoop|Illustrator|InDesign|iOS|Kubernetes|LibreOffice|MATLAB|Microsoft[._ -](Office|Azure|SQL|Teams)|'.
                   'MongoDB|MySQL|Nginx|Office|Photoshop|PostgreSQL|PowerBI|PowerPoint|Redis|Salesforce|'.
                   'Tableau|Ubuntu|Unity|Unix|VMWare|VS[._ -]Code|Windows|WordPress';

        // Book types and formats
        $bookTypes = 'Beginner\'?s[._ -]Guide|Bible|Cookbook|Complete[._ -]Guide|Crash[._ -]Course|'.
                    'Definitive[._ -]Guide|Encyclopedia|Essential[._ -]Guide|Field[._ -]Guide|Guide[._ -](For|To)|'.
                    'Handbook|How[._ -]To|Introduction[._ -]To|Learn|Mastering|Practical|Professional|'.
                    'Quick[._ -]Start|Reference|Solutions|Textbook|Training|Tutorial|Understanding';

        // Combined pattern with word boundaries
        $technicalPattern = '/(?:^|[^a-zA-Z0-9])(?:'.
                            $publishers.'|'.
                            $subjects.'|'.
                            $programming.'|'.
                            $software.'|'.
                            $bookTypes.
                            ')(?:$|[^a-zA-Z0-9])/i';

        // Check if there's no match for technical book patterns
        if (! preg_match($technicalPattern, $this->releaseName)) {
            // Additional check for specific technical indicators
            if (! preg_match('/\b(?:Course|Certification|Exam|Tutorial|Workshop|Learning|Mastering|Programming)\b.*\b(?:Videos?|Tutorials?|Lectures?|Series|Courses?)\b/i', $this->releaseName)) {
                return false;
            }
        }

        // Check if it should be categorized as foreign instead
        if ($this->isBookForeign()) {
            return true;
        }

        // If we've reached here, categorize as technical book
        $this->tmpCat = Category::BOOKS_TECHNICAL;

        return true;
    }

    public function isMagazine(): bool
    {
        // Month/date patterns
        $months = 'January|February|March|April|May|June|July|August|September|October|November|December';
        $datePattern = '/[a-z\-\._ ][._ -]('.$months.')[._ -](\d{1,2},)?20\d\d[._ -]|^\(.+[ .]\d{1,2}[ .]20\d\d[ .].+\.scr/i';

        // Major magazine titles - general interest, fashion, lifestyle
        $majorTitles = 'Bloomberg|Cosmopolitan|Economist|Elle|Esquire|FHM|Forbes|Fortune|GQ|Hustler|'.
                      'Life|Maxim|Mens[._ -](Health|Fitness)|National[._ -]Geographic|Newsweek|'.
                      'New[._ -]Yorker|Penthouse|People|Playboy|Rolling[._ -]Stone|Time|Vanity[._ -]Fair|'.
                      'Vogue|Wired';

        // Tech, gaming and special interest magazines
        $techGaming = 'Android[._ -](Magazine|World)|Computer(world|active|bild)|Digital[._ -](Camera|Photography)|'.
                     'GameInformer|Game[._ -]?(Master|Markt|star|TM)|Maximum[._ -]PC|MacLife|MacWorld|'.
                     'PC[._ -](Format|Gamer|Magazine|World|Welt)|PCGames|Popular[._ -](Mechanics|Science)|'.
                     'T3|TechRadar|Web[._ -]Designer';

        // Home, lifestyle and hobby magazines
        $lifestyle = 'Architectural[._ -]Digest|Better[._ -]Homes[._ -]Gardens|Bon[._ -]Appetit|'.
                    'Brides|Car[._ -]and[._ -]Driver|Conde[._ -]Nast|Cook\'?s[._ -]Illustrated|'.
                    'Gardening|Golf[._ -]Digest|Good[._ -]Housekeeping|GuitarPlayer|Martha[._ -]Stewart|'.
                    'Motor[._ -]Trend|Mountain[._ -]Bike|Outdoor[._ -]Life|Photography|Readers[._ -]Digest|'.
                    'Road[._ -]and[._ -]Track|Runner\'?s[._ -]World|Top[._ -]Gear';

        // News and current affairs magazines
        $news = 'Atlantic|Bulletin|Daily[._ -](Mail|Express|Mirror|Star|Telegraph)|'.
               'Economist|Guardian|La[._ -](Republica|Stampa)|Le[._ -](Monde|Figaro|Temps)|'.
               'Newsweek|New[._ -]Statesman|NY[._ -]Times|Spectator|Times|Washington[._ -]Post';

        // Magazine identifiers and file formats
        $identifiers = 'Catalogue|Digest|e?Magazin(es?)?|Journal|Newspaper|Periodical|TruePDF|Weekly|Zeitung';

        // Modern digital magazine formats and platforms
        $digital = 'Digital[._ -](Edition|Copy|Issue|Magazine|Subscription)|e[._ -]Magazine|Interactive[._ -]Issue|'.
                  'iPad[._ -]Magazine|Zinio|Magzter|PressReader|Readly';

        // Legacy magazine titles from original pattern
        $legacyTitles = 'Allehanda|Club|Computer([a-z0-9]+)?|Connect \d+|Corriere|ct|Diario|Digit(al)?|FHM|'.
                       'Gadgets|Galileo|Glam|Infosat|Inked|Instyle|io|Kicker|Liberation|New Scientist|NGV|'.
                       'Nuts|Pictorial|Popular|Professional|Reise|Sette(tv)?|Springer|Stuff|Studentlitteratur|'.
                       'Tatler|Vegetarian|Vegetable|Videomarkt|XXX|Brady(.+)?Games|Catalog|Columbus.+Dispatch|'.
                       'Correspondenten|Corriere[._ -]Della[._ -]Sera|Dagbladet|Digital[._ -]Guide|Eload ?24|'.
                       'ExtraTime|Fatto[._ -]Quotidiano|Flight[._ -](International|Journal)|Finanzwoche|'.
                       'France.+Football|Foto.+Video|Gazzetta|Globe[._ -]And[._ -]Mail|Guitar|Heimkino|'.
                       'Mac(life|welt)|Marie.+Claire|Motocross|Motorcycle|MusikWoche|PC(Games|Go|Tip)|'.
                       'Photograph(er|ic)|Posten|Quotidiano|(Golf|Readers?).+Digest|SFX[._ -]UK|Recipe(.+Guide|s)|'.
                       'SkyNews|Sport[._ -]?Week|Strategy.+Guide|TabletPC|Tattoo[._ -]Life|The[._ -]Guardian|'.
                       'Tageszeitung|Tid(bits|ning)|Total[._ -]Guitar|Travel[._ -]Guides?|Tribune[._ -]De[._ -]|'.
                       'US[._ -]Weekly|USA[._ -]Today|Verlag|Warcraft|What[._ -]Car';

        // Combined pattern with proper word boundaries
        $magazinePattern = '/(?:^|[^a-zA-Z0-9])(?:'.
                          $majorTitles.'|'.
                          $techGaming.'|'.
                          $lifestyle.'|'.
                          $news.'|'.
                          $identifiers.'|'.
                          $digital.'|'.
                          $legacyTitles.
                          ')(?:$|[^a-zA-Z0-9])/i';

        // Check date pattern - common for magazine releases
        if (preg_match($datePattern, $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_MAGAZINES;
            }

            return true;
        }

        // Check for magazine title patterns
        if (preg_match($magazinePattern, $this->releaseName)) {
            // Additional validation - check for typical magazine indicators
            if (preg_match('/(?:Issue|Edition|Vol(\.|ume)?)[._ -]?\d+|No\.[._ -]?\d+|[._ -]?\d{4}[._ -]?\d{2}[._ -]?\d{2}/i', $this->releaseName) ||
                preg_match('/\.(pdf|djvu)$|[._ -](?:PDF|TruePDF|HQ|iNTERNAL|SCAN|Weekly)[._ -]/i', $this->releaseName)) {
                if (! $this->isBookForeign()) {
                    $this->tmpCat = Category::BOOKS_MAGAZINES;
                }

                return true;
            }
        }

        // Check special publication patterns
        if (preg_match('/[._ -](Annual|Bimonthly|Monthly|Quarterly|Special[._ -]Issue)[._ -]/i', $this->releaseName) ||
            preg_match('/\d{1,2}[._ -]?\d{4}[._ -](?:'.$majorTitles.'|'.$techGaming.'|'.$lifestyle.')/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_MAGAZINES;
            }

            return true;
        }

        // Legacy all-in-one pattern for backward compatibility
        if (preg_match('/[a-z\-\._ ][._ -](January|February|March|April|May|June|July|August|September|October|November|December)[._ -](\d{1,2},)?20\d\d[._ -]|^\(.+[ .]\d{1,2}[ .]20\d\d[ .].+\.scr|[._ -](Catalogue|FHM|NUTS|Pictorial|Tatler|XXX)[._ -]|^\(?(Allehanda|Club|Computer([a-z0-9]+)?|Connect \d+|Corriere|ct|Diario|Digit(al)?|Esquire|FHM|Gadgets|Galileo|Glam|GQ|Infosat|Inked|Instyle|io|Kicker|Liberation|New Scientist|NGV|Nuts|Popular|Professional|Reise|Sette(tv)?|Springer|Stuff|Studentlitteratur|Vegetarian|Vegetable|Videomarkt|Wired)[._ -]|Brady(.+)?Games|Catalog|Columbus.+Dispatch|Correspondenten|Corriere[._ -]Della[._ -]Sera|Cosmopolitan|Dagbladet|Digital[._ -]Guide|Economist|Eload ?24|ExtraTime|Fatto[._ -]Quotidiano|Flight[._ -](International|Journal)|Finanzwoche|France.+Football|Foto.+Video|Games?(Master|Markt|tar|TM)|Gardening|Gazzetta|Globe[._ -]And[._ -]Mail|Guitar|Heimkino|Hustler|La.+(Lettura|Rblica|Stampa)|Le[._ -](Monde|Temps)|Les[._ -]Echos|e?Magazin(es?)?|Mac(life|welt)|Marie.+Claire|Maxim|Men.+(Health|Fitness)|Motocross|Motorcycle|Mountain[._ -]Bike|MusikWoche|National[._ -]Geographic|New[._ -]Yorker|PC([._ -](Gamer|Welt|World)|Games|Go|Tip)|Penthouse|Photograph(er|ic)|Playboy|Posten|Quotidiano|(Golf|Readers?).+Digest|SFX[._ -]UK|Recipe(.+Guide|s)|SkyNews|Sport[._ -]?Week|Strategy.+Guide|TabletPC|Tattoo[._ -]Life|The[._ -]Guardian|Tageszeitung|Tid(bits|ning)|Top[._ -]Gear[._ -]|Total[._ -]Guitar|Travel[._ -]Guides?|Tribune[._ -]De[._ -]|US[._ -]Weekly|USA[._ -]Today|TruePDF|Vogue|Verlag|Warcraft|Web.+Designer|What[._ -]Car|Zeitung/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_MAGAZINES;
            }

            return true;
        }

        return false;
    }

    public function isBookOther(): bool
    {
        // First check for specific PS4 format patterns that should never be books
        if (preg_match('/\.PS4-[A-Z0-9]+$/i', $this->releaseName)) {
            return false;
        }

        // Check for period-separated titles ending with PS4 marker
        if (preg_match('/\.[tT]he\.[a-zA-Z]+\.[eE]dition\.PS4/i', $this->releaseName) ||
            preg_match('/\.(Gold|Deluxe|Complete|Definitive|GOTY|Digital|Standard|Ultimate|Special|Premium|Legacy)\.Edition\.PS4/i', $this->releaseName)) {
            return false;
        }

        // More comprehensive check for gaming platforms
        if (preg_match('/\b(?:PS[1-5]|PlayStation[1-5]?|Xbox(?:360|One|Series[SX])?|Switch|Nintendo|Wii[U]?|3DS|GameCube)\b|[\._-](?:PS[1-5]|XONE|NSW|WiiU)[\._-]|\.(PS[1-5]|XONE|NSW|WiiU)-/i', $this->releaseName)) {
            return false;
        }

        // Exclude common game release groups and patterns
        if (preg_match('/[\._-](?:DUPLEX|CODEX|RELOADED|SKIDROW|PLAZA|HOODLUM|ALI213|DODI|FitGirl)$|[.\-_](Game|Games)[.\-_]/i', $this->releaseName)) {
            return false;
        }

        // Exclude Grand Theft Auto and other major game series
        if (preg_match('/Grand[\._-]Theft[\._-]Auto|GTA|Call[\._-]of[\._-]Duty|Assassins[\._-]Creed|Final[\._-]Fantasy/i', $this->releaseName)) {
            return false;
        }

        // Exclude games with "Edition" in the name
        if (preg_match('/\.(Definitive|Complete|Special|Deluxe|Collectors|Ultimate|Enhanced|Remastered)\.Edition/i', $this->releaseName)) {
            return false;
        }

        // The rest of the original method follows...
        $formats = 'PDF|EPUB|MOBI|AZW\d?|FB2|LIT|LRF|RTF|ODF|DJVU|IBA|DOC[X]?';

        // Fiction genres and categories
        $fiction = 'Novel|Fiction|Thriller|Mystery|Fantasy|SciFi|Romance|Horror|Western|'.
                  'Literature|Classics|Contemporary|Historical|Adventure|Detective|Drama';

        // Non-fiction categories (excluding technical)
        $nonfiction = 'Biography|Memoir|Autobiography|History|Philosophy|Psychology|Self[._ -]Help|'.
                     'Business|Cooking|Gardening|Health|Religion|Travel|Art|Music|Spirituality|Politics';

        // Book publishing indicators
        $publishing = 'ISBN|Retail|Publisher|Imprint|Edition|Chapter|Prologue|Epilogue|Foreword|'.
                     'Paperback|Hardcover|Hardback|Softcover|Softback|Print[._ -]On[._ -]Demand';

        // Book collection patterns
        $collections = 'Anthology|Collection|Complete[._ -]Works|Series|Box[._ -]Set|Library|'.
                      'Set[._ -]of|Volume[s]?|Compendium|Omnibus|Trilogy|Saga';

        // Check date patterns more comprehensively
        if (preg_match('/"\d\d-\d\d-20\d\d\.|[\._-]\d{2}[\._-]\d{2}[\._-]20\d{2}[\._-]|[\(\[]\d{2}[\._-]\d{2}[\._-]20\d{2}[\)\]]/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_UNKNOWN;

                return true;
            }

            return true;
        }

        // Check for e-book formats that aren't caught by specific book types
        if (preg_match('/\.('.$formats.')$|\b('.$formats.')\b|[\._\-]('.$formats.')[\._\-]/i', $this->releaseName) &&
            ! preg_match('/\b(?:Magazine|Comic|Technical)\b/i', $this->releaseName)) {

            // Additional validation to exclude other book types
            if (! $this->isComic() && ! $this->isTechnicalBook() && ! $this->isMagazine() && ! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_UNKNOWN;

                return true;
            }
        }

        // Check for fiction indicators
        if (preg_match('/\b('.$fiction.')\b/i', $this->releaseName) &&
            ! preg_match('/\b(?:Magazine|Comic|Technical)\b/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_UNKNOWN;

                return true;
            }

            return true;
        }

        // Check for non-fiction indicators
        if (preg_match('/\b('.$nonfiction.')\b/i', $this->releaseName) &&
            ! preg_match('/\b(?:Magazine|Comic|Technical)\b/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_UNKNOWN;

                return true;
            }

            return true;
        }

        // Check for book publishing or collections
        if ((preg_match('/\b('.$publishing.')\b/i', $this->releaseName) ||
             preg_match('/\b('.$collections.')\b/i', $this->releaseName)) &&
            ! preg_match('/\b(?:Magazine|Comic|Technical)\b/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_UNKNOWN;

                return true;
            }

            return true;
        }

        // Check for common author-title patterns
        if (preg_match('/^[A-Z][a-zA-Z\s\.\-]+\s+\-\s+[A-Z][a-zA-Z0-9\s\.\-\:]+\s+\(?(19|20)\d{2}\)?/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_UNKNOWN;

                return true;
            }

            return true;
        }

        return false;
    }

    public function isEBook(): bool
    {
        // Common e-book formats
        $formats = 'EPUB|MOBI|AZW\d?|KFX|PDF|FB2|DJVU|LIT|LRF|RTF|TXT|DOC[X]?|HTM[L]?|CBZ|CBR|IBA|IBOOKS';

        // E-book descriptors and indicators
        $indicators = 'E-?book|E-?pub|E-?edition|E-?text|Electronic[._ -]Book|Digital[._ -]Book|Digital[._ -]Edition';

        // E-book platforms and stores
        $platforms = 'Kindle|Kobo|Nook|Google[._ -]Play[._ -]Books|iBooks|Smashwords|Gutenberg|Scribd|eReader';

        // E-book publishers and sources
        $publishers = 'O\'?Reilly|Packt|Apress|Manning|Wiley|Pearson|Addison[._ -]Wesley|No[._ -]Starch|Pragmatic';

        // Check for explicit e-book format extensions
        if (preg_match('/\.('.$formats.')$/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_EBOOK;
            }

            return true;
        }

        // Check for e-book formats with word boundaries or common delimiters
        if (preg_match('/\b('.$formats.')\b|[._ -]('.$formats.')[._ -]/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_EBOOK;
            }

            return true;
        }

        // Check for e-book indicators
        if (preg_match('/\b('.$indicators.')\b|[._ -]('.$indicators.')[._ -]/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_EBOOK;
            }

            return true;
        }

        // Check for e-book platforms when associated with book content
        if (preg_match('/\b('.$platforms.')\b/i', $this->releaseName) &&
            preg_match('/\b(Book|Novel|Edition|Title|Author|Chapter)\b/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_EBOOK;
            }

            return true;
        }

        // Check for common e-book publishers in digital format
        if (preg_match('/\b('.$publishers.')\b.*?\b(Digital|Electronic|E-?book)\b/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_EBOOK;
            }

            return true;
        }

        // Legacy pattern matching for backward compatibility
        if (preg_match('/^ePub|[._ -](Ebook|E?\-book|\) WW|Publishing)|[\.\-_\(\[ ](azw|epub|html|mobi|pdf|rtf|tif|txt)[\.\-_\)\] ]|[\. ](azw|doc|epub|mobi|pdf)(?![\w .])|\.ebook-\w$/i', $this->releaseName)) {
            if (! $this->isBookForeign()) {
                $this->tmpCat = Category::BOOKS_EBOOK;
            }

            return true;
        }

        return false;
    }

    //	Misc, all hash/misc go in other misc.

    public function isMisc(): bool
    {
        // Hash patterns - detect common hash formats
        $hashPatterns = [
            // MD5 hash (32 hex characters)
            '/\b[a-f0-9]{32}\b/i',
            // SHA-1 hash (40 hex characters)
            '/\b[a-f0-9]{40}\b/i',
            // SHA-256 hash (64 hex characters)
            '/\b[a-f0-9]{64}\b/i',
            // Generic hex hash pattern (32-128 chars)
            '/\b[a-f0-9]{32,128}\b/i',
        ];

        // Archive and compression formats
        $archiveFormats = '/\.(zip|rar|7z|tar|gz|bz2|xz|tgz|tbz2|cab|iso|img|dmg|pkg|archive)$/i';

        // Dataset and dump file patterns
        $datasetPatterns = [
            // Database dumps
            '/\b(sql|csv|dump|backup|dataset|collection)\b/i',
            // Data leaks and dumps
            '/\b(leak|breach|data|dump|database)\b/i',
        ];

        // Generic misc patterns for unidentifiable content
        $miscPatterns = [
            // Long alphanumeric strings (likely encoded/obfuscated)
            '/[a-z0-9]{20,}/i',
            // Release names consisting only of uppercase letters and numbers
            '/^[A-Z0-9]{1,}$/i',
            // Unusual punctuation patterns
            '/^[^a-zA-Z]*[A-Z0-9\._\-]{5,}[^a-zA-Z]*$/i',
        ];

        // Check for hash patterns first (highest priority)
        foreach ($hashPatterns as $pattern) {
            if (preg_match($pattern, $this->releaseName)) {
                $this->tmpCat = Category::OTHER_HASHED;

                return true;
            }
        }

        // Check for archive formats
        if (preg_match($archiveFormats, $this->releaseName)) {
            $this->tmpCat = Category::OTHER_MISC;

            return true;
        }

        // Check for dataset/dump patterns
        foreach ($datasetPatterns as $pattern) {
            if (preg_match($pattern, $this->releaseName) &&
                ! preg_match('/\b(movie|tv|show|audio|video|book|game)\b/i', $this->releaseName)) {
                $this->tmpCat = Category::OTHER_MISC;

                return true;
            }
        }

        // Check for generic misc patterns
        foreach ($miscPatterns as $pattern) {
            if (preg_match($pattern, $this->releaseName)) {
                $this->tmpCat = Category::OTHER_MISC;

                return true;
            }
        }

        // Legacy pattern checks for backward compatibility
        if (preg_match('/[a-f0-9]{32,64}/i', $this->releaseName)) {
            $this->tmpCat = Category::OTHER_HASHED;

            return true;
        }

        return false;
    }

    /**
     * @param  string  $regex  Regex to use for match
     * @param  string  $fromName  Poster that needs to be matched by regex
     * @param  string  $category  Category to set if there is a match
     */
    public function checkPoster(string $regex, string $fromName, string $category): bool
    {
        if (preg_match($regex, $fromName)) {
            $this->tmpCat = $category;

            return true;
        }

        return false;
    }
}
