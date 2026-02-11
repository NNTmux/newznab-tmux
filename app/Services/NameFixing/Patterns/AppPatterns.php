<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Patterns;

/**
 * Regex patterns for Application/Software release name matching.
 */
final class AppPatterns
{
    // ========================================================================
    // KEYGEN/CRACK PATTERNS
    // ========================================================================

    /**
     * Software with keygen/patch.
     * Example: App.Name.v1.0.Linux.Incl.Keygen-GROUP
     */
    public const WITH_KEYGEN = '/\w[\-\w.\',;& ]+(\d{1,10}|v\d+[\.\d]*|Linux|UNIX|MacOS)[._ -](RPM|DEB)?[._ -]?(X64|X86|ARM64)?[._ -]?(Incl|With)?[._ -]?(Keygen|Patch|Crack|Serial|License)[\-\w.\',;& ]+\w/i';

    // ========================================================================
    // PLATFORM-SPECIFIC PATTERNS
    // ========================================================================

    /**
     * Windows applications.
     * Example: App.Name.v1.0.WinAll-GROUP
     */
    public const WINDOWS = '/\w[\-\w.\',;& ]+\d{1,8}[._ -](winall|win32|win64|x64|x86)[._ -]?(freeware|portable|repack)?[\-\w.\',;& ]+\w/i';

    /**
     * macOS applications.
     * Example: App.Name.v1.0.MacOS-GROUP
     */
    public const MACOS = '/\w[\-\w.\',;& ]+(MacOS|Mac[._ -]?OS[._ -]?X|OSX)[._ -][\-\w.\',;& ]+\w/i';

    /**
     * Linux applications.
     * Example: App.Name.v1.0.Linux.x64-GROUP
     */
    public const LINUX = '/\w[\-\w.\',;& ]+(Linux|Ubuntu|Debian|CentOS|RHEL|Fedora)[._ -](x64|x86|arm64)?[\-\w.\',;& ]+\w/i';

    // ========================================================================
    // VENDOR-SPECIFIC PATTERNS
    // ========================================================================

    /**
     * Adobe software.
     * Example: Adobe.Photoshop.2024.v25.0-GROUP
     */
    public const ADOBE = '/\w[\-\w.\',;& ]*(Adobe|Photoshop|Illustrator|Premiere|After[._ -]?Effects|InDesign|Lightroom)[._ -][\-\w.\',;& ]+\w/i';

    /**
     * Microsoft software.
     * Example: Microsoft.Office.2021.Pro.Plus-GROUP
     */
    public const MICROSOFT = '/\w[\-\w.\',;& ]*(Microsoft|Office|Windows|Visual[._ -]?Studio)[._ -]\d{2,4}[._ -][\-\w.\',;& ]+\w/i';

    // ========================================================================
    // GENERIC PATTERNS
    // ========================================================================

    /**
     * Generic software with version.
     * Example: App.Name.v1.2.3.Multilingual-GROUP
     */
    public const WITH_VERSION = '/\w[\-\w.\',;& ]+[._ -]v?\d+[\.\d]+[._ -](Multilingual|MULTi|Portable|Repack|Cracked)[\-\w.\',;& ]+\w/i';

    // ========================================================================
    // ARCHITECTURE CONSTANTS
    // ========================================================================

    /**
     * Common architecture identifiers.
     */
    public const ARCHITECTURES = [
        'x64',
        'x86',
        'win64',
        'win32',
        'amd64',
        'arm64',
        'arm',
        'i386',
        'i686',
        'universal',
    ];

    /**
     * Common software qualifiers.
     */
    public const QUALIFIERS = [
        'Portable',
        'Repack',
        'Multilingual',
        'MULTi',
        'Cracked',
        'Registered',
        'Activated',
        'Pre-Activated',
        'Patched',
        'Fixed',
        'Retail',
        'Pro',
        'Enterprise',
        'Ultimate',
        'Professional',
    ];

    // ========================================================================
    // PATTERN COLLECTIONS
    // ========================================================================

    /**
     * Get all patterns in priority order.
     *
     * @return array<string, mixed>
     */
    public static function getAllPatterns(): array
    {
        return [
            'WITH_KEYGEN' => self::WITH_KEYGEN,
            'WINDOWS' => self::WINDOWS,
            'MACOS' => self::MACOS,
            'LINUX' => self::LINUX,
            'ADOBE' => self::ADOBE,
            'MICROSOFT' => self::MICROSOFT,
            'WITH_VERSION' => self::WITH_VERSION,
        ];
    }

    /**
     * Get platform-specific patterns.
     *
     * @return array<string, mixed>
     */
    public static function getPlatformPatterns(): array
    {
        return [
            'WINDOWS' => self::WINDOWS,
            'MACOS' => self::MACOS,
            'LINUX' => self::LINUX,
        ];
    }

    /**
     * Get vendor-specific patterns.
     *
     * @return array<string, mixed>
     */
    public static function getVendorPatterns(): array
    {
        return [
            'ADOBE' => self::ADOBE,
            'MICROSOFT' => self::MICROSOFT,
        ];
    }
}
