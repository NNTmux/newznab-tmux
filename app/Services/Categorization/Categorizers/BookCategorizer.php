<?php

declare(strict_types=1);

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

/**
 * Categorizer for Book content (Comics, Technical, Magazines, eBooks)
 */
class BookCategorizer extends AbstractCategorizer
{
    protected int $priority = 45;

    // Comic groups from Categorize.php
    protected const COMIC_GROUPS = ['alt.binaries.comics.dcp', 'alt.binaries.comics', 'alt.binaries.pictures.comics.dcp'];

    // Ebook groups
    protected const EBOOK_GROUPS = [
        'alt.binaries.e-book',
        'alt.binaries.ebook',
        'alt.binaries.e-books',
        'alt.binaries.ebooks',
    ];

    public function getName(): string
    {
        return 'Book';
    }

    /**
     * Check if the group is a comic group.
     */
    protected function isComicGroup(string $groupName): bool
    {
        if (empty($groupName)) {
            return false;
        }

        return in_array($groupName, self::COMIC_GROUPS) ||
               (bool) preg_match('/comics?/i', $groupName);
    }

    /**
     * Check if the group is an ebook group.
     */
    protected function isEbookGroup(string $groupName): bool
    {
        if (empty($groupName)) {
            return false;
        }

        return in_array($groupName, self::EBOOK_GROUPS) ||
               (bool) preg_match('/e-?books?/i', $groupName);
    }

    public function shouldSkip(ReleaseContext $context): bool
    {
        $name = $context->releaseName;

        if ($context->hasAdultMarkers()) {
            return true;
        }
        // The archive is known to hold a PDF or ebook, so name-based video and music guards don't apply.
        if ($context->hasDocumentContent()) {
            return false;
        }
        // Skip content from erotica groups.
        if (preg_match('/erotica/i', $context->groupName)) {
            return true;
        }
        if (preg_match('/\.PS4-[A-Z0-9]+$/i', $name)) {
            return true;
        }
        if (preg_match('/\b(?:PS[1-5]|PlayStation|Xbox|Switch|Nintendo|Wii|3DS|GameCube)\b/i', $name)) {
            return true;
        }
        if (preg_match('/\b(?:PPSA\d{4,6}|CUSA\d{5}|XCI|NSP|PKG)\b/i', $name)) {
            return true;
        }
        // Skip software and utility release naming patterns.
        if (preg_match('/\bv?\d+(?:\.\d+){1,4}\b.*\b(?:Multilingual|x64|x86|Portable|Setup|Patch|Install(?:er)?|Crack(?:ed)?|Keygen|Serial|Regged|Pre-?Activated)\b/i', $name)) {
            return true;
        }
        if ($this->hasSoftwareMarkers($name)) {
            return true;
        }
        // Skip music-style release names that are often misfiled as books.
        if (preg_match('/\b(?:WEB[._ -]?FLAC|FLAC|MP3|M4A|M4B|AAC|OGG|ALAC|WAV|LOSSLESS|320kbps|256kbps|192kbps|128kbps|VBR|CBR|discography|FALCON|OST|Soundtrack|Vinyl[._ -]?Rip|CD[._ -]?Rip)\b/i', $name)) {
            return true;
        }
        // Skip video course/training releases that often include "tutorial" language.
        if (preg_match('/\b(?:Udemy|Pluralsight|Lynda\.?com|Coursera|LinkedIn[._ -]?Learning|Skillshare|MasterClass|CBT[._ -]?Nuggets|Digital-?Tutors)\b/i', $name)) {
            return true;
        }
        // Skip video media signatures.
        if ($this->hasVideoReleaseMarkers($name)) {
            return true;
        }
        // Skip distro/iso images and generic media images.
        if (preg_match('/\b(?:Ubuntu|Debian|Fedora|Kali|Arch(?:Linux)?|CentOS|OpenSUSE|LinuxMint)\b.*\b(?:ISO|img|amd64|x86_64|installer)\b/i', $name)) {
            return true;
        }
        if (preg_match('/\b(?:ISO|IMG|DMG|bootable)\b/i', $name)) {
            return true;
        }
        // Skip font packs and typeface archives.
        if (preg_match('/\b(?:Fonts?|Typeface|OpenType|TrueType)\b/i', $name) ||
            preg_match('/\.(?:otf|ttf|woff2?)$/i', $name)) {
            return true;
        }
        // Skip TV shows (season patterns)
        if (preg_match('/^S\d{1,3}[._ -]?E\d{1,3}|[._ -]S\d{1,3}[._ -]?(E\d|Complete|Full|1080|720|480|2160|WEB|HDTV|BluRay)/i', $name)) {
            return true;
        }
        // Skip movies (year + quality patterns)
        if (preg_match('/\b(19|20)\d{2}\b.*\b(1080p|720p|2160p|BluRay|WEB-DL|BDRip|DVDRip)\b/i', $name)) {
            return true;
        }

        return false;
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;
        $groupName = $context->groupName;

        // Check if from book-related group
        $isFromComicGroup = $this->isComicGroup($groupName);
        $isFromEbookGroup = $this->isEbookGroup($groupName);

        // Check for comic group first
        if ($isFromComicGroup) {
            // Try to get more specific comic categorization
            if ($result = $this->checkComic($name, $context->categorizeForeign)) {
                return $result;
            }

            // Fallback to comics category
            return $this->matched(Category::BOOKS_COMICS, 0.7, 'fallback_comic_group');
        }

        if ($result = $this->checkComic($name, $context->categorizeForeign)) {
            return $result;
        }
        if ($result = $this->checkTechnical($name, $context->categorizeForeign)) {
            return $result;
        }
        if ($result = $this->checkMagazine($name, $context->categorizeForeign)) {
            return $result;
        }
        if ($result = $this->checkEbook($name, $context->categorizeForeign)) {
            return $result;
        }
        if ($result = $this->checkBookOther($name, $context->categorizeForeign)) {
            return $result;
        }

        // If from ebook group but no specific match, categorize as ebook
        if ($isFromEbookGroup) {
            return $this->categorizeByFormat($name, $context->categorizeForeign);
        }

        if ($context->hasDocumentContent()) {
            return $this->categorizeDocumentContent($context);
        }

        return $this->noMatch();
    }

