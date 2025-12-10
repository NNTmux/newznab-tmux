<?php

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

/**
 * Categorizer for Adult/XXX content.
 */
class XxxCategorizer extends AbstractCategorizer
{
    protected int $priority = 10; // High priority - should run early

    // Known adult studios/sites - comprehensive list
    protected const KNOWN_STUDIOS = 'Brazzers|NaughtyAmerica|RealityKings|Bangbros|BangBros18|TeenFidelity|PornPros|SexArt|WowGirls|Vixen|Blacked|Tushy|Deeper|Bellesa|Defloration|MetArt|MetArtX|TheLifeErotic|VivThomas|JoyMii|Nubiles|NubileFilms|Anilos|FamilyStrokes|X-Art|Babes|Twistys|WetAndPuffy|WowPorn|MomsTeachSex|Mofos|BangBus|Passion-HD|EvilAngel|DorcelClub|Private|Hustler|CherryPimps|PureTaboo|LadyLyne|TeamSkeet|GirlsWay|SweetSinner|NewSensations|Digital[._ -]?Playground|Wicked|Penthouse|Playboy|Kink|HardX|ArchAngel|JulesJordan|ManuelFerrara|LesbianX|AllAnal|DarkX|Elegant[._ -]?Angel|ZeroTolerance|Score|PornFidelity|Kelly[._ -]?Madison|DDF[._ -]?Network|21Sextury|21Naturals|Colette|SexMex|Bang|SpankBang|PornWorld|LegalPorno|AnalVids|GonzoXXX|RoccoSiffredi|Fake[._ -]?Hub|FakeAgent|FakeTaxi|FakeHostel|PublicAgent|StrandedTeens|Property[._ -]?Sex|Dane[._ -]?Jones|Lets[._ -]?Doe[._ -]?It|Office[._ -]?Obsession|SexyHub|Massage[._ -]?Rooms|Fitness[._ -]?Rooms|Female[._ -]?Agent|MissaX|All[._ -]?Girl[._ -]?Massage|Fantasy[._ -]?Massage|Nurumassage|Soapymassage|Reality[._ -]?Junkies|Perv[._ -]?Mom|Bad[._ -]?Milfs|Milf[._ -]?Body|Step[._ -]?Siblings|Sis[._ -]?Loves[._ -]?Me|Brother[._ -]??Crush|Dad[._ -]?Crush|Mom[._ -]?Knows[._ -]?Best|Bratty[._ -]?Sis|My[._ -]?Family[._ -]?Pies|Family[._ -]?Therapy|Nubiles[._ -]?Porn|Step[._ -]?Fantasy|Caught[._ -]?Fapping|She[._ -]?Will[._ -]?Cheat|Dirty[._ -]?Wives[._ -]?Club|Big[._ -]?Tits[._ -]?Round[._ -]?Asses|Ass[._ -]?Parade|Monsters[._ -]?Of[._ -]?Cock|Brown[._ -]?Bunnies|Teens[._ -]?Love[._ -]?Huge[._ -]?Cocks|Ass[._ -]?Masterpiece|Bang[._ -]?Casting|Holed|Tiny4K|Lubed|POVD|Exotic4K|CastingCouch[._ -]?X|Casting[._ -]?Couch|Creampie[._ -]?Angels|Digital[._ -]?Desire|Femjoy|Hegre|Joymii|Met[._ -]?Art|MPL[._ -]?Studios|Rylsky[._ -]?Art|Showy[._ -]?Beauty|Stunning18|Photodromm|Watch4Beauty|Wow[._ -]?Girls|Yonitale|Mommys[._ -]?Boy|AllOver30|MyFirst|10musume|Caribbeancom|Heyzo|Pacopacomama|1Pondo|TokyoHot';

