<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\Settings;
use App\Models\UsenetGroup;

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
     * @throws \Exception
     */
    public function __construct()
    {
        $this->categorizeForeign = (bool) Settings::settingValue('categorizeforeign');
        $this->catWebDL = (bool) Settings::settingValue('catwebdl');
    }

    /**
     * Look up the site to see which language of categorizing to use.
     * Then work out which category is applicable for either a group or a binary.
     * Returns Category::OTHER_MISC if no category is appropriate.
     *
     *
     * @throws \Exception
     */
    public function determineCategory($groupId, string $releaseName = '', string $poster = ''): array
    {
        $this->releaseName = $releaseName;
        $this->groupId = $groupId;
        $this->poster = $poster;
        $this->groupName = UsenetGroup::whereId($this->groupId)->value('name') ?? '';
        $this->tmpCat = Category::OTHER_MISC;

        return match (true) {
            $this->isMisc(), $this->byGroupName($this->groupName), $this->isPC(), $this->isXxx(), $this->isTV(), $this->isMovie(), $this->isConsole(), $this->isBook(), $this->isMusic() => [
                'categories_id' => $this->tmpCat,
            ],
            default => ['categories_id' => $this->tmpCat],
        };
    }

    public function byGroupName($groupName): bool
    {
        switch (true) {
            case preg_match('/alt\.binaries\.erotica([.]\w+)?/i', $groupName):
                if ($this->isXxx()) {
                    return true;
                }
                $this->tmpCat = Category::XXX_OTHER;

                return true;
            case preg_match('/alt\.binaries\.podcast$/i', $groupName):
                $this->tmpCat = Category::MUSIC_PODCAST;

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
        if (! preg_match('/(S\d+).*(2160p).*(Netflix|Amazon).*(TrollUHD|NTb|VLAD)/i', $this->releaseName) && stripos($this->releaseName, '2160p') !== false) {
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
        if (preg_match('/web[._ -]dl|web-?rip/i', $this->releaseName)) {
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
        if (preg_match('/^NDS|[^a-zA-Z0-9]NDS|[\._-](nds|NDS)|nintendo.+[^3]n?dsi?/', $this->releaseName)) {
            if (preg_match('/\((DE|DSi(\sEnhanched)?|_NDS-|EUR?|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA?)\)/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_NDS;

                return true;
            }
            if (preg_match('/EUR|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA|\bROMS?(et)?\b/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_NDS;

                return true;
            }
        }

        return false;
    }

    public function isGame3DS(): bool
    {
        if (preg_match('/\b3DS\b[^max]|[\._-]3ds|nintendo.+3ds|[_\.]3DS-/i', $this->releaseName) && ! preg_match('/3ds max/i', $this->releaseName) && preg_match('/(EUR|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA|ASIA)/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_3DS;

            return true;
        }

        return false;
    }

    public function isGameNGC(): bool
    {
        if (preg_match('/[\._-]N?G(AME)?C(UBE)?-/i', $this->releaseName)) {
            if (preg_match('/_(EUR?|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA?)_/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_OTHER;

                return true;
            }
            if (preg_match('/-(((STAR|DEATH|STINKY|MOON|HOLY|G)?CUBE(SOFT)?)|(DARKFORCE|DNL|GP|ICP|iNSOMNIA|JAY|LaKiTu|METHS|NOMIS|QUBiSM|PANDORA|REACT0R|SUNSHiNE|SAVEPOiNT|SYNDiCATE|WAR3X|WRG))/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_OTHER;

                return true;
            }
        }

        return false;
    }

    public function isGamePS3(): bool
    {
        if (preg_match('/[^e]PS3/i', $this->releaseName)) {
            if (preg_match('/ANTiDOTE|DLC|DUPLEX|EUR?|Googlecus|GOTY|\-HR|iNSOMNi|JAP|JPN|KONDIOS|\[PS3\]|PSN/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PS3;

                return true;
            }
            if (preg_match('/AGENCY|APATHY|Caravan|MULTi|NRP|NTSC|PAL|SPLiT|STRiKE|USA?|ZRY/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PS3;

                return true;
            }
        }

        return false;
    }

    public function isGamePS4(): bool
    {
        if (preg_match('/[ \(_.-]PS4[ \)_.-]/i', $this->releaseName)) {
            if (preg_match('/ANTiDOTE|DLC|DUPLEX|EUR?|Googlecus|GOTY|\-HR|iNSOMNi|JAP|JPN|KONDIOS|\[PS4\]/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PS4;

                return true;
            }
            if (preg_match('/AGENCY|APATHY|Caravan|MULTi|NRP|NTSC|PAL|SPLiT|STRiKE|USA?|WaYsTeD|ZRY/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PS4;

                return true;
            }
        }

        return false;
    }

    public function isGamePSP(): bool
    {
        if (stripos($this->releaseName, 'PSP') !== false) {
            if (preg_match('/[._ -](BAHAMUT|Caravan|EBOOT|EMiNENT|EUR?|EvoX|GAME|GHS|Googlecus|HandHeld|\-HR|JAP|JPN|KLOTEKLAPPERS|KOR|NTSC|PAL)/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PSP;

                return true;
            }
            if (preg_match('/[._ -](Dynarox|HAZARD|ITALIAN|KLB|KuDoS|LIGHTFORCE|MiRiBS|POPSTATiON|(PLAY)?ASiA|PSN|PSX2?PSP|SPANiSH|SUXXORS|UMD(RIP)?|USA?|YARR)/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PSP;

                return true;
            }
        }

        return false;
    }

    public function isGamePSVita(): bool
    {
        if (preg_match('/PS ?Vita/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_PSVITA;

            return true;
        }

        return false;
    }

    public function isGameWiiWare(): bool
    {
        if (preg_match('/(Console|DLC|VC).+[._ -]WII|(Console|DLC|VC)[._ -]WII|WII[._ -].+(Console|DLC|VC)|WII[._ -](Console|DLC|VC)|WIIWARE/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_WIIWARE;

            return true;
        }

        return false;
    }

    public function isGameWiiU(): bool
    {
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
        if (preg_match('/DLC.+xbox360|xbox360.+DLC|XBLA.+xbox360|xbox360.+XBLA/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_XBOX360DLC;

            return true;
        }

        return false;
    }

    public function isGameXBOX360(): bool
    {
        if (stripos($this->releaseName, '/XBOX360/i') !== false) {
            $this->tmpCat = Category::GAME_XBOX360;

            return true;
        }
        if (stripos($this->releaseName, 'x360') !== false) {
            if (preg_match('/Allstars|ASiA|CCCLX|COMPLEX|DAGGER|GLoBAL|iMARS|JAP|JPN|MULTi|NTSC|PAL|REPACK|RRoD|RF|SWAG|USA?/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_XBOX360;

                return true;
            }
            if (preg_match('/DAMNATION|GERMAN|GOTY|iNT|iTA|JTAG|KINECT|MARVEL|MUX360|RANT|SPARE|SPANISH|VATOS|XGD/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_XBOX360;

                return true;
            }
        }

        return false;
    }

    public function isGameXBOXONE(): bool
    {
        if (preg_match('/XBOXONE|XBOX\.ONE/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_XBOXONE;

            return true;
        }

        return false;
    }

    public function isGameXBOX(): bool
    {
        if (stripos($this->releaseName, 'XBOX') !== false) {
            $this->tmpCat = Category::GAME_XBOX;

            return true;
        }

        return false;
    }

    public function isGameOther(): bool
    {
        if (preg_match('/\b(PS(1)X|PS2|SNES|NES|SEGA\s(GENESIS|CD)|GB([AC])|Dreamcast|SEGA\sSaturn|Atari\s(Jaguar)?|3DO)\b/i', $this->releaseName) && preg_match('/EUR|FR|GAME|HOL|\bISO\b|JP|JPN|NL|NTSC|PAL|KS|USA|ROMS?(et)?/i', $this->releaseName)) {
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
        if ($this->categorizeForeign && preg_match('/[ \-\._](brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|ita|latin|mandarin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish|bl|cz|de|es|fr|ger|heb|hu|hun|it(a| 19|20\d\d)|jap|ko|kor|nl|pl|se)[ \-\._]/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_FOREIGN;

            return true;
        }

        return false;
    }

    public function isAudiobook(): bool
    {
        if (preg_match('/(Audiobook|Audio.?Book)/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_AUDIOBOOK;

            return true;
        }

        return false;
    }

    public function isMusicVideo(): bool
    {
        if (preg_match('/(720P|x264)\-(19|20)\d\d\-[a-z0-9]{1,12}/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_VIDEO;

            return true;
        }
        if (preg_match('/[a-z0-9]{1,12}-(19|20)\d\d-(720P|x264)/i', $this->releaseName)) {
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
        if (preg_match('/\[(19|20)\d\d\][._ -]\[FLAC\]|([\(\[])flac([\)\]])|FLAC\-(19|20)\d\d\-[a-z0-9]{1,12}|\.flac"|(19|20)\d\d\sFLAC|[._ -]FLAC.+(19|20)\d\d[._ -]| FLAC$/i', $this->releaseName)) {
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
        if (preg_match('/[a-z0-9]{1,12}\-(19|20)\d\d\-[a-z0-9]{1,12}|[\.\-\(\[_ ]\d{2,3}k[\.\-\)\]_ ]|\((192|256|320)\)|(320|cd|eac|vbr).+mp3|(cd|eac|mp3|vbr).+320|FIH\_INT|\s\dCDs|[._ -]MP3[._ -]|MP3\-\d{3}kbps|\.(m3u|mp3)"|NMR\s\d{2,3}\skbps|\(320\)\.|\-\((Bootleg|Promo)\)|\.mp3$|\-\sMP3\s(19|20)\d\d|\(vbr\)|rip(192|256|320)|[._ -](CDR|SBD|WEB).+(19|20)\d\d/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_MP3;

            return true;
        }
        if (preg_match('/\s(19|20)\d\d\s([a-z0-9]{3}|[a-z]{2,})$|\-(19|20)\d\d\-(C4|MTD)([\s\.])|[._ -]FM.+MP3[._ -]|-web-(19|20)\d\d([\.\s$])|[._ -](SAT|SBD|WEB).+(19|20)\d\d([._ -]|$)|[._ -](19|20)\d\d.+(SAT|WEB)([._ -]|$)| MP3$/i', $this->releaseName)) {
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
        if (preg_match('/(19|20)\d\d\-(C4)$|[._ -]\d?CD[._ -](19|20)\d\d|\(\d\-?CD\)|\-\dcd\-|\d[._ -]Albums|Albums.+(EP)|Bonus.+Tracks|Box.+?CD.+SET|Discography|D\.O\.M|Greatest\sSongs|Live.+(Bootleg|Remastered)|Music.+Vol|([\(\[\s])NMR([\)\]\s])|Promo.+CD|Reggaeton|Tiesto.+Club|Vinyl\s2496|\WV\.A\.|^\(VA\s|^VA[._ -]/i', $this->releaseName)) {
            if (! $this->isMusicForeign()) {
                $this->tmpCat = Category::MUSIC_OTHER;
            }

            return true;
        }
        if (preg_match('/\(pure_fm\)|-+\(?(2lp|cd[ms]([\-_ .][a-z]{2})?|cover|ep|ltd_ed|mix|original|ost|.*?(edit(ion)?|remix(es)?|vinyl)|web)\)?-+((19|20)\d\d|you$)/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_OTHER;

            return true;
        }

        return false;
    }

    public function isMusicPodcast(): bool
    {
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
        switch (true) {
            case preg_match('/[ \-\._](brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|ita|latin|mandarin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)[._ -]/i', $this->releaseName):
                $this->tmpCat = Category::BOOKS_FOREIGN;

                return true;
            default:
                return false;
        }
    }

    public function isComic(): bool
    {
        switch (true) {
            case ! preg_match('/[\. ](cbr|cbz)|[\( ]c2c|cbr|cbz[\) ]|comix|^\(comic|[\.\-_\(\[ ]comics?[._ -]|comic.+book|covers.+digital|DC.+(Adventures|Universe)|digital.+(son|zone)|Graphic.+Novel|[\.\-_h ]manga|Total[._ -]Marvel/i', $this->releaseName):
                return false;
            case $this->isBookForeign():
                break;
            default:
                $this->tmpCat = Category::BOOKS_COMICS;
                break;
        }

        return true;
    }

    public function isTechnicalBook(): bool
    {
        switch (true) {
            case ! preg_match('/^\(?(atz|bb|css|c ?t|Drawing|Gabler|IOS|Iphone|Lynda|Manning|Medic(al|ine)|MIT|No[._ -]Starch|Packt|Peachpit|Pragmatic|Revista|Servo|SmartBooks|Spektrum|Strata|Sybex|Syngress|Vieweg|Wiley|Woods|Wrox)[._ -]|[._ -](Ajax|CSS|DIY|Javascript|(My|Postgre)?SQL|XNA)[._ -]|3DS\.\-_ ]Max|Academic|Adobe|Algebra|Analysis|Appleworks|Archaeology|Bitdefender|Birkhauser|Britannica|[._ -]C\+\+|C[._ -](\+\+|Sharp|Plus)|Chemistry|Circuits|Cook(book|ing)|(Beginners?|Complete|Communications|Definitive|Essential|Hackers?|Practical|Professionals?)[._ -]Guide|Developer|Diagnostic|Disassembl(er|ing|y)|Debugg(er|ing)|Dreamweaver|Economics|Education|Electronics|Enc([iy])clopedia|Engineer(ing|s)|Essays|Exercizes|For.+Beginners|Focal[._ -]Press|For[._ -]Dummies|FreeBSD|Fundamentals[._ -]of[._ -]|(Galileo|Island)[._ -]Press|Geography|Grammar|Guide[._ -](For|To)|Hacking|Google|Handboo?k|How[._ -](It|To)|Intoduction[._ -]to|Iphone|jQuery|Lessons[._ -]In|Learning|LibreOffice|Linux|Manual|Marketing|Masonry|Mathematic(al|s)?|Medical|Microsoft|National[._ -]Academies|Nero[._ -]\d+|OReilly|OS[._ -]X[._ -]|Official[._ -]Guide|Open(GL|Office)|Pediatric|Periodic.+Table|Photoshop|Physics|Power(PC|Point|Shell)|Programm(ers?|ier||ing)|Raspberry.+Pi|Remedies|Service\s?Manual|SitePoint|Sketching|Statistics|Stock.+Market|Students|Theory|Training|Tutsplus|Ubuntu|Understanding[._ -](and|Of|The)|Visual[._ -]Studio|Textbook|VMWare|wii?max|Windows[._ -](8|7|Vista|XP)|^Wood[._ -]|Woodwork|WordPress|Work(book|shop)|Youtube/i', $this->releaseName):
                return false;
            case $this->isBookForeign():
                break;
            default:
                $this->tmpCat = Category::BOOKS_TECHNICAL;
                break;
        }

        return true;
    }

    public function isMagazine(): bool
    {
        switch (true) {
            case ! preg_match('/[a-z\-\._ ][._ -](January|February|March|April|May|June|July|August|September|October|November|December)[._ -](\d{1,2},)?20\d\d[._ -]|^\(.+[ .]\d{1,2}[ .]20\d\d[ .].+\.scr|[._ -](Catalogue|FHM|NUTS|Pictorial|Tatler|XXX)[._ -]|^\(?(Allehanda|Club|Computer([a-z0-9]+)?|Connect \d+|Corriere|ct|Diario|Digit(al)?|Esquire|FHM|Gadgets|Galileo|Glam|GQ|Infosat|Inked|Instyle|io|Kicker|Liberation|New Scientist|NGV|Nuts|Popular|Professional|Reise|Sette(tv)?|Springer|Stuff|Studentlitteratur|Vegetarian|Vegetable|Videomarkt|Wired)[._ -]|Brady(.+)?Games|Catalog|Columbus.+Dispatch|Correspondenten|Corriere[._ -]Della[._ -]Sera|Cosmopolitan|Dagbladet|Digital[._ -]Guide|Economist|Eload ?24|ExtraTime|Fatto[._ -]Quotidiano|Flight[._ -](International|Journal)|Finanzwoche|France.+Football|Foto.+Video|Games?(Master|Markt|tar|TM)|Gardening|Gazzetta|Globe[._ -]And[._ -]Mail|Guitar|Heimkino|Hustler|La.+(Lettura|Rblica|Stampa)|Le[._ -](Monde|Temps)|Les[._ -]Echos|e?Magazin(es?)?|Mac(life|welt)|Marie.+Claire|Maxim|Men.+(Health|Fitness)|Motocross|Motorcycle|Mountain[._ -]Bike|MusikWoche|National[._ -]Geographic|New[._ -]Yorker|PC([._ -](Gamer|Welt|World)|Games|Go|Tip)|Penthouse|Photograph(er|ic)|Playboy|Posten|Quotidiano|(Golf|Readers?).+Digest|SFX[._ -]UK|Recipe(.+Guide|s)|SkyNews|Sport[._ -]?Week|Strategy.+Guide|TabletPC|Tattoo[._ -]Life|The[._ -]Guardian|Tageszeitung|Tid(bits|ning)|Top[._ -]Gear[._ -]|Total[._ -]Guitar|Travel[._ -]Guides?|Tribune[._ -]De[._ -]|US[._ -]Weekly|USA[._ -]Today|TruePDF|Vogue|Verlag|Warcraft|Web.+Designer|What[._ -]Car|Zeitung/i', $this->releaseName):
                return false;
            case $this->isBookForeign():
                break;
            default:
                $this->tmpCat = Category::BOOKS_MAGAZINES;
                break;
        }

        return true;
    }

    public function isBookOther(): bool
    {
        if (preg_match('/"\d\d-\d\d-20\d\d\./', $this->releaseName)) {
            $this->tmpCat = Category::BOOKS_UNKNOWN;

            return true;
        }

        return false;
    }

    public function isEBook(): bool
    {
        switch (true) {
            case ! preg_match('/^ePub|[._ -](Ebook|E?\-book|\) WW|Publishing)|[\.\-_\(\[ ](azw|epub|html|mobi|pdf|rtf|tif|txt)[\.\-_\)\] ]|[\. ](azw|doc|epub|mobi|pdf)(?![\w .])|\.ebook-\w$/i', $this->releaseName):
                return false;
            case $this->isBookForeign():
                break;
            default:
                $this->tmpCat = Category::BOOKS_EBOOK;
                break;
        }

        return true;
    }

    //	Misc, all hash/misc go in other misc.

    public function isMisc(): bool
    {
        switch (true) {
            case preg_match('/[a-f0-9]{32,64}/i', $this->releaseName):
                $this->tmpCat = Category::OTHER_HASHED;
                break;
            case preg_match('/[a-z0-9]{20,}/i', $this->releaseName):
            case preg_match('/^[A-Z0-9]{1,}$/i', $this->releaseName):
                $this->tmpCat = Category::OTHER_MISC;
                break;
            default:
                return false;
        }

        return true;
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