    /**
     * An NZBSPLIT archive holding a PDF/ebook whose title names no book format (`World.Soccer.June.2026`).
     */
    protected function categorizeDocumentContent(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;

        if (in_array(strtolower((string) $context->contentExtension), ['cbr', 'cbz'], true)) {
            return $this->matched(Category::BOOKS_COMICS, 0.85, 'nzbsplit_document_comic');
        }

        if (preg_match('/\b(?:Magazine|Issue|Weekly|Monthly|Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|June?|July?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\b/i', $name)) {
            return $this->matched(Category::BOOKS_MAGAZINES, 0.85, 'nzbsplit_document_magazine');
        }

        return $this->matched(Category::BOOKS_EBOOK, 0.8, 'nzbsplit_document_ebook');
    }

    /**
     * Fallback categorization based on format indicators for ebook group releases.
     */
    protected function categorizeByFormat(string $name, bool $categorizeForeign): CategorizationResult
    {
        // Check for comic formats
        if (preg_match('/\.(cbr|cbz)$|\b(CBR|CBZ|Comic)\b/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.65, 'fallback_comic_foreign');
            }

            return $this->matched(Category::BOOKS_COMICS, 0.65, 'fallback_comic');
        }

        // Check for magazine indicators
        if (preg_match('/\b(Magazine|Issue|Vol\.\s*\d+|Weekly|Monthly)\b/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.65, 'fallback_magazine_foreign');
            }

            return $this->matched(Category::BOOKS_MAGAZINES, 0.65, 'fallback_magazine');
        }

        // Check for technical book indicators
        if (preg_match('/\b(Programming|Tutorial|Manual|Guide|Handbook|Reference)\b/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.65, 'fallback_technical_foreign');
            }

            return $this->matched(Category::BOOKS_TECHNICAL, 0.65, 'fallback_technical');
        }

        // Default to ebook
        if ($this->checkForeign($name, $categorizeForeign)) {
            return $this->matched(Category::BOOKS_FOREIGN, 0.5, 'fallback_ebook_foreign');
        }

        return $this->matched(Category::BOOKS_EBOOK, 0.5, 'fallback_ebook');
    }