    // Adult keywords
    protected const ADULT_KEYWORDS = 'Anal|Ass|BBW|BDSM|Blow|Boob|Bukkake|Casting|Couch|Cock|Compilation|Creampie|Cum|Dick|Dildo|Facial|Fetish|Fuck|Gang|Hardcore|Homemade|Horny|Interracial|Lesbian|MILF|Masturbat|Nympho|Oral|Orgasm|Penetrat|Pornstar|POV|Pussy|Riding|Seduct|Sex|Shaved|Slut|Squirt|Suck|Swallow|Threesome|Tits|Titty|Toy|Virgin|Whore';

    // VR sites
    protected const VR_SITES = 'SexBabesVR|LittleCapriceVR|VRoomed|VRMagic|TonightsGirlfriend|NaughtyAmericaVR|BaDoinkVR|WankzVR|VRBangers|StripzVR|RealJamVR|TmwVRnet|MilfVR|KinkVR|CzechVR(?:Fetish)?|HoloGirlsVR|WetVR|XSinsVR|VRCosplayX|BIBIVR|SLR|SexLikeReal';

    public function getName(): string
    {
        return 'XXX';
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;

        // Check if it looks like adult content
        if (!$this->looksLikeXxx($name)) {
            return $this->noMatch();
        }

        // Try specific XXX subcategories in order of specificity
        if ($result = $this->checkOnlyFans($name)) {
            return $result;
        }

        if ($result = $this->checkVR($name)) {
            return $result;
        }

        if ($result = $this->checkUHD($name)) {
            return $result;
        }

        if ($result = $this->checkClipHD($name)) {
            return $result;
        }

        if ($result = $this->checkPack($name)) {
            return $result;
        }

        if ($result = $this->checkClipSD($name, $context->poster)) {
            return $result;
        }

        if ($result = $this->checkSD($name)) {
            return $result;
        }

        if ($context->catWebDL && ($result = $this->checkWebDL($name))) {
            return $result;
        }

        if ($result = $this->checkX264($name)) {
            return $result;
        }

        if ($result = $this->checkXvid($name)) {
            return $result;
        }

        if ($result = $this->checkImageset($name)) {
            return $result;
        }

        if ($result = $this->checkWMV($name)) {
            return $result;
        }

        if ($result = $this->checkDVD($name)) {
            return $result;
        }

        if ($result = $this->checkOther($name)) {
            return $result;
        }

        return $this->noMatch();
    }

