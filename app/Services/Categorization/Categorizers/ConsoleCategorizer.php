<?php

declare(strict_types=1);

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

/**
 * Categorizer for Console games (PlayStation, Xbox, Nintendo, etc.)
 */
class ConsoleCategorizer extends AbstractCategorizer
{
    protected int $priority = 35;

    // Console game usenet groups
    protected const GAME_GROUPS = [
        'alt.binaries.games',
        'alt.binaries.games.xbox',
        'alt.binaries.games.xbox360',
        'alt.binaries.games.wii',
        'alt.binaries.games.nintendods',
        'alt.binaries.console.ps3',
        'alt.binaries.sony.psp',
    ];

    public function getName(): string
    {
        return 'Console';
    }

    /**
     * Check if the group is a console game group.
     */
    protected function isGameGroup(string $groupName): bool
    {
        if (empty($groupName)) {
            return false;
        }

        return in_array($groupName, self::GAME_GROUPS) ||
               (bool) preg_match('/alt\.binaries\.(games|console|sony|nintendo|xbox)/i', $groupName);
    }

    public function shouldSkip(ReleaseContext $context): bool
    {
        if ($context->hasAdultMarkers()) {
            return true;
        }
        // Skip TV shows (season patterns)
        if (preg_match('/[._ -]S\d{1,3}[._ -]?(E\d|Complete|Full|1080|720|480|2160|WEB|HDTV|BluRay)/i', $context->releaseName)) {
            return true;
        }

        return false;
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;
        $groupName = $context->groupName;

        // Check if from game group
        $isFromGameGroup = $this->isGameGroup($groupName);

        // Check in order of specificity
        if ($result = $this->checkPS4($name)) {
            return $result;
        }
        if ($result = $this->checkPS3($name)) {
            return $result;
        }
        if ($result = $this->checkPSVita($name)) {
            return $result;
        }
        if ($result = $this->checkPSP($name)) {
            return $result;
        }
        if ($result = $this->checkXboxOne($name)) {
            return $result;
        }
        if ($result = $this->checkXbox360DLC($name)) {
            return $result;
        }
        if ($result = $this->checkXbox360($name)) {
            return $result;
        }
        if ($result = $this->checkXbox($name)) {
            return $result;
        }
        if ($result = $this->checkWiiU($name)) {
            return $result;
        }
        if ($result = $this->checkWiiWare($name)) {
            return $result;
        }
        if ($result = $this->checkWii($name)) {
            return $result;
        }
        if ($result = $this->checkNGC($name)) {
            return $result;
        }
        if ($result = $this->check3DS($name)) {
            return $result;
        }
        if ($result = $this->checkNDS($name)) {
            return $result;
        }
        if ($result = $this->checkOther($name)) {
            return $result;
        }

        // If from game group but no specific match, try to categorize by platform hints
        if ($isFromGameGroup) {
            return $this->categorizeByPlatformHints($name, $groupName);

        }

        return $this->noMatch();
    }

    /**
     * Fallback categorization based on platform hints for game group releases.
     */
    protected function categorizeByPlatformHints(string $name, string $groupName): CategorizationResult
    {
        // Check group name for platform hints
        if (preg_match('/xbox360/i', $groupName)) {
            return $this->matched(Category::GAME_XBOX360, 0.65, 'fallback_xbox360_group');
        }
        if (preg_match('/xbox/i', $groupName)) {
            return $this->matched(Category::GAME_XBOX, 0.65, 'fallback_xbox_group');
        }
        if (preg_match('/wii/i', $groupName)) {
            return $this->matched(Category::GAME_WII, 0.65, 'fallback_wii_group');
        }
        if (preg_match('/nintendo/i', $groupName)) {
            return $this->matched(Category::GAME_NDS, 0.65, 'fallback_nintendo_group');
        }
        if (preg_match('/ps3|playstation/i', $groupName)) {
            return $this->matched(Category::GAME_PS3, 0.65, 'fallback_ps3_group');
        }
        if (preg_match('/psp/i', $groupName)) {
            return $this->matched(Category::GAME_PSP, 0.65, 'fallback_psp_group');
        }

        // Default to GAME_OTHER
        return $this->matched(Category::GAME_OTHER, 0.5, 'fallback_game');
    }

