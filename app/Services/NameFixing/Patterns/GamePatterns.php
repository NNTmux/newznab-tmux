<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Patterns;

/**
 * Regex patterns for Game release name matching.
 *
 * Supports modern platforms and scene groups.
 */
final class GamePatterns
{
    // ========================================================================
    // MODERN CONSOLE PATTERNS
    // ========================================================================

    /**
     * Modern console releases (PS5, Xbox Series, Switch).
     * Example: Game.Name.NSW-GROUP or Game.Name.PS5-GROUP
     */
    public const MODERN_CONSOLE = '/\w[\-\w.\',;& ]+(NSW|PS[345P]|PSV|XBSX|XSX|XBOX[._ -]?SERIES[._ -]?[XS]|XBOX[._ -]?ONE|XBOX360?|WiiU?|Switch)[._ -](INTERNAL|PROPER|READNFO|READ[._ -]?NFO|MULTI\d{1,2})?[._ -]?[\-\w.\',;& ]+\-[A-Za-z0-9]+$/i';

    /**
     * Region-based game releases.
     * Example: Game.Name.EUR.PS5-GROUP
     */
    public const REGION_CONSOLE = '/\w[\-\w.\',;& ]+(ASIA|DLC|EUR|GOTY|JPN|KOR|MULTI\d{1}|NTSCU?|PAL|RF|Region[._ -]?Free|USA|XBLA)[._ -](DLC[._ -]Complete|FRENCH|GERMAN|MULTI\d{1}|PROPER|PSN|READ[._ -]?NFO|UMD)?[._ -]?(GC|NDS|NGC|PS[345P]|PSP|PSV|Switch|NSW|Wii(U)?|XBOX(360|ONE|SERIES)?|XBSX)[\-\w.\',;& ]+\w/i';

    /**
     * Console with scene group.
     * Example: Game.Name.PS5-CODEX
     */
    public const CONSOLE_SCENE_GROUP = '/\w[\-\w.\',;& ]+(GC|NDS|NGC|PS[345P]|Switch|NSW|Wii(U)?|XBOX(360|ONE|SERIES)?|XBSX)[._ -](CODEX|DUPLEX|PLAZA|SKIDROW|RELOADED|CPY|EMPRESS|RAZOR1911|HOODLUM|DARKSiDERS|FLT|TiNYiSO|ANOMALY|iNSOMNi|OneUp|STRANGE|SWAG|SKY|SUXXORS)[\-\w.\',;& ]+\w/i';

    // ========================================================================
    // PC GAME PATTERNS
    // ========================================================================

    /**
     * PC Games with scene groups.
     * Example: Game.Name-CODEX
     */
    public const PC_SCENE_GROUP = '/\w[\-\w.\',;& ]+(PC|WIN(32|64)?|MAC(OSX?)?|LINUX)[._ -]?(CODEX|SKIDROW|RELOADED|CPY|EMPRESS|RAZOR1911|HOODLUM|DARKSiDERS|FLT|GOG|PROPHET|TiNYiSO|PLAZA|P2P|SiMPLEX|rG)[\-\w.\',;& ]+\w/i';

    /**
     * DLC and Update releases.
     * Example: Game.Name.DLC-CODEX or Game.Name.Update.v1.2-PLAZA
     */
    public const DLC_UPDATE = '/\w[\-\w.\',;& ]+(DLC|Update|Patch|Hotfix)[._ -](v?\d+[\.\d]*)?[._ -]?(CODEX|SKIDROW|RELOADED|PLAZA|EMPRESS|FLT|GOG|P2P|TiNYiSO)[\-\w.\',;& ]+\w/i';

    /**
     * OUTLAWS group releases.
     * Example: Game.Name-OUTLAWS
     */
    public const OUTLAWS = '/\w[\w.\',;-].+-OUTLAWS/i';

    /**
     * ALiAS group releases.
     * Example: Game.Name-ALiAS
     */
    public const ALIAS = '/\w[\w.\',;-].+\-ALiAS/i';