    /**
     * Check if release name looks like adult content.
     */
    protected function looksLikeXxx(string $name): bool
    {
        // Check for XXX marker
        if (preg_match('/\bXXX\b/i', $name)) {
            return true;
        }

        // Check for known studios/sites
        if (preg_match('/\b(' . self::KNOWN_STUDIOS . ')\b/i', $name)) {
            return true;
        }

        // Check for known VR sites
        if (preg_match('/\b(' . self::VR_SITES . ')\b/i', $name)) {
            return true;
        }

        // Check for adult content indicators combined with video markers
        if (preg_match('/\b(' . self::ADULT_KEYWORDS . ')\b/i', $name) &&
            preg_match('/\b(720p|1080p|2160p|4k|mp4|mkv|avi|wmv)\b/i', $name)) {
            return true;
        }

        // Check for JAV/AV marker (common in Japanese adult releases)
        if (preg_match('/\b(AV|JAV)\b/', $name) && preg_match('/\b(' . self::KNOWN_STUDIOS . ')\b/i', $name)) {
            return true;
        }

        // Site with date pattern: sitename.YYYY.MM.DD or sitename.YY.MM.DD
        // This pattern is very common for adult sites but rare for regular content
        if (preg_match('/^[A-Za-z]+[.\-_ ](19|20)?\d{2}[.\-_ ]\d{2}[.\-_ ]\d{2}[.\-_ ][A-Za-z]/i', $name)) {
            // Check it's not a TV daily show by checking for adult keywords or specific patterns
            if (preg_match('/\b(' . self::ADULT_KEYWORDS . ')\b/i', $name)) {
                return true;
            }
            // Check for performer name patterns (firstname.lastname) after the date
            if (preg_match('/\d{2}[.\-_ ]([a-z]+)[.\-_ ]([a-z]+)[.\-_ ]/i', $name)) {
                // Has a "firstname.lastname" pattern after date - likely adult
                // But exclude obvious TV patterns
                if (!preg_match('/\b(S\d{1,2}E\d{1,2}|Episode|Season|HDTV|PDTV)\b/i', $name)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function checkOnlyFans(string $name): ?CategorizationResult
    {
        // Skip photo packs unless there's a video hint
        if (preg_match('/\b(photo(set)?|image(set)?|pics?|wallpapers?|collection|pack)\b/i', $name) &&
            !preg_match('/\b(mp4|mkv|mov|wmv|avi|webm|h\.?264|x264|h\.?265|x265)\b/i', $name)) {
            return null;
        }

        if (preg_match('/\bOnly[-_ ]?Fans\b|^OF\./i', $name)) {
            return $this->matched(Category::XXX_ONLYFANS, 0.95, 'onlyfans');
        }

        return null;
    }

    protected function checkVR(string $name): ?CategorizationResult
    {
        if (stripos($name, 'vr') === false && stripos($name, 'oculus') === false && stripos($name, 'quest') === false) {
            return null;
        }

        // Check for known VR site
        $hasVRSite = preg_match('/\b(' . self::VR_SITES . ')\b/i', $name);

        // Require either a VR site token, explicit VR180/VR360, or VR device
        if (!preg_match('/\bVR(?:180|360)\b/i', $name) &&
            !$hasVRSite &&
            !preg_match('/\b(?:GearVR|Oculus|Quest[123]?|PSVR|Vive|Index|Pimax)\b/i', $name)) {
            return null;
        }

        // VR pattern matching - includes VR devices
        $vrPattern = '/\b(' . self::VR_SITES . ')\b|\bVR(?:180|360)\b|\b(?:5K|6K|7K|8K)\b.*\bVR\b|\b(?:GearVR|Oculus|Quest[123]?|PSVR|Vive|Index|Pimax)\b/i';

        if (preg_match($vrPattern, $name)) {
            // VR sites are definitively adult content
            if ($hasVRSite) {
                return $this->matched(Category::XXX_VR, 0.95, 'vr_site');
            }
            // VR device with adult keywords
            if (preg_match('/\bXXX\b/i', $name) || preg_match('/\b(' . self::ADULT_KEYWORDS . ')\b/i', $name)) {
                return $this->matched(Category::XXX_VR, 0.9, 'vr_device');
            }
        }

        return null;
    }

    protected function checkUHD(string $name): ?CategorizationResult
    {
        if (!preg_match('/\b(2160p|4k|UHD|Ultra[._ -]?HD)\b/i', $name)) {
            return null;
        }

        // Check for adult markers
        $hasAdultMarker = preg_match('/\bXXX\b/i', $name) ||
                          preg_match('/\b(' . self::KNOWN_STUDIOS . ')\b/i', strtolower($name)) ||
                          preg_match('/\b(Hardcore|Porn|Sex|Anal|Creampie|MILF|Lesbian|Teen|Interracial)\b/i', $name);

        if (!$hasAdultMarker) {
            return null;
        }

        // Known UHD release groups
        if (preg_match('/XXX.+2160p[\w\-.]+M[PO][V4]-(KTR|GUSH|FaiLED|SEXORS|hUSHhUSH|YAPG|WRB|NBQ|FETiSH)/i', $name)) {
            return $this->matched(Category::XXX_UHD, 0.95, 'uhd_group');
        }

        return $this->matched(Category::XXX_UHD, 0.9, 'uhd');
    }

    protected function checkClipHD(string $name): ?CategorizationResult
    {
        // Exclude packs and collections
        if (preg_match('/^(Complete|Pack|Collection|Anthology|Siterip|SiteRip)\b/i', $name)) {
            return null;
        }

        // Exclude TV shows
        if (preg_match('/\b(S\d{1,2}E\d{1,2}|S\d{1,2}|Season\s\d{1,2})\b/i', $name)) {
            return null;
        }

        // Check for HD resolution
        $hasHD = preg_match('/\b(720p|1080p|2160p|HD|4K)\b/i', $name);

        // Studio + performer + HD resolution
        if (preg_match('/^(' . self::KNOWN_STUDIOS . ')\.([A-Z][a-z]+).*?(720p|1080p|2160p|HD|4K)/i', $name)) {
            return $this->matched(Category::XXX_CLIPHD, 0.9, 'clip_hd_studio');
        }

        // Known studio with date pattern: site.YYYY.MM.DD or site.YY.MM.DD
        if (preg_match('/^(' . self::KNOWN_STUDIOS . ')[.\-_ ](19|20)?\d{2}[.\-_ ]\d{2}[.\-_ ]\d{2}/i', $name)) {
            if ($hasHD) {
                return $this->matched(Category::XXX_CLIPHD, 0.95, 'clip_hd_studio_date');
            }
            // Even without HD marker, if it's a known studio with date pattern, likely XXX
            return $this->matched(Category::XXX_X264, 0.85, 'studio_date');
        }

        // Date pattern with 4-digit year: site.YYYY.MM.DD.performer.title.1080p
        if (preg_match('/^([A-Z][a-zA-Z0-9]+)[.\-_ ](19|20)\d{2}[.\-_ ]\d{2}[.\-_ ]\d{2}[.\-_ ]/i', $name) &&
            !preg_match('/\b(S\d{2}E\d{2}|Documentary|Series)\b/i', $name)) {
            // Check if it has adult keywords or HD resolution
            if ($hasHD || preg_match('/\b(' . self::ADULT_KEYWORDS . ')\b/i', $name)) {
                return $this->matched(Category::XXX_CLIPHD, 0.85, 'clip_hd_date_4digit');
            }
        }

        // Date pattern with 2-digit year: site.YY.MM.DD.performer.title.1080p
        if (preg_match('/^([A-Z][a-zA-Z0-9]+)\.(\d{2})\.(\d{2})\.(\d{2})\..*?(720p|1080p|2160p|HD|4K)/i', $name) &&
            !preg_match('/\b(S\d{2}E\d{2}|Documentary|Series)\b/i', $name)) {
            return $this->matched(Category::XXX_CLIPHD, 0.85, 'clip_hd_date');
        }

        // JAV compact date pattern: site.YYMMDD (e.g., 10musume.121025)
        if (preg_match('/^(' . self::KNOWN_STUDIOS . ')[.\-_ ](\d{6})/i', $name)) {
            if ($hasHD) {
                return $this->matched(Category::XXX_CLIPHD, 0.9, 'clip_hd_jav_date');
            }
            return $this->matched(Category::XXX_X264, 0.85, 'jav_date');
        }

        // Known studio with XXX marker and HD resolution
        if (preg_match('/^(' . self::KNOWN_STUDIOS . ')[.\-_ ].*\bXXX\b.*?(720p|1080p|2160p|HD|4K)/i', $name)) {
            return $this->matched(Category::XXX_CLIPHD, 0.9, 'clip_hd_studio_xxx');
        }

        // XXX with HD resolution
        if (preg_match('/\b(XXX|MILF|Anal|Sex|Porn)[._ -]+(720p|1080p|2160p|HD|4K)\b/i', $name) ||
            preg_match('/\b(720p|1080p|2160p|HD|4K)[._ -]+(XXX|MILF|Anal|Sex|Porn)\b/i', $name)) {
            return $this->matched(Category::XXX_CLIPHD, 0.8, 'clip_hd_xxx');
        }

        return null;
    }

    protected function checkPack(string $name): ?CategorizationResult
    {
        if (preg_match('/[ .]PACK[ .]/i', $name)) {
            return $this->matched(Category::XXX_PACK, 0.85, 'pack');
        }

        return null;
    }

    protected function checkClipSD(string $name, string $poster): ?CategorizationResult
    {
        if (preg_match('/anon@y[.]com|@md-hobbys[.]com|oz@lot[.]com/i', $poster)) {
            return $this->matched(Category::XXX_CLIPSD, 0.85, 'clip_sd_poster');
        }

        if (preg_match('/(iPT\sTeam|KLEENEX)/i', $name) || stripos($name, 'SDPORN') !== false) {
            return $this->matched(Category::XXX_CLIPSD, 0.85, 'clip_sd');
        }

        return null;
    }

    protected function checkSD(string $name): ?CategorizationResult
    {
        if (preg_match('/SDX264XXX|XXX\.HR\./i', $name)) {
            return $this->matched(Category::XXX_SD, 0.85, 'sd');
        }

        return null;
    }

    protected function checkWebDL(string $name): ?CategorizationResult
    {
        // Exclude TV shows
        if (preg_match('/\b(S\d{1,2}E\d{1,2})\b/i', $name)) {
            return null;
        }

        if (preg_match('/web[._ -]dl|web-?rip/i', $name) &&
            (preg_match('/\b(' . self::ADULT_KEYWORDS . ')\b/i', $name) ||
             preg_match('/\b(' . self::KNOWN_STUDIOS . ')\b/i', $name) ||
             preg_match('/\b(XXX|Porn|Adult|JAV|Hentai)\b/i', $name))) {
            return $this->matched(Category::XXX_WEBDL, 0.85, 'webdl');
        }

        return null;
    }

    protected function checkX264(string $name): ?CategorizationResult
    {
        // Exclude HEVC/x265
        if (preg_match('/\b(x265|hevc)\b/i', $name)) {
            return null;
        }

        // Require H.264/x264/AVC
        if (!preg_match('/\b((x|h)[\.\-_ ]?264|AVC)\b/i', $name)) {
            return null;
        }

        // Reject obvious non-targets
        if (preg_match('/\bwmv\b|S\d{1,2}E\d{1,2}|\d+x\d+/i', $name)) {
            return null;
        }

        // Check for adult content
        $adultPattern = '/\bXXX\b|a\.b\.erotica|BangBros|Cum|Defloration|Err?oticax?|JoyMii|MetArt|Nubiles|Porn|SexArt|Tushy|Vixen|JAV|Brazzers|NaughtyAmerica|RealityKings/i';

        if (preg_match($adultPattern, $name)) {
            return $this->matched(Category::XXX_X264, 0.85, 'x264');
        }

        return null;
    }

    protected function checkXvid(string $name): ?CategorizationResult
    {
        if (preg_match('/(b[dr]|dvd)rip|detoxication|divx|nympho|pornolation|swe6|tesoro|xvid/i', $name)) {
            return $this->matched(Category::XXX_XVID, 0.8, 'xvid');
        }

        return null;
    }

    protected function checkImageset(string $name): ?CategorizationResult
    {
        if (preg_match('/IMAGESET|PICTURESET|ABPEA/i', $name)) {
            return $this->matched(Category::XXX_IMAGESET, 0.9, 'imageset');
        }

        return null;
    }

    protected function checkWMV(string $name): ?CategorizationResult
    {
        // Exclude modern formats
        if (preg_match('/\b(720p|1080p|2160p|x264|x265|h264|h265|hevc|XviD|MP4-|\.mp4)[._ -]/i', $name)) {
            return null;
        }

        if (preg_match('/\b(WMV|Windows\s?Media\s?Video)\b|\.wmv$|[._ -]wmv[._ -]/i', $name)) {
            return $this->matched(Category::XXX_WMV, 0.8, 'wmv');
        }

        return null;
    }

    protected function checkDVD(string $name): ?CategorizationResult
    {
        if (preg_match('/dvdr[^i]|dvd[59]/i', $name)) {
            return $this->matched(Category::XXX_DVD, 0.85, 'dvd');
        }

        return null;
    }

    protected function checkOther(string $name): ?CategorizationResult
    {
        if (preg_match('/[._ -]Brazzers|Creampie|[._ -]JAV[._ -]|North\.Pole|She[._ -]?Male|Transsexual|OLDER ANGELS/i', $name)) {
            return $this->matched(Category::XXX_OTHER, 0.7, 'other');
        }

        return null;
    }
}

