<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\Settings;
use App\Models\UsenetGroup;
use Elastic\Elasticsearch\Endpoints\Cat;

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
     *
     * @var int
     */
    protected int $tmpCat = Category::OTHER_MISC;

    /**
     * Temporary tag while we sort through the name.
     *
     * @var array
     */
    protected array $tmpTag = [Category::TAG_OTHER_MISC];
    /**
     * @var bool
     */
    protected bool $categorizeForeign;

    /**
     * @var bool
     */
    protected bool $catWebDL;

    /**
     * Release name to sort through.
     *
     * @var string
     */
    public string $releaseName;

    /**
     * Release poster to sort through.
     *
     * @var string
     */
    public string $poster;

    /**
     * Group id of the releasename we are sorting through.
     *
     * @var int|string
     */
    public string|int $groupId;

    /**
     * @var string
     */
    public string $groupName;

    /**
     * Categorize constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->categorizeForeign = (bool) Settings::settingValue('indexer.categorise.categorizeforeign');
        $this->catWebDL = (bool) Settings::settingValue('indexer.categorise.catwebdl');
    }

    /**
     * Look up the site to see which language of categorizing to use.
     * Then work out which category is applicable for either a group or a binary.
     * Returns Category::OTHER_MISC if no category is appropriate.
     *
     * @param    $groupId
     * @param  string  $releaseName
     * @param  string  $poster
     * @return array
     *
     * @throws \Exception
     */
    public function determineCategory($groupId, string $releaseName = '', string $poster = ''): array
    {
        $this->releaseName = $releaseName;
        $this->groupId = $groupId;
        $this->poster = $poster;
        $this->groupName = UsenetGroup::whereId($this->groupId)->value('name');

        return match (true) {
            $this->isMisc(), $this->byGroupName($this->groupName), $this->isPC(), $this->isXxx(), $this->isTV(), $this->isMovie(), $this->isConsole(), $this->isBook(), $this->isMusic() => [
                'categories_id' => $this->tmpCat,
                'tags' => $this->tmpTag,
            ],
            default => ['categories_id' => $this->tmpCat, 'tags' => $this->tmpTag],
        };
    }

    /**
     * @param $groupName
     * @return bool
     */
    public function byGroupName($groupName): bool
   {
        switch(true) {
            case preg_match('/alt\.binaries\.erotica([.]\w+)?/i', $groupName):
                if ($this->isXxx()) {
                    break;
                }
                $this->tmpCat = Category::XXX_OTHER;
                $this->tmpTag[] = Category::TAG_XXX_OTHER;
                break;
            case preg_match('/alt\.binaries\.podcast$/i', $groupName):
                $this->tmpCat = Category::MUSIC_PODCAST;
                $this->tmpTag = Category::TAG_MUSIC_PODCAST;
                break;
            default:
                return false;
        }

        return true;
   }

    //
    // Beginning of functions to determine category by release name.
    //

    /**
     * @return bool
     */
    public function isTV(): bool
    {
        if (preg_match('/Daily[\-_\.]Show|Nightly News|^\[[a-zA-Z\.\-]+\].*[\-_].*\d{1,3}[\-_. ](([\[\(])(h264-)?\d{3,4}([pi])([\]\)])\s?(\[AAC\])?|\[[a-fA-F0-9]{8}\]|(8|10)BIT|hi10p)(\[[a-fA-F0-9]{8}\])?|(\d\d-){2}[12]\d{3}|[12]\d{3}(\.\d\d){2}|\d+x\d+|\.e\d{1,3}\.|s\d{1,3}[._ -]?[ed]\d{1,3}([ex]\d{1,3}|[\-.\w ])|[._ -](\dx\d\d|C4TV|Complete[._ -]Season|DSR|([DHPS])DTV|EP[._ -]?\d{1,3}|S\d{1,3}.+Extras|SUBPACK|Season[._ -]\d{1,2})([._ -]|$)|TVRIP|TV[._ -](19|20)\d\d|Troll(HD|UHD)/i', $this->releaseName)
            && ! preg_match('/[._ -](flac|imageset|mp3|xxx)[._ -]|[ .]exe$/i', $this->releaseName)) {
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
                    $this->tmpTag[] = Category::TAG_TV_OTHER;

                    return true;
            }
        }

        if (preg_match('/[._ -]((19|20)\d\d[._ -]\d{1,2}[._ -]\d{1,2}[._ -]VHSRip|Indy[._ -]?Car|(iMPACT|Smoky[._ -]Mountain|Texas)[._ -]Wrestling|Moto[._ -]?GP|NSCS[._ -]ROUND|NECW[._ -]TV|(Per|Post)\-Show|PPV|WrestleMania|WCW|WEB[._ -]HD|WWE[._ -](Monday|NXT|RAW|Smackdown|Superstars|WrestleMania))[._ -]/i', $this->releaseName)) {
            if ($this->isSportTV()) {
                return true;
            }
            $this->tmpCat = Category::TV_OTHER;
            $this->tmpTag[] = Category::TAG_TV_OTHER;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isOtherTV(): bool
    {
        if (preg_match('/[._ -]S\d{1,3}.+(EP\d{1,3}|Extras|SUBPACK)[._ -]|News/i', $this->releaseName)
            //special case for "Have.I.Got.News.For.You" tv show
            && ! preg_match('/[._ -]Got[._ -]News[._ -]For[._ -]You/i', $this->releaseName)
        ) {
            $this->tmpCat = Category::TV_OTHER;
            $this->tmpTag[] = Category::TAG_TV_OTHER;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isForeignTV(): bool
    {
        switch (true) {
            case preg_match('/[._ -](chinese|dk|fin|french|ger?|heb|ita|jap|kor|nor|nordic|nl|pl|swe)[._ -]?(sub|dub)(ed|bed|s)?|<German>/i', $this->releaseName):
            case preg_match('/[._ -](brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|ita|latin|mandarin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish).+(720p|1080p|Divx|DOKU|DUB(BED)?|DLMUX|NOVARIP|RealCo|Sub(bed|s)?|Web[._ -]?Rip|WS|Xvid|x264)[._ -]/i', $this->releaseName):
            case preg_match('/[._ -](720p|1080p|Divx|DOKU|DUB(BED)?|DLMUX|NOVARIP|RealCo|Sub(bed|s)?|WEB(-DL|-?RIP)|WS|Xvid).+(brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|ita|latin|mandarin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)[._ -]/i', $this->releaseName):
            case preg_match('/(S\d\d[EX]\d\d|DOCU(MENTAIRE)?|TV)?[._ -](FRENCH|German|Dutch)[._ -](720p|1080p|dv([bd])r(ip)?|LD|HD\-?TV|TV[._ -]?RIP|x264|WEB(-DL|-?RIP))[._ -]/i', $this->releaseName):
            case preg_match('/[._ -]FastSUB|NL|nlvlaams|patrfa|RealCO|Seizoen|slosinh|Videomann|Vostfr|xslidian[._ -]|x264\-iZU/i', $this->releaseName):
                $this->tmpCat = Category::TV_FOREIGN;
                $this->tmpTag[] = Category::TAG_TV_FOREIGN;

                return true;
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function isSportTV(): bool
    {
        switch (true) {
            case preg_match('/[._ -]?(Bellator|bundesliga|EPL|ESPN|FIA|la[._ -]liga|MMA|motogp|NFL|MLB|NCAA|PGA|FIM|NJPW|red[._ -]bull|.+race|Sengoku|Strikeforce|supercup|uefa|UFC|wtcc|WWE)[._ -]/i', $this->releaseName):
            case preg_match('/[._ -]?(DTM|FIFA|formula[._ -]1|indycar|Rugby|NASCAR|NBA|NHL|NRL|netball[._ -]anz|ROH|SBK|Superleague|The[._ -]Ultimate[._ -]Fighter|TNA|V8[._ -]Supercars|WBA|WrestleMania)[._ -]/i', $this->releaseName):
            case preg_match('/[._ -]?(AFL|Grand Prix|Indy[._ -]Car|(iMPACT|Smoky[._ -]Mountain|Texas)[._ -]Wrestling|Moto[._ -]?GP|NSCS[._ -]ROUND|NECW|Poker|PWX|Rugby|WCW)[._ -]/i', $this->releaseName):
            case preg_match('/[._ -]?(Horse)[._ -]Racing[._ -]/i', $this->releaseName):
            case preg_match('/[._ -](VERUM|GRiP|Ebi|OVERTAKE|LEViTATE|WiNNiNG|ADMIT)/i', $this->releaseName):
                $this->tmpCat = Category::TV_SPORT;
                $this->tmpTag[] = Category::TAG_TV_SPORT;

                return true;
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function isDocumentaryTV(): bool
    {
        if (preg_match('/[._ -](Docu|Documentary)[._ -]/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_DOCU;
            $this->tmpTag[] = Category::TAG_TV_DOCU;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isWEBDL(): bool
    {
        if (preg_match('/(S\d+).*.web[._-]?(dl|rip).*/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_WEBDL;
            $this->tmpTag = [Category::TAG_TV_WEBDL, Category::TAG_TV_HD];

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isAnimeTV(): bool
    {
        if (preg_match('/[._ -]Anime[._ -]|^\[[a-zA-Z\.\-]+\].*[\-_].*\d{1,3}[\-_. ](([\[\(])((\d{1,4}x\d{1,4})|(h264-)?\d{3,4}([pi]))([\]\)])\s?(\[AAC\])?|\[[a-fA-F0-9]{8}\]|(8|10)BIT|hi10p)(\[[a-fA-F0-9]{8}\])?/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_ANIME;
            $this->tmpTag[] = Category::TAG_TV_ANIME;

            return true;
        }
        if (preg_match('/(ANiHLS|HaiKU|ANiURL)/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_ANIME;
            $this->tmpTag[] = Category::TAG_TV_ANIME;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isHDTV(): bool
    {
        if (preg_match('/1080([ip])|720p|bluray/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_HD;
            $this->tmpTag[] = Category::TAG_TV_HD;

            return true;
        }
        if (! $this->catWebDL && preg_match('/web[._ -]dl|web-?rip/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_HD;
            $this->tmpTag[] = Category::TAG_TV_HD;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isUHDTV(): bool
    {
        if (preg_match('/(S\d+).*(2160p).*(Netflix|Amazon|NF|AMZN).*(TrollUHD|NTb|VLAD|DEFLATE|POFUDUK)/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_UHD;
            $this->tmpTag = [Category::TAG_TV_UHD, Category::TAG_TV_HD];

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isSDTV(): bool
    {
        switch (true) {
            case preg_match('/(360|480|576)p|Complete[._ -]Season|dvdr(ip)?|dvd5|dvd9|\.pdtv|SD[._ -]TV|TVRip|NTSC|BDRip|hdtv|xvid/i', $this->releaseName):
            case preg_match('/(([HP])D[._ -]?TV|DSR|WebRip)[._ -]x264/i', $this->releaseName):
            case preg_match('/s\d{1,3}[._ -]?[ed]\d{1,3}([ex]\d{1,3}|[\-.\w ])|\s\d{3,4}\s/i', $this->releaseName) && preg_match('/([HP])D[._ -]?TV|BDRip|WEB[._ -]x264/i', $this->releaseName):
                $this->tmpCat = Category::TV_SD;
                $this->tmpTag[] = Category::TAG_TV_SD;

                return true;
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function isOtherTV2(): bool
    {
        if (preg_match('/[._ -]s\d{1,3}[._ -]?(e|d(isc)?)\d{1,3}([._ -]|$)/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_OTHER;
            $this->tmpTag[] = Category::TAG_TV_OTHER;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isTVx265(): bool
    {
        if (preg_match('/(S\d+).*(x265).*(rmteam|MeGusta|HETeam|PSA|ONLY|H4S5S|TrollHD|ImE)/i', $this->releaseName)) {
            $this->tmpCat = Category::TV_X265;
            $this->tmpTag = [Category::TAG_TV_X265, Category::TAG_TV_HD];

            return true;
        }

        return false;
    }

    //  Movies.

    /**
     * @return bool
     */
    public function isMovie(): bool
    {
        if (preg_match('/[._ -]AVC|[._ -]|[BH][DR]RIP|Bluray|BD[._ -]?(25|50)?|\bBR\b|Camrip|[._ -]\d{4}[._ -].+(720p|1080p|Cam|HDTS)|DIVX|[._ -]DVD[._ -]|DVD-?(5|9|R|Rip)|Untouched|VHSRip|XVID|[._ -](DTS|TVrip)[._ -]/i', $this->releaseName) && ! preg_match('/auto(cad|desk)|divx[._ -]plus|[._ -]exe$|[._ -](jav|XXX)[._ -]|SWE6RUS|\wXXX(1080p|720p|DVD)|Xilisoft/i', $this->releaseName)) {
            return match (true) {
                $this->categorizeForeign && $this->isMovieForeign(), $this->isMovieDVD(), $this->isMovieX265(), $this->isMovieUHD(), $this->catWebDL && $this->isMovieWEBDL(), $this->isMovieSD(), $this->isMovie3D(), $this->isMovieBluRay(), $this->isMovieHD(), $this->isMovieOther() => true,
                default => false,
            };
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMovieForeign(): bool
    {
        switch (true) {
            case $this->isConsole():
                return true;
            case preg_match('/(danish|flemish|Deutsch|dutch|french|german|heb|hebrew|nl[._ -]?sub|dub(bed|s)?|\.NL|norwegian|swedish|swesub|spanish|Staffel)[._ -]|\(german\)|Multisub/i', $this->releaseName):
            case stripos($this->releaseName, 'Castellano') !== false:
            case preg_match('/(720p|1080p|AC3|AVC|DIVX|DVD(5|9|RIP|R)|XVID)[._ -](Dutch|French|German|ITA)|\(?(Dutch|French|German|ITA)\)?[._ -](720P|1080p|AC3|AVC|DIVX|DVD(5|9|RIP|R)|WEB(-DL|-?RIP)|HD[._ -]|XVID)/i', $this->releaseName):
                $this->tmpCat = Category::MOVIE_FOREIGN;
                $this->tmpTag[] = Category::TAG_MOVIE_FOREIGN;

                return true;
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function isMovieDVD(): bool
    {
        if (preg_match('/(dvd\-?r|[._ -]dvd|dvd9|dvd5|[._ -]r5)[._ -]/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_DVD;
            $this->tmpTag[] = Category::TAG_MOVIE_DVD;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMovieSD(): bool
    {
        if (preg_match('/(divx|dvdscr|extrascene|dvdrip|\.CAM|HDTS(-LINE)?|vhsrip|xvid(vd)?)[._ -]/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_SD;
            $this->tmpTag[] = Category::TAG_MOVIE_SD;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMovie3D(): bool
    {
        if (preg_match('/[._ -]3D\s?[\.\-_\[ ](1080p|(19|20)\d\d|AVC|BD(25|50)|Blu[._ -]?ray|CEE|Complete|GER|MVC|MULTi|SBS|H(-)?SBS)[._ -]/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_3D;
            $this->tmpTag[] = Category::TAG_MOVIE_3D;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMovieBluRay(): bool
    {
        if (preg_match('/bluray-|[._ -]bd?[._ -]?(25|50)|blu-ray|Bluray\s-\sUntouched|[._ -]untouched[._ -]/i', $this->releaseName)
            && ! preg_match('/SecretUsenet\.com$/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_BLURAY;
            $this->tmpTag[] = Category::TAG_MOVIE_BLURAY;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMovieHD(): bool
    {
        if (preg_match('/720p|1080p|AVC|VC1|VC-1|web-dl|wmvhd|x264|XvidHD|bdrip/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_HD;
            $this->tmpTag[] = Category::TAG_MOVIE_HD;

            return true;
        }
        if ($this->catWebDL === false && preg_match('/web[._ -]dl|web-?rip/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_HD;
            $this->tmpTag[] = Category::TAG_MOVIE_HD;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMovieUHD(): bool
    {
        if (! preg_match('/(S\d+).*(2160p).*(Netflix|Amazon).*(TrollUHD|NTb|VLAD)/i', $this->releaseName) && false !== stripos($this->releaseName, '2160p')) {
            $this->tmpCat = Category::MOVIE_UHD;
            $this->tmpTag = [Category::TAG_MOVIE_UHD, Category::TAG_MOVIE_HD];

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMovieOther(): bool
    {
        if (preg_match('/[._ -]cam[._ -]/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_OTHER;
            $this->tmpTag[] = Category::TAG_MOVIE_OTHER;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMovieWEBDL(): bool
    {
        if (preg_match('/web[._ -]dl|web-?rip/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_WEBDL;
            $this->tmpTag = [Category::TAG_MOVIE_WEBDL, Category::TAG_MOVIE_HD];

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMovieX265(): bool
    {
        if (preg_match('/(\w+[\.-_\s]+).*(x265).*(Tigole|SESKAPiLE|CHD|IAMABLE|THREESOME|OohLaLa|DEFLATE|NCmt)/i', $this->releaseName)) {
            $this->tmpCat = Category::MOVIE_X265;
            $this->tmpTag = [Category::TAG_MOVIE_X265, Category::TAG_MOVIE_HD];

            return true;
        }

        return false;
    }

    //  PC.

    /**
     * @return bool
     */
    public function isPC(): bool
    {
        return match (true) {
            $this->isPhone(), $this->isMac(), $this->isPCGame(), $this->isISO(), $this->is0day() => true,
            default => false,
        };
    }

    /**
     * @return bool
     */
    public function isPhone(): bool
    {
        switch (true) {
            case preg_match('/[^a-z0-9](IPHONE|ITOUCH|IPAD)[._ -]/i', $this->releaseName):
                $this->tmpCat = Category::PC_PHONE_IOS;
                $this->tmpTag[] = Category::TAG_PC_PHONE_IOS;
                break;
            case preg_match('/[._ -]?(ANDROID)[._ -]/i', $this->releaseName):
                $this->tmpCat = Category::PC_PHONE_ANDROID;
                $this->tmpTag[] = Category::TAG_PC_PHONE_ANDROID;
                break;
            case preg_match('/[^a-z0-9](symbian|xscale|wm5|wm6)[._ -]/i', $this->releaseName):
                $this->tmpCat = Category::PC_PHONE_OTHER;
                $this->tmpTag[] = Category::TAG_PC_PHONE_OTHER;
                break;
            default:
                return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isISO(): bool
    {
        switch (true) {
            case preg_match('/[._ -]([a-zA-Z]{2,10})?iso[ _.-]|[\-. ]([a-z]{2,10})?iso$/i', $this->releaseName):
            case preg_match('/[._ -](DYNAMiCS|INFINITESKILLS|UDEMY|kEISO|PLURALSIGHT|DIGITALTUTORS|TUTSPLUS|OSTraining|PRODEV|CBT\.Nuggets|COMPRISED)/i', $this->releaseName):
                $this->tmpCat = Category::PC_ISO;
                $this->tmpTag[] = Category::TAG_PC_ISO;

                return true;
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function is0day(): bool
    {
        switch (true) {
            case preg_match('/[._ -]exe$|[._ -](utorrent|Virtualbox)[._ -]|\b0DAY\b|incl.+crack| DRM$|>DRM</i', $this->releaseName):
            case preg_match('/[._ -]((32|64)bit|converter|i\d86|key(gen|maker)|freebsd|GAMEGUiDE|hpux|irix|linux|multilingual|Patch|Pro v\d{1,3}|portable|regged|software|solaris|template|unix|win2kxp2k3|win64|win(2k|32|64|all|dows|nt(2k)?(xp)?|xp)|win9x(me|nt)?|x(32|64|86))[._ -]/i', $this->releaseName):
            case preg_match('/\b(Adobe|auto(cad|desk)|-BEAN|Cracked|Cucusoft|CYGNUS|Divx[._ -]Plus|\.(deb|exe)|DIGERATI|FOSI|-FONT|Key(filemaker|gen|maker)|Lynda\.com|lz0|MULTiLANGUAGE|Microsoft\s*(Office|Windows|Server)|MultiOS|-(iNViSiBLE|SPYRAL|SUNiSO|UNION|TE)|v\d{1,3}.*?Pro|[._ -]v\d{1,3}[._ -]|\(x(64|86)\)|Xilisoft)\b/i', $this->releaseName):
                $this->tmpCat = Category::PC_0DAY;
                $this->tmpTag[] = Category::TAG_PC_0DAY;

                return true;
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function isMac(): bool
    {
        if (preg_match('/(\b|[._ -])mac([\.\s])?osx(\b|[\-_. ])/i', $this->releaseName)) {
            $this->tmpCat = Category::PC_MAC;
            $this->tmpTag[] = Category::TAG_PC_MAC;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isPCGame(): bool
    {
        if (preg_match('/[^a-z0-9](0x0007|ALiAS|BACKLASH|BAT|CLONECD|CPY|FAS(DOX|iSO)|FLT([._ -]|COGENT)|FLT(DOX)?|PC GAMES?|\(?(Game([sz])|GAME([SZ]))\)? ?(\(([Cc])\))|GENESIS|-GOG|-HATRED|HI2U|INLAWS|JAGUAR|MAZE|MONEY|OUTLAWS|PPTCLASSiCS|PC Game|PROPHET|RAiN|Razor1911|RELOADED|DEViANCE|PLAZA|RiTUELYPOGEiOS|[rR][iI][pP]-[uU][nN][lL][eE][aA][sS][hH][eE][dD]|Steam(\b)?Rip|SKIDROW|TiNYiSO|CODEX|SiMPLEX)[^a-z0-9]?/', $this->releaseName)) {
            $this->tmpCat = Category::PC_GAMES;
            $this->tmpTag[] = Category::TAG_PC_GAMES;

            return true;
        }

        if ($this->checkPoster('/<PC@MASTER\.RACE>/i', $this->poster, Category::PC_GAMES)) {
            return true;
        }

        return false;
    }

    //	XXX.

    /**
     * @return bool
     */
    public function isXxx(): bool
    {
        switch (true) {
            case ! preg_match('/\bXXX\b|(a\.b\.erotica|ClubSeventeen|Cum(ming|shot)|Err?oticax?|Porn(o|lation)?|Imageset|PICTURESET|JAV Uncensored|lesb(ians?|os?)|mastur(bation|e?bate)|My_Stepfather_Made_Me|nympho?|OLDER ANGELS|pictures\.erotica\.anime|sexontv|slut|Squirt|SWE6RUS|Transsexual|whore)/i', $this->releaseName):
                return false;
            case $this->isXxxPack():
            case $this->isXxxClipSD():
            case $this->isXxxSD():
            case $this->isXxxUHD():
            case $this->isXxxClipHD():
            case $this->catWebDL && $this->isXxxWEBDL():
            case $this->isXxx264():
            case $this->isXxxXvid():
            case $this->isXxxImageset():
            case $this->isXxxWMV():
            case $this->isXxxDVD():
            case $this->isXxxOther():

                return true;
            default:
                $this->tmpCat = Category::XXX_OTHER;
                $this->tmpTag[] = Category::TAG_XXX_OTHER;

                return true;
        }
    }

    /**
     * @return bool
     */
    public function isXxx264(): bool
    {
        if (preg_match('/720p|1080(hd|[ip])|[xh][^a-z0-9]?264/i', $this->releaseName) && ! preg_match('/\bwmv\b/i', $this->releaseName) && stripos($this->releaseName, 'SDX264XXX') === false) {
            $this->tmpCat = Category::XXX_X264;
            $this->tmpTag[] = Category::TAG_XXX_X264;

            return true;
        }
        if ($this->catWebDL === false && preg_match('/web[._ -]dl|web-?rip/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_X264;
            $this->tmpTag[] = Category::TAG_XXX_X264;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isXxxUHD(): bool
    {
        if (preg_match('/XXX.+(2160p)+[\w\-.]+(M[PO][V4]-(KTR|GUSH|FaiLED|SEXORS|hUSHhUSH|YAPG|WRB|NBQ|FETiSH))/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_UHD;
            $this->tmpTag[] = Category::TAG_XXX_UHD;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isXxxClipHD(): bool
    {
        if (preg_match('/^[\w\-.]+(\d{2}\.\d{2}\.\d{2}).+(720|1080)+[\w\-.]+(M[PO][V4]-(KTR|GUSH|FaiLED|SEXORS|hUSHhUSH|YAPG|TRASHBIN|WRB|NBQ|FETiSH))/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_CLIPHD;
            $this->tmpTag[] = Category::TAG_XXX_CLIPHD;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isXxxWMV(): bool
    {
        if (preg_match('/(\d{2}\.\d{2}\.\d{2})|([ex]\d{2,})|[^a-z0-9](f4v|flv|isom|(issue\.\d{2,})|mov|mp(4|eg)|multiformat|pack-|realmedia|uhq|wmv)[^a-z0-9]/i', $this->releaseName) && stripos($this->releaseName, 'SDX264XXX') === false) {
            $this->tmpCat = Category::XXX_WMV;
            $this->tmpTag[] = Category::TAG_XXX_WMV;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isXxxXvid(): bool
    {
        if (preg_match('/(b[dr]|dvd)rip|detoxication|divx|nympho|pornolation|swe6|tesoro|xvid/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_XVID;
            $this->tmpTag[] = Category::TAG_XXX_XVID;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isXxxDVD(): bool
    {
        if (preg_match('/dvdr[^i]|dvd[59]/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_DVD;
            $this->tmpTag[] = Category::TAG_XXX_DVD;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isXxxImageset(): bool
    {
        if (preg_match('/IMAGESET|PICTURESET|ABPEA/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_IMAGESET;
            $this->tmpTag[] = Category::TAG_XXX_IMAGESET;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isXxxPack(): bool
    {
        if (preg_match('/[ .]PACK[ .]/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_PACK;
            $this->tmpTag[] = Category::TAG_XXX_PACK;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isXxxOther(): bool
    {
        // If nothing else matches, then try these words.
        if (preg_match('/[._ -]Brazzers|Creampie|[._ -]JAV[._ -]|North\.Pole|^Nubiles|She[._ -]?Male|Transsexual|OLDER ANGELS/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_OTHER;
            $this->tmpTag[] = Category::TAG_XXX_OTHER;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
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
                $this->tmpTag[] = Category::TAG_XXX_CLIPSD;

                return true;
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function isXxxSD(): bool
    {
        if (preg_match('/SDX264XXX|XXX\.HR\./i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_SD;
            $this->tmpTag[] = Category::TAG_XXX_SD;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isXxxWEBDL(): bool
    {
        if (preg_match('/web[._ -]dl|web-?rip/i', $this->releaseName)) {
            $this->tmpCat = Category::XXX_WEBDL;
            $this->tmpTag[] = Category::TAG_XXX_WEBDL;

            return true;
        }

        return false;
    }

    //	Console.

    /**
     * @return bool
     */
    public function isConsole(): bool
    {
        return match (true) {
            $this->isGameNDS(), $this->isGame3DS(), $this->isGamePS3(), $this->isGamePS4(), $this->isGamePSP(), $this->isGamePSVita(), $this->isGameWiiWare(), $this->isGameWiiU(), $this->isGameWii(), $this->isGameNGC(), $this->isGameXBOX360DLC(), $this->isGameXBOX360(), $this->isGameXBOXONE(), $this->isGameXBOX(), $this->isGameOther() => true,
            default => false,
        };
    }

    /**
     * @return bool
     */
    public function isGameNDS(): bool
    {
        if (preg_match('/^NDS|[^a-zA-Z0-9]NDS|[\._-](nds|NDS)|nintendo.+[^3]n?dsi?/', $this->releaseName)) {
            if (preg_match('/\((DE|DSi(\sEnhanched)?|_NDS-|EUR?|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA?)\)/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_NDS;
                $this->tmpTag[] = Category::TAG_GAME_NDS;

                return true;
            }
            if (preg_match('/EUR|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA|\bROMS?(et)?\b/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_NDS;
                $this->tmpTag[] = Category::TAG_GAME_NDS;

                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isGame3DS(): bool
    {
        if (preg_match('/\b3DS\b[^max]|[\._-]3ds|nintendo.+3ds|[_\.]3DS-/i', $this->releaseName) && ! preg_match('/3ds max/i', $this->releaseName) && preg_match('/(EUR|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA|ASIA)/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_3DS;
            $this->tmpTag[] = Category::TAG_GAME_3DS;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isGameNGC(): bool
    {
        if (preg_match('/[\._-]N?G(AME)?C(UBE)?-/i', $this->releaseName)) {
            if (preg_match('/_(EUR?|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA?)_/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_OTHER;
                $this->tmpTag[] = Category::TAG_GAME_OTHER;

                return true;
            }
            if (preg_match('/-(((STAR|DEATH|STINKY|MOON|HOLY|G)?CUBE(SOFT)?)|(DARKFORCE|DNL|GP|ICP|iNSOMNIA|JAY|LaKiTu|METHS|NOMIS|QUBiSM|PANDORA|REACT0R|SUNSHiNE|SAVEPOiNT|SYNDiCATE|WAR3X|WRG))/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_OTHER;
                $this->tmpTag[] = Category::TAG_GAME_OTHER;

                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isGamePS3(): bool
    {
        if (preg_match('/[^e]PS3/i', $this->releaseName)) {
            if (preg_match('/ANTiDOTE|DLC|DUPLEX|EUR?|Googlecus|GOTY|\-HR|iNSOMNi|JAP|JPN|KONDIOS|\[PS3\]|PSN/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PS3;
                $this->tmpTag[] = Category::TAG_GAME_PS3;

                return true;
            }
            if (preg_match('/AGENCY|APATHY|Caravan|MULTi|NRP|NTSC|PAL|SPLiT|STRiKE|USA?|ZRY/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PS3;
                $this->tmpTag[] = Category::TAG_GAME_PS3;

                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isGamePS4(): bool
    {
        if (preg_match('/[ \(_.-]PS4[ \)_.-]/i', $this->releaseName)) {
            if (preg_match('/ANTiDOTE|DLC|DUPLEX|EUR?|Googlecus|GOTY|\-HR|iNSOMNi|JAP|JPN|KONDIOS|\[PS4\]/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PS4;
                $this->tmpTag[] = Category::TAG_GAME_PS4;

                return true;
            }
            if (preg_match('/AGENCY|APATHY|Caravan|MULTi|NRP|NTSC|PAL|SPLiT|STRiKE|USA?|WaYsTeD|ZRY/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PS4;
                $this->tmpTag[] = Category::TAG_GAME_PS4;

                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isGamePSP(): bool
    {
        if (stripos($this->releaseName, 'PSP') !== false) {
            if (preg_match('/[._ -](BAHAMUT|Caravan|EBOOT|EMiNENT|EUR?|EvoX|GAME|GHS|Googlecus|HandHeld|\-HR|JAP|JPN|KLOTEKLAPPERS|KOR|NTSC|PAL)/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PSP;
                $this->tmpTag[] = Category::TAG_GAME_PSP;

                return true;
            }
            if (preg_match('/[._ -](Dynarox|HAZARD|ITALIAN|KLB|KuDoS|LIGHTFORCE|MiRiBS|POPSTATiON|(PLAY)?ASiA|PSN|PSX2?PSP|SPANiSH|SUXXORS|UMD(RIP)?|USA?|YARR)/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_PSP;
                $this->tmpTag[] = Category::TAG_GAME_PSP;

                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isGamePSVita(): bool
    {
        if (preg_match('/PS ?Vita/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_PSVITA;
            $this->tmpTag[] = Category::TAG_GAME_PSVITA;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isGameWiiWare(): bool
    {
        if (preg_match('/(Console|DLC|VC).+[._ -]WII|(Console|DLC|VC)[._ -]WII|WII[._ -].+(Console|DLC|VC)|WII[._ -](Console|DLC|VC)|WIIWARE/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_WIIWARE;
            $this->tmpTag[] = Category::TAG_GAME_WIIWARE;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isGameWiiU(): bool
    {
        switch (true) {
            case preg_match('/[._ -](Allstars|BiOSHOCK|dumpTruck|DNi|iCON|JAP|NTSC|PAL|ProCiSiON|PROPER|RANT|REV0|SUNSHiNE|SUSHi|TMD|USA?)$/i', $this->releaseName):
            case preg_match('/[._ -](APATHY|BAHAMUT|DMZ|ERD|GAME|JPN|LoCAL|MULTi|NAGGERS|OneUp|PLAYME|PONS|Scrubbed|VORTEX|ZARD|ZER0)$/i', $this->releaseName):
            case preg_match('/[._ -](ALMoST|AMBITION|Caravan|CLiiCHE|DRYB|HaZMaT|KOR|LOADER|MARVEL|PROMiNENT|LaKiTu|LOCAL|QwiiF|RANT)$/i', $this->releaseName):
                $this->tmpCat = Category::GAME_WIIU;
                $this->tmpTag[] = Category::TAG_GAME_WIIU;

                return true;
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function isGameWii(): bool
    {
        switch (true) {
            case preg_match('/[._ -](Allstars|BiOSHOCK|dumpTruck|DNi|iCON|JAP|NTSC|PAL|ProCiSiON|PROPER|RANT|REV0|SUNSHiNE|SUSHi|TMD|USA?)/i', $this->releaseName):
            case preg_match('/[._ -](APATHY|BAHAMUT|DMZ|ERD|GAME|JPN|LoCAL|MULTi|NAGGERS|OneUp|PLAYME|PONS|Scrubbed|VORTEX|ZARD|ZER0)/i', $this->releaseName):
            case preg_match('/[._ -](ALMoST|AMBITION|Caravan|CLiiCHE|DRYB|HaZMaT|KOR|LOADER|MARVEL|PROMiNENT|LaKiTu|LOCAL|QwiiF|RANT)/i', $this->releaseName):
                $this->tmpCat = Category::GAME_WII;
                $this->tmpTag[] = Category::TAG_GAME_WII;

                return true;
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function isGameXBOX360DLC(): bool
    {
        if (preg_match('/DLC.+xbox360|xbox360.+DLC|XBLA.+xbox360|xbox360.+XBLA/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_XBOX360DLC;
            $this->tmpTag[] = Category::TAG_GAME_XBOX360DLC;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isGameXBOX360(): bool
    {
        if (stripos($this->releaseName, '/XBOX360/i') !== false) {
            $this->tmpCat = Category::GAME_XBOX360;
            $this->tmpTag[] = Category::TAG_GAME_XBOX360;

            return true;
        }
        if (stripos($this->releaseName, 'x360') !== false) {
            if (preg_match('/Allstars|ASiA|CCCLX|COMPLEX|DAGGER|GLoBAL|iMARS|JAP|JPN|MULTi|NTSC|PAL|REPACK|RRoD|RF|SWAG|USA?/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_XBOX360;
                $this->tmpTag[] = Category::TAG_GAME_XBOX360;

                return true;
            }
            if (preg_match('/DAMNATION|GERMAN|GOTY|iNT|iTA|JTAG|KINECT|MARVEL|MUX360|RANT|SPARE|SPANISH|VATOS|XGD/i', $this->releaseName)) {
                $this->tmpCat = Category::GAME_XBOX360;
                $this->tmpTag[] = Category::TAG_GAME_XBOX360;

                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isGameXBOXONE(): bool
    {
        if (preg_match('/XBOXONE|XBOX\.ONE/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_XBOXONE;
            $this->tmpTag[] = Category::TAG_GAME_XBOXONE;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isGameXBOX(): bool
    {
        if (stripos($this->releaseName, 'XBOX') !== false) {
            $this->tmpCat = Category::GAME_XBOX;
            $this->tmpTag[] = Category::TAG_GAME_XBOX;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isGameOther(): bool
    {
        if (preg_match('/\b(PS(1)X|PS2|SNES|NES|SEGA\s(GENESIS|CD)|GB([AC])|Dreamcast|SEGA\sSaturn|Atari\s(Jaguar)?|3DO)\b/i', $this->releaseName) && preg_match('/EUR|FR|GAME|HOL|\bISO\b|JP|JPN|NL|NTSC|PAL|KS|USA|ROMS?(et)?/i', $this->releaseName)) {
            $this->tmpCat = Category::GAME_OTHER;
            $this->tmpTag[] = Category::TAG_GAME_OTHER;

            return true;
        }

        return false;
    }

    //	Music.

    /**
     * @return bool
     */
    public function isMusic(): bool
    {
        return match (true) {
            $this->isMusicVideo(), $this->isAudiobook(), $this->isMusicLossless(), $this->isMusicMP3(), $this->isMusicPodcast(),$this->isMusicOther() => true,
            default => false,
        };
    }

    /**
     * @return bool
     */
    public function isMusicForeign(): bool
    {
        if ($this->categorizeForeign && preg_match('/[ \-\._](brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|ita|latin|mandarin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish|bl|cz|de|es|fr|ger|heb|hu|hun|it(a| 19|20\d\d)|jap|ko|kor|nl|pl|se)[ \-\._]/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_FOREIGN;
            $this->tmpTag[] = Category::TAG_MUSIC_FOREIGN;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isAudiobook(): bool
    {
        if ($this->categorizeForeign && stripos($this->releaseName, 'Audiobook') !== false) {
            $this->tmpCat = Category::MUSIC_FOREIGN;
            $this->tmpTag[] = Category::TAG_MUSIC_FOREIGN;

            return true;
        }

        if (str_contains($this->groupName, 'audiobook')) {
            if ($this->categorizeForeign && $this->isMusicForeign()) {
                return false;
            }
            $this->tmpCat = Category::MUSIC_AUDIOBOOK;
            $this->tmpTag[] = Category::TAG_MUSIC_AUDIOBOOK;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMusicVideo(): bool
    {
        if (preg_match('/(720P|x264)\-(19|20)\d\d\-[a-z0-9]{1,12}/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_VIDEO;
            $this->tmpTag[] = Category::TAG_MUSIC_VIDEO;

            return true;
        }
        if (preg_match('/[a-z0-9]{1,12}-(19|20)\d\d-(720P|x264)/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_VIDEO;
            $this->tmpTag[] = Category::TAG_MUSIC_VIDEO;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMusicLossless(): bool
    {
        if (preg_match('/\[(19|20)\d\d\][._ -]\[FLAC\]|([\(\[])flac([\)\]])|FLAC\-(19|20)\d\d\-[a-z0-9]{1,12}|\.flac"|(19|20)\d\d\sFLAC|[._ -]FLAC.+(19|20)\d\d[._ -]| FLAC$/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_LOSSLESS;
            $this->tmpTag[] = Category::TAG_MUSIC_LOSSLESS;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMusicMP3(): bool
    {
        if (preg_match('/[a-z0-9]{1,12}\-(19|20)\d\d\-[a-z0-9]{1,12}|[\.\-\(\[_ ]\d{2,3}k[\.\-\)\]_ ]|\((192|256|320)\)|(320|cd|eac|vbr).+mp3|(cd|eac|mp3|vbr).+320|FIH\_INT|\s\dCDs|[._ -]MP3[._ -]|MP3\-\d{3}kbps|\.(m3u|mp3)"|NMR\s\d{2,3}\skbps|\(320\)\.|\-\((Bootleg|Promo)\)|\.mp3$|\-\sMP3\s(19|20)\d\d|\(vbr\)|rip(192|256|320)|[._ -](CDR|SBD|WEB).+(19|20)\d\d/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_MP3;
            $this->tmpTag[] = Category::TAG_MUSIC_MP3;

            return true;
        }
        if (preg_match('/\s(19|20)\d\d\s([a-z0-9]{3}|[a-z]{2,})$|\-(19|20)\d\d\-(C4|MTD)([\s\.])|[._ -]FM.+MP3[._ -]|-web-(19|20)\d\d([\.\s$])|[._ -](SAT|SBD|WEB).+(19|20)\d\d([._ -]|$)|[._ -](19|20)\d\d.+(SAT|WEB)([._ -]|$)| MP3$/i', $this->releaseName)) {
            if ($this->isMusicForeign()) {
                return true;
            }
            $this->tmpCat = Category::MUSIC_MP3;
            $this->tmpTag[] = Category::TAG_MUSIC_MP3;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMusicOther(): bool
    {
        if (preg_match('/(19|20)\d\d\-(C4)$|[._ -]\d?CD[._ -](19|20)\d\d|\(\d\-?CD\)|\-\dcd\-|\d[._ -]Albums|Albums.+(EP)|Bonus.+Tracks|Box.+?CD.+SET|Discography|D\.O\.M|Greatest\sSongs|Live.+(Bootleg|Remastered)|Music.+Vol|([\(\[\s])NMR([\)\]\s])|Promo.+CD|Reggaeton|Tiesto.+Club|Vinyl\s2496|\WV\.A\.|^\(VA\s|^VA[._ -]/i', $this->releaseName)) {
            if (! $this->isMusicForeign()) {
                $this->tmpCat = Category::MUSIC_OTHER;
                $this->tmpTag[] = Category::TAG_MUSIC_OTHER;
            }

            return true;
        }
        if (preg_match('/\(pure_fm\)|-+\(?(2lp|cd[ms]([\-_ .][a-z]{2})?|cover|ep|ltd_ed|mix|original|ost|.*?(edit(ion)?|remix(es)?|vinyl)|web)\)?-+((19|20)\d\d|you$)/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_OTHER;
            $this->tmpTag[] = Category::TAG_MUSIC_OTHER;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isMusicPodcast(): bool
    {
        if (preg_match('/podcast/i', $this->releaseName)) {
            $this->tmpCat = Category::MUSIC_PODCAST;
            $this->tmpTag[] = Category::TAG_MUSIC_PODCAST;
            return true;
        }
        return false;
    }

    //	Books.

    /**
     * @return bool
     */
    public function isBook(): bool
    {
        return match (true) {
            $this->isComic(), $this->isTechnicalBook(), $this->isMagazine(), $this->isBookOther(), $this->isEBook() => true,
            default => false,
        };
    }

    /**
     * @return bool
     */
    public function isBookForeign(): bool
    {
        switch (true) {
            case preg_match('/[ \-\._](brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|ita|latin|mandarin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)[._ -]/i', $this->releaseName):
                $this->tmpCat = Category::BOOKS_FOREIGN;
                $this->tmpTag[] = Category::TAG_BOOKS_FOREIGN;

                return true;
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function isComic(): bool
    {
        switch (true) {
            case ! preg_match('/[\. ](cbr|cbz)|[\( ]c2c|cbr|cbz[\) ]|comix|^\(comic|[\.\-_\(\[ ]comics?[._ -]|comic.+book|covers.+digital|DC.+(Adventures|Universe)|digital.+(son|zone)|Graphic.+Novel|[\.\-_h ]manga|Total[._ -]Marvel/i', $this->releaseName):
                return false;
            case $this->isBookForeign():
                break;
            default:
                $this->tmpCat = Category::BOOKS_COMICS;
                $this->tmpTag[] = Category::TAG_BOOKS_COMICS;
                break;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isTechnicalBook(): bool
    {
        switch (true) {
            case ! preg_match('/^\(?(atz|bb|css|c ?t|Drawing|Gabler|IOS|Iphone|Lynda|Manning|Medic(al|ine)|MIT|No[._ -]Starch|Packt|Peachpit|Pragmatic|Revista|Servo|SmartBooks|Spektrum|Strata|Sybex|Syngress|Vieweg|Wiley|Woods|Wrox)[._ -]|[._ -](Ajax|CSS|DIY|Javascript|(My|Postgre)?SQL|XNA)[._ -]|3DS\.\-_ ]Max|Academic|Adobe|Algebra|Analysis|Appleworks|Archaeology|Bitdefender|Birkhauser|Britannica|[._ -]C\+\+|C[._ -](\+\+|Sharp|Plus)|Chemistry|Circuits|Cook(book|ing)|(Beginners?|Complete|Communications|Definitive|Essential|Hackers?|Practical|Professionals?)[._ -]Guide|Developer|Diagnostic|Disassembl(er|ing|y)|Debugg(er|ing)|Dreamweaver|Economics|Education|Electronics|Enc([iy])clopedia|Engineer(ing|s)|Essays|Exercizes|For.+Beginners|Focal[._ -]Press|For[._ -]Dummies|FreeBSD|Fundamentals[._ -]of[._ -]|(Galileo|Island)[._ -]Press|Geography|Grammar|Guide[._ -](For|To)|Hacking|Google|Handboo?k|How[._ -](It|To)|Intoduction[._ -]to|Iphone|jQuery|Lessons[._ -]In|Learning|LibreOffice|Linux|Manual|Marketing|Masonry|Mathematic(al|s)?|Medical|Microsoft|National[._ -]Academies|Nero[._ -]\d+|OReilly|OS[._ -]X[._ -]|Official[._ -]Guide|Open(GL|Office)|Pediatric|Periodic.+Table|Photoshop|Physics|Power(PC|Point|Shell)|Programm(ers?|ier||ing)|Raspberry.+Pi|Remedies|Service\s?Manual|SitePoint|Sketching|Statistics|Stock.+Market|Students|Theory|Training|Tutsplus|Ubuntu|Understanding[._ -](and|Of|The)|Visual[._ -]Studio|Textbook|VMWare|wii?max|Windows[._ -](8|7|Vista|XP)|^Wood[._ -]|Woodwork|WordPress|Work(book|shop)|Youtube/i', $this->releaseName):
                return false;
            case $this->isBookForeign():
                break;
            default:
                $this->tmpCat = Category::BOOKS_TECHNICAL;
                $this->tmpTag[] = Category::TAG_BOOKS_TECHNICAL;
                break;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isMagazine(): bool
    {
        switch (true) {
            case ! preg_match('/[a-z\-\._ ][._ -](January|February|March|April|May|June|July|August|September|October|November|December)[._ -](\d{1,2},)?20\d\d[._ -]|^\(.+[ .]\d{1,2}[ .]20\d\d[ .].+\.scr|[._ -](Catalogue|FHM|NUTS|Pictorial|Tatler|XXX)[._ -]|^\(?(Allehanda|Club|Computer([a-z0-9]+)?|Connect \d+|Corriere|ct|Diario|Digit(al)?|Esquire|FHM|Gadgets|Galileo|Glam|GQ|Infosat|Inked|Instyle|io|Kicker|Liberation|New Scientist|NGV|Nuts|Popular|Professional|Reise|Sette(tv)?|Springer|Stuff|Studentlitteratur|Vegetarian|Vegetable|Videomarkt|Wired)[._ -]|Brady(.+)?Games|Catalog|Columbus.+Dispatch|Correspondenten|Corriere[._ -]Della[._ -]Sera|Cosmopolitan|Dagbladet|Digital[._ -]Guide|Economist|Eload ?24|ExtraTime|Fatto[._ -]Quotidiano|Flight[._ -](International|Journal)|Finanzwoche|France.+Football|Foto.+Video|Games?(Master|Markt|tar|TM)|Gardening|Gazzetta|Globe[._ -]And[._ -]Mail|Guitar|Heimkino|Hustler|La.+(Lettura|Rblica|Stampa)|Le[._ -](Monde|Temps)|Les[._ -]Echos|e?Magazin(es?)?|Mac(life|welt)|Marie.+Claire|Maxim|Men.+(Health|Fitness)|Motocross|Motorcycle|Mountain[._ -]Bike|MusikWoche|National[._ -]Geographic|New[._ -]Yorker|PC([._ -](Gamer|Welt|World)|Games|Go|Tip)|Penthouse|Photograph(er|ic)|Playboy|Posten|Quotidiano|(Golf|Readers?).+Digest|SFX[._ -]UK|Recipe(.+Guide|s)|SkyNews|Sport[._ -]?Week|Strategy.+Guide|TabletPC|Tattoo[._ -]Life|The[._ -]Guardian|Tageszeitung|Tid(bits|ning)|Top[._ -]Gear[._ -]|Total[._ -]Guitar|Travel[._ -]Guides?|Tribune[._ -]De[._ -]|US[._ -]Weekly|USA[._ -]Today|TruePDF|Vogue|Verlag|Warcraft|Web.+Designer|What[._ -]Car|Zeitung/i', $this->releaseName):
                return false;
            case $this->isBookForeign():
                break;
            default:
                $this->tmpCat = Category::BOOKS_MAGAZINES;
                $this->tmpTag[] = Category::TAG_BOOKS_MAGAZINES;
                break;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isBookOther(): bool
    {
        if (preg_match('/"\d\d-\d\d-20\d\d\./', $this->releaseName)) {
            $this->tmpCat = Category::BOOKS_UNKNOWN;
            $this->tmpTag[] = Category::TAG_BOOKS_UNKNOWN;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isEBook(): bool
    {
        switch (true) {
            case ! preg_match('/^ePub|[._ -](Ebook|E?\-book|\) WW|Publishing)|[\.\-_\(\[ ](azw|epub|html|mobi|pdf|rtf|tif|txt)[\.\-_\)\] ]|[\. ](azw|doc|epub|mobi|pdf)(?![\w .])|\.ebook-\w$/i', $this->releaseName):
                return false;
            case $this->isBookForeign():
                break;
            default:
                $this->tmpCat = Category::BOOKS_EBOOK;
                $this->tmpTag[] = Category::TAG_BOOKS_EBOOK;
                break;
        }

        return true;
    }

    //	Misc, all hash/misc go in other misc.

    /**
     * @return bool
     */
    public function isMisc(): bool
    {
        switch (true) {
            case preg_match('/[a-f0-9]{32,64}/i', $this->releaseName):
                $this->tmpCat = Category::OTHER_HASHED;
                $this->tmpTag[] = Category::TAG_OTHER_HASHED;
                break;
            case preg_match('/[a-z0-9]{20,}/i', $this->releaseName):
            case preg_match('/^[A-Z0-9]{1,}$/i', $this->releaseName):
                $this->tmpCat = Category::OTHER_MISC;
                $this->tmpTag[] = Category::TAG_OTHER_MISC;
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
     * @return bool
     */
    public function checkPoster(string $regex, string $fromName, string $category): bool
    {
        if (preg_match($regex, $fromName)) {
            $this->tmpCat = $category;
            $this->tmpTag[] = $category;

            return true;
        }

        return false;
    }
}