    protected function checkForeign(string $name, bool $categorizeForeign): bool
    {
        if (! $categorizeForeign) {
            return false;
        }
        // Full language names
        $fullLanguages = 'arabic|brazilian|bulgarian|cantonese|chinese|croatian|czech|danish|deutsch|dutch|estonian|'.
            'finnish|flemish|french|german|greek|hebrew|hungarian|icelandic|italian|japanese|korean|'.
            'latin|mandarin|nordic|norwegian|polish|portuguese|romanian|russian|serbian|slovenian|'.
            'spanish|spanisch|swedish|thai|turkish|ukrainian|vietnamese';

        // Common language codes and abbreviations
        $langCodes = 'ar|bg|cn|cs|cz|da|de|dk|el|es|et|fi|fr|ger|gr|heb|hr|hu|hun|is|it|ita|jp|kr|ko|lt|lv|'.
            'mk|nl|no|pl|pt|ro|rs|ru|se|sk|sl|sr|sv|th|tr|ua|vi|zh';

        return (bool) preg_match('/(?:^|[\s._-])(?:'.$fullLanguages.'|'.$langCodes.')(?:$|[\s._-])/i', $name);
    }

    protected function checkComic(string $name, bool $categorizeForeign): ?CategorizationResult
    {
        // Check for comic file formats and common identifiers
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:CBR|CBZ|Comics?|Comic[._ -]Book|Graphic[._ -]Novel)|\b(?:CBR|CBZ|C2C)\b|\.(?:cbr|cbz)$|[[(](?:c2c|cbr|cbz)[])]/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.85, 'comic_foreign');
            }

            return $this->matched(Category::BOOKS_COMICS, 0.9, 'comic_format');
        }
        // Check for publishers
        if (preg_match('/\b(?:Marvel|DC[._ -]Comics|Image[._ -]Comics|Dark[._ -]Horse|IDW)\b/i', $name) &&
            preg_match('/\b(?:Comics?|Annual|Issue|Vol|TPB)\b/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.85, 'comic_publisher_foreign');
            }

            return $this->matched(Category::BOOKS_COMICS, 0.85, 'comic_publisher');
        }
        // Manga
        if (preg_match('/\b(?:Manga|Manhwa|Manhua|Webtoon)\b/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.85, 'manga_foreign');
            }

            return $this->matched(Category::BOOKS_COMICS, 0.85, 'manga');
        }

        return null;
    }

    protected function checkTechnical(string $name, bool $categorizeForeign): ?CategorizationResult
    {
        if ($this->hasSoftwareMarkers($name) || $this->hasVideoCourseMarkers($name)) {
            return null;
        }

        $publishers = 'Apress|Addison[._ -]Wesley|Manning|No[._ -]Starch|O[\'’]?Reilly|Packt|Pragmatic|Wiley|Wrox';
        if (preg_match('/\b('.$publishers.')\b/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.85, 'technical_publisher_foreign');
            }

            return $this->matched(Category::BOOKS_TECHNICAL, 0.9, 'technical_publisher');
        }
        $subjects = 'Programming|Python|JavaScript|Java|Database|Linux|DevOps|Machine[._ -]Learning|Data[._ -]Science';
        $bookSignals = '\b(?:Book|Guide|Tutorial|Learn|Handbook|Cookbook|Reference)\b';
        if (preg_match('/\b('.$subjects.')\b/i', $name) &&
            preg_match('/'.$bookSignals.'/i', $name) &&
            $this->hasBookCorroborator($name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.8, 'technical_subject_foreign');
            }

            return $this->matched(Category::BOOKS_TECHNICAL, 0.85, 'technical_subject');
        }

        return null;
    }

    protected function checkMagazine(string $name, bool $categorizeForeign): ?CategorizationResult
    {
        $magazines = 'Forbes|Fortune|GQ|National[._ -]Geographic|Newsweek|Vogue|Wired|The[._ -]?Economist|New[._ -]?Yorker|Scientific[._ -]?American|Popular[._ -]?Mechanics|Cosmopolitan|Elle|Esquire|Vanity[._ -]?Fair|Rolling[._ -]?Stone|Entertainment[._ -]?Weekly|People|Playboy';
        $hasTitle = preg_match('/\b('.$magazines.')\b/i', $name) === 1;
        $hasIssueNumber = preg_match('/(?:^|[._ -])Issue[._ -]?\d{1,4}(?:$|[._ -,])/i', $name) === 1;
        $hasDateSignal = preg_match('/\b(?:19|20)\d{2}\b|(?:^|[._ -])(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*[._ -]?(?:19|20)?\d{2}\b/i', $name) === 1;
        $hasFrequency = preg_match('/[._ -](Monthly|Weekly|Quarterly|Annual)[._ -]/i', $name) === 1;
        $hasIssueStyleTitle = preg_match('/\b([A-Z][a-zA-Z]+(?:\s+[A-Z][a-zA-Z]+)*)\s*-\s*Issue\s+\d+/i', $name) === 1;
        $hasMcnMagazineSignal = preg_match('/\bMCN[._ -](?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|January|February|March|April|June|July|August|September|October|November|December)[._ -]\d{1,2}[._ -](?:19|20)\d{2}\b/i', $name) === 1
            && preg_match('/\bMAGAZINE\b/i', $name) === 1;

        if (($hasFrequency && ($hasIssueNumber || $hasDateSignal || $hasTitle)) ||
            ($hasIssueNumber && ($hasDateSignal || $hasTitle || $hasIssueStyleTitle)) ||
            $hasMcnMagazineSignal) {
            return $this->matched(Category::BOOKS_MAGAZINES, 0.9, 'magazine_frequency');
        }
        if ($hasTitle) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.8, 'magazine_title_foreign');
            }

            return $this->matched(Category::BOOKS_MAGAZINES, 0.85, 'magazine_title');
        }

        return null;
    }

    protected function checkBookOther(string $name, bool $categorizeForeign): ?CategorizationResult
    {
        if ($this->hasSoftwareMarkers($name) || $this->hasVideoCourseMarkers($name) || $this->hasVideoReleaseMarkers($name)) {
            return null;
        }

        $fiction = 'Novel|Fiction|Thriller|Mystery|Fantasy|SciFi|Romance|Horror|Western|'.
            'Literature|Classics|Contemporary|Historical|Adventure|Detective|Drama';
        $nonfiction = 'Biography|Memoir|Autobiography|History|Philosophy|Psychology|Self[._ -]Help|'.
            'Business|Cooking|Gardening|Health|Religion|Travel|Art|Music|Spirituality|Politics';
        $publishing = 'ISBN|Retail|Publisher|Imprint|Edition|Chapter|Prologue|Epilogue|Foreword|'.
            'Paperback|Hardcover|Hardback|Softcover|Softback|Print[._ -]On[._ -]Demand';
        $collections = 'Anthology|Collection|Complete[._ -]Works|Series|Box[._ -]Set|Library|'.
            'Set[._ -]of|Volume[s]?|Compendium|Omnibus|Trilogy|Saga';
        $hasBookCategoryExclusion = preg_match('/\b(?:Magazine|Comic|Technical)\b/i', $name) === 1;

        if (! $hasBookCategoryExclusion &&
            preg_match('/\.(DOC[X]?|RTF|ODF|TXT|HTM[L]?)$/i', $name) &&
            $this->hasBookCorroborator($name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.75, 'book_format_foreign');
            }

            return $this->matched(Category::BOOKS_UNKNOWN, 0.7, 'book_format');
        }
        if (! $hasBookCategoryExclusion && preg_match('/\b('.$fiction.')\b/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.75, 'book_fiction_foreign');
            }

            return $this->matched(Category::BOOKS_UNKNOWN, 0.7, 'book_fiction');
        }
        if (! $hasBookCategoryExclusion && preg_match('/\b('.$nonfiction.')\b/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.75, 'book_nonfiction_foreign');
            }

            return $this->matched(Category::BOOKS_UNKNOWN, 0.7, 'book_nonfiction');
        }
        if (! $hasBookCategoryExclusion &&
            (preg_match('/\b('.$publishing.')\b/i', $name) || preg_match('/\b('.$collections.')\b/i', $name))) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.7, 'book_publishing_foreign');
            }

            return $this->matched(Category::BOOKS_UNKNOWN, 0.65, 'book_publishing');
        }
        if (preg_match('/^[A-Z][a-zA-Z\s.-]+\s+-\s+[A-Z][a-zA-Z0-9\s.:-]+\s+\(?(19|20)\d{2}\)?/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.7, 'book_author_foreign');
            }

            return $this->matched(Category::BOOKS_UNKNOWN, 0.65, 'book_author');
        }

        return null;
    }

    protected function checkEbook(string $name, bool $categorizeForeign): ?CategorizationResult
    {
        if ($this->hasSoftwareMarkers($name)) {
            return null;
        }

        $strictFormats = 'EPUB|MOBI|AZW\d?|FB2|DJVU|LIT';
        if (preg_match('/\.('.$strictFormats.')$/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.85, 'ebook_format_foreign');
            }

            return $this->matched(Category::BOOKS_EBOOK, 0.9, 'ebook_format');
        }
        if (preg_match('/\b('.$strictFormats.')\b/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.8, 'ebook_indicator_foreign');
            }

            return $this->matched(Category::BOOKS_EBOOK, 0.85, 'ebook_indicator');
        }
        if (preg_match('/\.PDF$/i', $name) && $this->hasBookCorroborator($name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.75, 'ebook_pdf_format_foreign');
            }

            return $this->matched(Category::BOOKS_EBOOK, 0.75, 'ebook_pdf_format');
        }
        if (preg_match('/\bPDF\b/i', $name) && $this->hasBookCorroborator($name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.65, 'ebook_pdf_indicator_foreign');
            }

            return $this->matched(Category::BOOKS_EBOOK, 0.65, 'ebook_pdf_indicator');
        }
        if (preg_match('/\b(E-?book|Kindle|Kobo|Nook)\b/i', $name)) {
            if ($this->checkForeign($name, $categorizeForeign)) {
                return $this->matched(Category::BOOKS_FOREIGN, 0.75, 'ebook_platform_foreign');
            }

            return $this->matched(Category::BOOKS_EBOOK, 0.8, 'ebook_platform');
        }
        if ($this->hasBookCorroborator($name) && preg_match('/\b(19|20)\d{2}\b/', $name) === 1) {
            return $this->matched(Category::BOOKS_EBOOK, 0.6, 'ebook_book_signals');
        }

        return null;
    }

    protected function hasBookCorroborator(string $name): bool
    {
        return preg_match('/\b(?:ISBN(?:-1[03])?|Edition|Ed\.|Author|Novel|Volume|Vol\.|Chapter|Press|Paperback|Hardcover|E-?book|Kindle|Kobo)\b/i', $name) === 1
            || preg_match('/\b(?:Apress|Addison[._ -]Wesley|Manning|No[._ -]Starch|O[\'’]?Reilly|Packt|Pragmatic|Wiley|Wrox)\b/i', $name) === 1
            || preg_match('/\.(?:epub|mobi|azw\d?|fb2|djvu|lit|pdf)$/i', $name) === 1;
    }

    protected function hasSoftwareMarkers(string $name): bool
    {
        if (preg_match('/\b(?:Setup|Install(?:er)?|Portable|Crack(?:ed)?|Keygen|Serial|Regged|Pre-?Activated|Patch(?:ed)?|NFO|ReadNFO|Win(?:dows)?|macOS|Linux|x64|x86|amd64|AIO|Retail|Incl)\b/i', $name)) {
            return true;
        }

        if (preg_match('/\b(?:Adobe|Foxit|Autodesk|AutoCAD|Corel|Microsoft[._ -]Office|Office(?:365)?|Visual[._ -]Studio|IntelliJ|PyCharm|VMware|VirtualBox)\b/i', $name)) {
            return true;
        }

        return preg_match('/\.(?:exe|msi|dmg|pkg)$/i', $name) === 1;
    }

    protected function hasVideoCourseMarkers(string $name): bool
    {
        return preg_match('/\b(?:Udemy|Pluralsight|Lynda\.?com|Coursera|LinkedIn[._ -]?Learning|Skillshare|MasterClass|CBT[._ -]?Nuggets|Digital-?Tutors)\b/i', $name) === 1;
    }

    protected function hasVideoReleaseMarkers(string $name): bool
    {
        return preg_match('/\b(?:720p|1080p|2160p|4k)\b/i', $name) === 1
            && preg_match('/\b(?:WEB[._ -]?DL|WEBRip|HDTV|BluRay|BDRip|DVDRip|x264|x265|HEVC|HDR|Remux|AVC)\b/i', $name) === 1;
    }
}