    /**
     * GOG releases (DRM-free).
     * Example: Game.Name.GOG.Classic-GROUP
     */
    public const GOG = '/\w[\-\w.\',;& ]+[._ -]GOG[._ -]?(Classic|Galaxy)?[\-\w.\',;& ]+\w/i';

    /**
     * REPACK releases.
     * Example: Game.Name.REPACK-FitGirl
     */
    public const REPACK = '/\w[\-\w.\',;& ]+[._ -](REPACK|RIP)[._ -](FitGirl|DODI|xatab|R\.G\.|Mechanics)[\-\w.\',;& ]+\w/i';

    // ========================================================================
    // SCENE GROUP LIST
    // ========================================================================

    /**
     * Known PC game scene groups.
     */
    public const SCENE_GROUPS = [
        'CODEX',
        'SKIDROW',
        'RELOADED',
        'CPY',
        'EMPRESS',
        'RAZOR1911',
        'HOODLUM',
        'DARKSiDERS',
        'FLT',
        'GOG',
        'PROPHET',
        'TiNYiSO',
        'PLAZA',
        'P2P',
        'SiMPLEX',
        'rG',
        'ANOMALY',
        'iNSOMNi',
        'OneUp',
        'STRANGE',
        'SWAG',
        'SKY',
        'SUXXORS',
        'DOGE',
        'TENOKE',
        'RUNE',
    ];

    /**
     * Modern platforms.
     */
    public const PLATFORMS = [
        'NSW' => 'Nintendo Switch',
        'PS5' => 'PlayStation 5',
        'PS4' => 'PlayStation 4',
        'PS3' => 'PlayStation 3',
        'PSP' => 'PlayStation Portable',
        'PSV' => 'PlayStation Vita',
        'XBSX' => 'Xbox Series X/S',
        'XSX' => 'Xbox Series X',
        'XBOX ONE' => 'Xbox One',
        'XBOX360' => 'Xbox 360',
        'Switch' => 'Nintendo Switch',
        'WiiU' => 'Wii U',
        'Wii' => 'Wii',
        'NDS' => 'Nintendo DS',
        '3DS' => 'Nintendo 3DS',
        'PC' => 'PC',
        'MAC' => 'macOS',
        'LINUX' => 'Linux',
    ];

    // ========================================================================
    // PATTERN COLLECTIONS
    // ========================================================================

    /**
     * Get all patterns in priority order.
     */
    public static function getAllPatterns(): array
    {
        return [
            'MODERN_CONSOLE' => self::MODERN_CONSOLE,
            'REGION_CONSOLE' => self::REGION_CONSOLE,
            'CONSOLE_SCENE_GROUP' => self::CONSOLE_SCENE_GROUP,
            'PC_SCENE_GROUP' => self::PC_SCENE_GROUP,
            'DLC_UPDATE' => self::DLC_UPDATE,
            'OUTLAWS' => self::OUTLAWS,
            'ALIAS' => self::ALIAS,
            'GOG' => self::GOG,
            'REPACK' => self::REPACK,
        ];
    }

    /**
     * Get console patterns only.
     */
    public static function getConsolePatterns(): array
    {
        return [
            'MODERN_CONSOLE' => self::MODERN_CONSOLE,
            'REGION_CONSOLE' => self::REGION_CONSOLE,
            'CONSOLE_SCENE_GROUP' => self::CONSOLE_SCENE_GROUP,
        ];
    }

    /**
     * Get PC game patterns only.
     */
    public static function getPCPatterns(): array
    {
        return [
            'PC_SCENE_GROUP' => self::PC_SCENE_GROUP,
            'DLC_UPDATE' => self::DLC_UPDATE,
            'OUTLAWS' => self::OUTLAWS,
            'ALIAS' => self::ALIAS,
            'GOG' => self::GOG,
            'REPACK' => self::REPACK,
        ];
    }
}