    protected function checkPS4(string $name): ?CategorizationResult
    {
        // Check for PS4 at start of filename with underscores
        if (preg_match('/^PS4[_\.\-]/i', $name)) {
            return $this->matched(Category::GAME_PS4, 0.95, 'ps4_prefix');
        }
        // Check for CUSA pattern which is specific to PS4 games
        if (preg_match('/CUSA\d{5}/i', $name)) {
            return $this->matched(Category::GAME_PS4, 0.95, 'ps4_cusa');
        }
        // Direct check for the PS4-DUPLEX pattern at the end of the name
        if (preg_match('/\.PS4-DUPLEX$/i', $name)) {
            return $this->matched(Category::GAME_PS4, 0.95, 'ps4_duplex');
        }
        // First check if the release name suggests PlayStation 4 content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:PS4|PlayStation\s*4)|\bPS4\b|[_\.\-]PS4[_\.\-]/i', $name)) {
            return $this->matched(Category::GAME_PS4, 0.9, 'ps4');
        }
        // Check for files with Game_Full_psgames which indicates PS4 game
        if (preg_match('/Game_Full_psgames/i', $name)) {
            return $this->matched(Category::GAME_PS4, 0.85, 'ps4_psgames');
        }

        return null;
    }

    protected function checkPS3(string $name): ?CategorizationResult
    {
        if (preg_match('/(?:^|[^a-zA-Z0-9e])(?:PS3|PlayStation\s+3)|\b(?:PS3)\b|[\._-]PS3[\._-]/i', $name)) {
            if (preg_match('/\b(?:ANTiDOTE|APATHY|AGENCY|Caravan|DUPLEX|DLC|EUR?|Googlecus|GOTY|iNSOMNi|JAP|JPN|KONDIOS|MULTi|NRP|NTSC|PAL|PSN|SPLiT|STRiKE|USA?|ZRY)\b/i', $name) ||
                preg_match('/\[PS3\]|\-HR/i', $name)) {
                return $this->matched(Category::GAME_PS3, 0.9, 'ps3');
            }

            return $this->matched(Category::GAME_PS3, 0.85, 'ps3_basic');
        }

        return null;
    }

    protected function checkPSVita(string $name): ?CategorizationResult
    {
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:PS ?Vita|PlayStation\s+Vita|PSV)|\b(?:PSVita|PSVITA)\b|[\._-](?:PSV|Vita)[\._-]/i', $name)) {
            if (preg_match('/\b(?:ANTiDOTE|APATHY|Caravan|DUPLEX|DLC|EUR?|GAME|GOTY|GRiDLOCK|iNSOMNi|JAP|JPN|KONDIOS|MULTi|NTSC|PAL|PSN|SPLiT|STRiKE|USA?|VENOM|VPK)\b/i', $name) ||
                preg_match('/\b(?:3\.60|3\.65|3\.68|Vitamin|NoNpDrm|MaiDump|UNDUB|PCSE\d{5}|PCSB\d{5}|PCSG\d{5}|PCSH\d{5})\b/i', $name) ||
                preg_match('/\[PSVita\]|\(PSV\)|\.PSV$|Vita[._-]?(ROM|GAME|ISO)/i', $name)) {
                return $this->matched(Category::GAME_PSVITA, 0.9, 'psvita');
            }

            return $this->matched(Category::GAME_PSVITA, 0.85, 'psvita_basic');
        }

        return null;
    }

    protected function checkPSP(string $name): ?CategorizationResult
    {
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:PSP|PlayStation\s+Portable)|\b(?:PSP)\b|[\._-]PSP[\._-]/i', $name)) {
            if (preg_match('/\b(?:BAHAMUT|Caravan|EBOOT|EMiNENT|EUR?|EvoX|GAME|GHS|Googlecus|HandHeld|JAP|JPN|KLOTEKLAPPERS|KOR|NTSC|PAL|USA?)\b/i', $name) ||
                preg_match('/\b(?:Dynarox|HAZARD|ITALIAN|KLB|KuDoS|LIGHTFORCE|MiRiBS|POPSTATiON|(PLAY)?ASiA|PSN|PSX2?PSP|SUXXORS|UMD(RIP)?|YARR)\b/i', $name) ||
                preg_match('/\b(?:CSO|ISO)\b|\-HR|[._-](?:v\d+\.\d+)|\.(PSP)$/i', $name)) {
                return $this->matched(Category::GAME_PSP, 0.9, 'psp');
            }

            return $this->matched(Category::GAME_PSP, 0.85, 'psp_basic');
        }

        return null;
    }

    protected function checkXboxOne(string $name): ?CategorizationResult
    {
        // Check for Xbox One content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:XboxOne|XBOX\s*One|XBONE|XB1)|\b(?:XboxOne|XBOX\s*One|XBONE|XB1)\b|[\._-](?:XboxOne|XBOX\s*One|XBONE|XB1)[\._-]/i', $name)) {
            if (preg_match('/\b(?:ANYiSO|AVENGED|CODEX|COMPLEX|DAGGER|DLCS|DODI|EUR?|FitGirl|FULLGAME|Googlecus|GOTY|iNLAWS|JAP|JPN|MULTi|NTSC|PAL|REPACK|RF|RSGTACTICS|SKIDROW|TiNYiSO|SiMPLEX)\b/i', $name) ||
                preg_match('/\b(?:Enhanced\s?for\s?Xbox|Optimized\s?for\s?Series|SmartDelivery|Console\s?Exclusive|Xbox\s?Play\s?Anywhere|Game\s?Preview|Game\s?Pass|RETAIL|INTERNAL|REDEVEiL|HOODLUM|BALiSTIC)\b/i', $name) ||
                preg_match('/\[XBOX\s?(ONE|1)\]|\(XBOX\s?(ONE|1)\)|\d{5}\.[0-9]{2}[\._-]|Microsoft[\._-]Store/i', $name)) {
                return $this->matched(Category::GAME_XBOXONE, 0.9, 'xboxone');
            }

            return $this->matched(Category::GAME_XBOXONE, 0.85, 'xboxone_basic');
        }
        // Check for Xbox Series S|X content which uses same category
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Xbox\s?Series[._ -]?[SX]|XSX|XSS)|\b(?:Xbox\s?Series[._ -]?[SX]|XSX|XSS)\b|[\._-](?:Xbox\s?Series[._ -]?[SX]|XSX|XSS)[\._-]/i', $name)) {
            return $this->matched(Category::GAME_XBOXONE, 0.9, 'xbox_series');
        }
        // Legacy detection
        if (preg_match('/XBOXONE|XBOX\.ONE|XBOX[._-]?ONE/i', $name)) {
            return $this->matched(Category::GAME_XBOXONE, 0.85, 'xboxone_legacy');
        }

        return null;
    }

    protected function checkXbox360DLC(string $name): ?CategorizationResult
    {
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:DLC|XBLA|Add[._ -]?On|Expansion|Content).*(?:Xbox360|XBOX360|X360)|\b(?:Xbox360|XBOX360|X360).*(?:DLC|XBLA|Add[._ -]?On|Expansion|Content)\b|[\._-](?:DLC|XBLA)[\._-]/i', $name)) {
            if (preg_match('/\b(?:COMPLEX|REPACK|READNFO|REGION|FREE|RGH|JTAG|ARCADE|MARKETPLACE|LIVE|XBLA|Games[._ -]On[._ -]Demand|GOD|FULL|iNT|JPN|RF|NTSC|PAL|Region[._ -]Free|USA?|ASIA|EUR?|KOR|WAVE\d+|XGD\d|SWAG|CCCLX|DAGGER)\b/i', $name) ||
                preg_match('/\b(?:TU\d+|Patch|Update|v\d+\.\d+|Package|Addon|MAP[._ -]PACK|Character[._ -]Pack|Skin[._ -]Pack|Unlock|Premium|Season[._ -]Pass|Episode|Part|Pack\d+)\b/i', $name) ||
                preg_match('/\[DLC\]|\(DLC\)|Xbox[._ -]360[._ -]DLC|MSPOINTS|Microsoft[._ -]Points|\d{4}[._ -]MS[._ -]Points|XBL[._ -]Arcade/i', $name)) {
                return $this->matched(Category::GAME_XBOX360DLC, 0.9, 'xbox360_dlc');
            }
        }
        // Check standalone XBLA releases
        if (preg_match('/\bXBLA[._ -](?!x360|xbox360)|\b(?:Xbox360|XBOX360|X360)[._ -]Arcade|\bXbox[._ -]LIVE[._ -]Arcade\b/i', $name)) {
            return $this->matched(Category::GAME_XBOX360DLC, 0.85, 'xbox360_xbla');
        }

        return null;
    }

    protected function checkXbox360(string $name): ?CategorizationResult
    {
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Xbox360|XBOX360|X360)|\b(?:Xbox360|XBOX360|X360)\b|[\._-](?:Xbox360|XBOX360|X360)[\._-]/i', $name)) {
            if (preg_match('/\b(?:Allstars|ASiA|CCCLX|COMPLEX|DAGGER|GLoBAL|iMARS|JAP|JPN|MULTi|NTSC|PAL|REPACK|RRoD|RF|SWAG|USA?|REGION|FREE|WAVE\d+|XGD\d|SPARE|JTAG|iNT|FULL|MARVEL|GOD|SPARE)\b/i', $name) ||
                preg_match('/\b(?:DAMNATION|GERMAN|GOTY|iNT|iTA|JTAG|KINECT|MARVEL|MUX360|RANT|SPANISH|VATOS|XBOX360|WiiERD|XBLA|Region[._ -]Free|RGH|ISO|COMPLEX)\b/i', $name) ||
                preg_match('/\[XBOX360\]|\(XBOX360\)|XBOX[._-]360[._-]|TU\d+|Patch|Update|v\d+\.\d+|\.(iso|xex|xbla)$/i', $name)) {
                return $this->matched(Category::GAME_XBOX360, 0.9, 'xbox360');
            }
            if (preg_match('/DAMNATION|GERMAN|GOTY|iNT|iTA|JTAG|KINECT|MARVEL|MUX360|RANT|SPARE|SPANISH|VATOS|XGD/i', $name)) {
                return $this->matched(Category::GAME_XBOX360, 0.85, 'xbox360_markers');
            }

            return $this->matched(Category::GAME_XBOX360, 0.8, 'xbox360_basic');
        }

        return null;
    }

    protected function checkXbox(string $name): ?CategorizationResult
    {
        // First check if the release name suggests original Xbox content (while excluding 360/One/Series)
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:XBOX|X-BOX)(?!(?:360|ONE|Series|One))\b|\b(?:XBOX)\b(?!(?:360|ONE|Series|One))|[\._-]XBOX[\._-](?!(?:360|ONE|Series|One))/i', $name)) {
            if (preg_match('/\b(?:USA?|PAL|NTSC|JPN|JAP|RF|REGION|FREE|EUR?|ASiA|Allstars|iNT|MULTi|REPACK|PROPER|READNFO|DVD[59]?|RETAIL|ISO|RIP|GOTY|UNCUT|GERMAN|FRENCH|SPANiSH|iTALiAN|DUTCH|SWEDiSH|DANiSH|FiNNiSH|NORWEGIAN|RUSSiAN)\b/i', $name) ||
                preg_match('/\b(?:XPG|ProjectX|DAGGER|STRANGE|SWAG|DEMONZ85|PROTOCOL|ICONCLAS|DNL|DRTL|RiNGERS|ORiGiNAL|Empire|Protocol|iMARS|GOTY|Caravan|PROPHETS|1TM|WaLMaRT|Eroticl1|LaKiTu|FLS)\b/i', $name) ||
                preg_match('/\[XBOX\]|\(XBOX\)|XBOX[._-]([^3]|$)|\.(iso|xbe)$/i', $name)) {
                return $this->matched(Category::GAME_XBOX, 0.9, 'xbox');
            }
        }
        // Legacy detection with exclusion of other Xbox platforms
        if (preg_match('/\bXBOX\b/i', $name) &&
            ! preg_match('/\b(XBOX\s?360|XBOX\s?ONE|XBONE|XB1|Xbox\s?Series|XSX|XSS)\b/i', $name)) {
            return $this->matched(Category::GAME_XBOX, 0.75, 'xbox_legacy');
        }

        return null;
    }

    protected function checkWiiU(string $name): ?CategorizationResult
    {
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Wii\s*U|WiiU)|\b(?:WiiU)\b|[\._-]WiiU[\._-]|Nintendo[\._-]WiiU/i', $name)) {
            if (preg_match('/\b(?:ANTiDOTE|APATHY|ALMoST|AMBITION|Allstars|BAHAMUT|BiOSHOCK|Caravan|CLiiCHE|DMZ|DNi|DRYB|DLC|EUR?|GAME|HaZMaT|iCON|JPN|JAP|KOR|LaKiTu|LoCAL|LOADER|MARVEL|MULTi|NAGGERS|OneUp|NTSC|PAL|PLAYME|PONS|PROMiNENT|ProCiSiON|PROPER|QwiiF|RANT|REV0|Scrubbed|SUNSHiNE|SUSHi|TMD|USA?|VORTEX|ZARD|ZER0)\b/i', $name) ||
                preg_match('/\b(?:WUD|WUX|WUP-[A-Z0-9]+|eShop|LOADIINE|WUDUMP|CONSOLE-WiiU|WiiU-\w+|UPDATE|vWii|WiiVC|Virtual\s*Console)\b/i', $name) ||
                preg_match('/\[WiiU\]|\(WiiU\)|Wii\.U|Nintendo\.WiiU|15GB\+?|RETAiL|Loadiine|CFW|CEMU|NUS|Installable/i', $name)) {
                return $this->matched(Category::GAME_WIIU, 0.9, 'wiiu');
            }

            return $this->matched(Category::GAME_WIIU, 0.85, 'wiiu_basic');
        }
        // Fallback patterns
        if (preg_match('/[._ -](Allstars|BiOSHOCK|dumpTruck|DNi|iCON|JAP|NTSC|PAL|ProCiSiON|PROPER|RANT|REV0|SUNSHiNE|SUSHi|TMD|USA?)$/i', $name) ||
            preg_match('/[._ -](APATHY|BAHAMUT|DMZ|ERD|GAME|JPN|LoCAL|MULTi|NAGGERS|OneUp|PLAYME|PONS|Scrubbed|VORTEX|ZARD|ZER0)$/i', $name) ||
            preg_match('/[._ -](ALMoST|AMBITION|Caravan|CLiiCHE|DRYB|HaZMaT|KOR|LOADER|MARVEL|PROMiNENT|LaKiTu|LOCAL|QwiiF|RANT)$/i', $name)) {
            if (preg_match('/WiiU|Wii\s*U/i', $name)) {
                return $this->matched(Category::GAME_WIIU, 0.8, 'wiiu_fallback');
            }
        }

        return null;
    }

    protected function checkWiiWare(string $name): ?CategorizationResult
    {
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:WiiWare|Wii\s+Ware)|\b(?:WiiWare)\b|[\._-]Wii[\._-]?(?:Ware)|(?:Console|DLC|VC)[._ -]WII|WII[._ -](?:Console|DLC|VC)|WII[._ -].+(?:Console|DLC|VC)|(?:Console|DLC|VC).+[._ -]WII/i', $name)) {
            if (preg_match('/\b(?:PROPER|READNFO|UPDATE|REPACK|WiiERD|DNi|JAP|JPN|USA?|EUR?|PAL|NTSC|iNSOMNi|MULTi|LOADER|VENOM|WBFS|WII\d+|NRP|WWII|VORTEX|DiSONiK|DNi|DRYB)\b/i', $name) ||
                preg_match('/\b(?:VC|Virtual[._ -]Console|WiiShop|Shop[._ -]Channel|WAD|IOS\d+|eShop)\b/i', $name) ||
                preg_match('/\[Wii\]|\(Wii\)|Wii\.Point|NintendoWare|CLASSIC/i', $name)) {
                return $this->matched(Category::GAME_WIIWARE, 0.9, 'wiiware');
            }
        }

        return null;
    }

    protected function checkWii(string $name): ?CategorizationResult
    {
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Wii|Nintendo\s+Wii)|\b(?:Wii)\b|[\._-]Wii[\._-]|Nintendo[\._-]Wii/i', $name)) {
            // Exclude WiiU
            if (preg_match('/WiiU|Wii\s*U/i', $name)) {
                return null;
            }
            if (preg_match('/\b(?:ANTiDOTE|APATHY|ALMoST|AMBITION|Allstars|BAHAMUT|BiOSHOCK|Caravan|CLiiCHE|DMZ|DNi|DRYB|EUR?|GAME|GC|GCP|HaZMaT|iCON|JAP|JPN|KOR|LaKiTu|LoCAL|LOADER|MARVEL|MULTi|NAGGERS|OneUp|NTSC|PAL|PLAYME|PONS|PROMiNENT|ProCiSiON|PROPER|QwiiF|RANT|REV0|Scrubbed|SUNSHiNE|SUSHi|TMD|USA?|VORTEX|WBFS|WIIERD|ZARD|ZER0)\b/i', $name) ||
                preg_match('/\b(?:ISO|WBFS|CSO|NKit|RVZ|NAND|WAD|IOS\d+|cIOS|MODCHIP|Homebrew|DOLPHIN|vWii|Virtual[._ -]Console)\b/i', $name) ||
                preg_match('/\[Wii\]|\(Wii\)|Nintendo\.Wii|Wii\.Game|RVZ-[A-Z0-9]+|WII-\w+|READNFO|WiiGamerZ|Wii-Backup/i', $name)) {
                return $this->matched(Category::GAME_WII, 0.9, 'wii');
            }

            return $this->matched(Category::GAME_WII, 0.8, 'wii_basic');
        }

        return null;
    }

    protected function checkNGC(string $name): ?CategorizationResult
    {
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:NGC|Nintendo\s+GameCube)|\b(?:GameCube)\b|[\._-]N?G(AME)?C(UBE)?[-_\.]/i', $name)) {
            if (preg_match('/[(\_\-](?:DE|EUR?|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA?)[\)\_]/i', $name) ||
                preg_match('/\b(?:EUR?|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA?|ROMs?(et)?)\b/i', $name) ||
                preg_match('/-(?:(?:STAR|DEATH|STINKY|MOON|HOLY|G)?CUBE(?:SOFT)?|DARKFORCE|DNL|GP|ICP|iNSOMNIA|JAY|LaKiTu|METHS|NOMIS|QUBiSM|PANDORA|REACT0R|SUNSHiNE|SAVEPOiNT|SYNDiCATE|WAR3X|WRG)/i', $name)) {
                return $this->matched(Category::GAME_OTHER, 0.9, 'gamecube');
            }
        }

        return null;
    }

    protected function check3DS(string $name): ?CategorizationResult
    {
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:3DS|nintendo\s+3ds)|\b(?:3ds)\b|nintendo.+3ds|(?<!max\.)[_\.-]3DS(?![_\.-]max)/i', $name)) {
            if (preg_match('/\((DE|EUR|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA|ASIA)\)/i', $name) ||
                preg_match('/\b(EUR|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA|ASIA|ROMs?(et)?)\b/i', $name) ||
                preg_match('/\b(CIA|3DS[_\.-]?ROM|eShop|Region\s*Free)\b/i', $name)) {
                return $this->matched(Category::GAME_3DS, 0.9, '3ds');
            }

            return $this->matched(Category::GAME_3DS, 0.8, '3ds_basic');
        }

        return null;
    }

    protected function checkNDS(string $name): ?CategorizationResult
    {
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:NDS|nintendo\s+ds)|\b(?:nds|NDS)\b|nintendo.+(?<!3)(?:nds|ndsi)\b/i', $name)) {
            if (preg_match('/\((DE|DSi(?: Enhanced)?|_NDS-|EUR|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA)\)/i', $name) ||
                preg_match('/\b(EUR|FR|GAME|HOL|JP|JPN|NL|NTSC|PAL|KS|USA|ROMs?(et)?)\b/i', $name)) {
                return $this->matched(Category::GAME_NDS, 0.9, 'nds');
            }
        }

        return null;
    }

    protected function checkOther(string $name): ?CategorizationResult
    {
        // Retro/other console content
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:PS[1X]|PS2|SNES|NES|SEGA(?:\s+(?:Genesis|CD|Saturn|32X|Master\s+System))?|GB[AC]?|GameBoy(?:\s+(?:Advance|Color))?|Game\s*Boy(?:\s+(?:Advance|Color))?|Dreamcast|Saturn|Atari(?:\s+(?:Jaguar|2600|5200|7800|Lynx))?|3DO|Neo\s*Geo|N64|Nintendo\s*64|PCEngine|TurboGrafx|Intellivision|Colecovision)|\b(?:PS[1X]|PS2|SNES|NES|MAME|N64)\b|[\._-](?:PS[1X]|PS2|SNES|NES|N64)[\._-]/i', $name)) {
            if (preg_match('/\b(?:EUR?|FR|GAME|HOL|ISO|JP|JPN|NL|NTSC|PAL|KS|USA?)[\)\_]/i', $name) ||
                preg_match('/\b(?:ROMs?(et)?|ROM\s+Collection|RIP|Full\s+Set|Redump|No\s+Intro|TOSEC|GoodSet|EverDrive|Collection|Classics|Anthology|Trilogy|Compilation|Complete|Rev\s+[A-Z])\b/i', $name) ||
                preg_match('/\(([CP]|\d{2,})\)|\.(bin|chd|cue|gcm|gdi|iso|img|mdf|nrg|z64|v64|n64|md|smc|smd|fig|gb|gbc|gba|nes|sfc|gen)$/i', $name)) {
                return $this->matched(Category::GAME_OTHER, 0.85, 'retro_console');
            }

            return $this->matched(Category::GAME_OTHER, 0.7, 'retro_basic');
        }
        // Legacy detection
        if (preg_match('/\b(PS(1|One|X)|PS2|PlayStation\s+(1|2|One)|SNES|Super\s+Nintendo|NES|Nintendo\s+Entertainment\s+System|SEGA\s+(GENESIS|CD|SATURN|32X)|GB([AC])?|GameBoy(\s+(Advance|Color))?|Game\s*Boy(\s+(Advance|Color))?|Dreamcast|SEGA\s+Saturn|Atari(\s+Jaguar)?|3DO|Neo\s*Geo|N64|Nintendo\s*64)\b/i', $name) &&
            preg_match('/\b(EUR|FR|GAME|HOL|ISO|JP|JPN|NL|NTSC|PAL|KS|USA|ROMS?(et)?)\b/i', $name)) {
            return $this->matched(Category::GAME_OTHER, 0.8, 'retro_legacy');
        }

        return null;
    }
}
