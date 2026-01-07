<?php

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

class BookCategorizer extends AbstractCategorizer
{
    protected int $priority = 45;

    public function getName(): string
    {
        return 'Book';
    }

    public function shouldSkip(ReleaseContext $context): bool
    {
        if ($context->hasAdultMarkers()) {
            return true;
        }
        if (preg_match('/\.PS4-[A-Z0-9]+$/i', $context->releaseName)) {
            return true;
        }
        if (preg_match('/\b(?:PS[1-5]|PlayStation|Xbox|Switch|Nintendo|Wii|3DS|GameCube)\b/i', $context->releaseName)) {
            return true;
        }
        // Skip TV shows (season patterns)
        if (preg_match('/[._ -]S\d{1,3}[._ -]?(E\d|Complete|Full|1080|720|480|2160|WEB|HDTV|BluRay)/i', $context->releaseName)) {
            return true;
        }
        // Skip movies (year + quality patterns)
        if (preg_match('/\b(19|20)\d{2}\b.*\b(1080p|720p|2160p|BluRay|WEB-DL|BDRip|DVDRip)\b/i', $context->releaseName)) {
            return true;
        }

        return false;
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;
        if ($result = $this->checkComic($name)) {
            return $result;
        }
        if ($result = $this->checkTechnical($name)) {
            return $result;
        }
        if ($result = $this->checkMagazine($name)) {
            return $result;
        }
        if ($result = $this->checkEbook($name)) {
            return $result;
        }

        return $this->noMatch();
    }

    protected function checkComic(string $name): ?CategorizationResult
    {
        if (preg_match('/\b(?:CBR|CBZ|C2C)\b|\.(?:cbr|cbz)$/i', $name)) {
            return $this->matched(Category::BOOKS_COMICS, 0.9, 'comic_format');
        }
        if (preg_match('/\b(?:Marvel|DC[._ -]Comics|Image[._ -]Comics|Dark[._ -]Horse|IDW)\b/i', $name) &&
            preg_match('/\b(?:Comics?|Annual|Issue|Vol|TPB)\b/i', $name)) {
            return $this->matched(Category::BOOKS_COMICS, 0.85, 'comic_publisher');
        }
        if (preg_match('/\b(?:Manga|Manhwa|Manhua|Webtoon)\b/i', $name)) {
            return $this->matched(Category::BOOKS_COMICS, 0.85, 'manga');
        }

        return null;
    }

    protected function checkTechnical(string $name): ?CategorizationResult
    {
        $publishers = 'Apress|Addison[._ -]Wesley|Manning|No[._ -]Starch|OReilly|Packt|Pragmatic|Wiley|Wrox';
        if (preg_match('/\b('.$publishers.')\b/i', $name)) {
            return $this->matched(Category::BOOKS_TECHNICAL, 0.9, 'technical_publisher');
        }
        $subjects = 'Programming|Python|JavaScript|Java|Database|Linux|DevOps|Machine[._ -]Learning|Data[._ -]Science';
        if (preg_match('/\b('.$subjects.')\b/i', $name) && preg_match('/\b(Book|Guide|Tutorial|Learn)\b/i', $name)) {
            return $this->matched(Category::BOOKS_TECHNICAL, 0.85, 'technical_subject');
        }

        return null;
    }

    protected function checkMagazine(string $name): ?CategorizationResult
    {
        if (preg_match('/[._ -](Monthly|Weekly|Annual|Quarterly|Issue)[._ -]/i', $name)) {
            return $this->matched(Category::BOOKS_MAGAZINES, 0.9, 'magazine_frequency');
        }
        $magazines = 'Forbes|Fortune|GQ|National[._ -]Geographic|Newsweek|Time|Vogue|Wired|PC[._ -]Gamer';
        if (preg_match('/\b('.$magazines.')\b/i', $name)) {
            return $this->matched(Category::BOOKS_MAGAZINES, 0.85, 'magazine_title');
        }

        return null;
    }

    protected function checkEbook(string $name): ?CategorizationResult
    {
        $formats = 'EPUB|MOBI|AZW\d?|PDF|FB2|DJVU|LIT';
        if (preg_match('/\.('.$formats.')$/i', $name)) {
            return $this->matched(Category::BOOKS_EBOOK, 0.9, 'ebook_format');
        }
        if (preg_match('/\b('.$formats.')\b/i', $name)) {
            return $this->matched(Category::BOOKS_EBOOK, 0.85, 'ebook_indicator');
        }
        if (preg_match('/\b(E-?book|Kindle|Kobo|Nook)\b/i', $name)) {
            return $this->matched(Category::BOOKS_EBOOK, 0.8, 'ebook_platform');
        }

        return null;
    }
}
