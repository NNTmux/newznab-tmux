<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Categorization\Categorizers\BookCategorizer;
use App\Services\Categorization\Categorizers\GroupNameCategorizer;
use App\Services\Categorization\Pipes\BookPipe;
use App\Services\Categorization\Pipes\CategorizationPassable;
use App\Services\Categorization\Pipes\GroupNamePipe;
use App\Services\Categorization\Pipes\MusicPipe;
use App\Services\Categorization\Pipes\PcPipe;
use App\Services\Categorization\ReleaseContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategorizeBookTest extends TestCase
{
    private BookCategorizer $bookCategorizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookCategorizer = new BookCategorizer;
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function validBookProvider(): array
    {
        return [
            'comic cbz' => ['Batman.Vol.3.Issue.145.2024.DC.Comics.CBZ', Category::BOOKS_COMICS],
            'technical publisher ebook' => ['The.Pragmatic.Programmer.20th.Anniversary.Edition.OReilly.2019.EPUB', Category::BOOKS_TECHNICAL],
            'magazine issue date' => ['Vegan_Food_and_Living_Monthly_May_2026_Issue_352', Category::BOOKS_MAGAZINES],
            'magazine issue with year' => ['History of War - Issue 158, 2026', Category::BOOKS_MAGAZINES],
            'mcn magazine pattern' => ['MCN.April.22.2026.HYBRID.MAGAZINE.eBook-21A1', Category::BOOKS_MAGAZINES],
            'ebook pdf with book context' => ['George.Orwell.1984.Novel.PDF', Category::BOOKS_EBOOK],
            'ebook part rar style after normalize' => ['Harry Styles Songbook - 1st Edition 2026', Category::BOOKS_EBOOK],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonBookProvider(): array
    {
        return [
            'foxit software with pdf name' => ['Foxit.PDF.Editor.Pro.14.0.4.33508.Multilingual'],
            'movie remux release' => ['The.Red.Ball.Express.1952.BR.Remux.AVC-d3g'],
            'python software runtime' => ['Python.3.12.2.Setup.x64'],
            'video training course' => ['Udemy.JavaScript.Masterclass.2026.1080p'],
            'font pack release' => ['Professional.Font.Pack.2026.OTF.Collection'],
            'music-like release' => ['Pop_Classics_6.26.part1'],
        ];
    }

    #[DataProvider('validBookProvider')]
    public function test_book_categorizer_detects_valid_books(string $name, int $expectedCategory): void
    {
        $context = new ReleaseContext(
            releaseName: $name,
            groupId: 0,
            groupName: '',
            poster: ''
        );

        $result = $this->bookCategorizer->categorize($context);

        $this->assertTrue($result->isSuccessful(), "Expected a valid book match for: {$name}");
        $this->assertSame($expectedCategory, $result->categoryId, "Wrong book category for: {$name}");
    }

    #[DataProvider('nonBookProvider')]
    public function test_book_categorizer_rejects_non_books(string $name): void
    {
        $context = new ReleaseContext(
            releaseName: $name,
            groupId: 0,
            groupName: '',
            poster: ''
        );

        $this->assertTrue(
            $this->bookCategorizer->shouldSkip($context) || ! $this->bookCategorizer->categorize($context)->isSuccessful(),
            "Expected non-book release to be skipped or unmatched: {$name}"
        );
    }

    public function test_audiobook_stays_in_music_category_in_pipeline(): void
    {
        $passable = $this->runPipes(
            'Brandon.Sanderson.Wind.And.Truth.Audiobook.Unabridged.M4B',
            '',
            [new MusicPipe, new BookPipe]
        );

        $this->assertSame(Category::MUSIC_AUDIOBOOK, $passable->bestResult->categoryId);
    }

    public function test_non_book_in_ebook_group_prefers_pc_category_over_group_book_hint(): void
    {
        $passable = $this->runPipes(
            'Foxit.PDF.Editor.Pro.v14.0.4.Portable.x64.Multilingual',
            'alt.binaries.e-book',
            [new GroupNamePipe, new PcPipe, new BookPipe]
        );

        $this->assertSame(Category::PC_0DAY, $passable->bestResult->categoryId);
    }

    public function test_group_name_book_confidence_is_reduced_to_point_five(): void
    {
        $categorizer = new GroupNameCategorizer;
        $context = new ReleaseContext(
            releaseName: 'Unknown.Upload.Name',
            groupId: 0,
            groupName: 'alt.binaries.ebooks.misc',
            poster: ''
        );

        $result = $categorizer->categorize($context);

        $this->assertSame(Category::BOOKS_EBOOK, $result->categoryId);
        $this->assertSame(0.5, $result->confidence);
        $this->assertSame('group_name_book', $result->matchedBy);
    }

    /**
     * @param  list<object>  $pipes
     */
    private function runPipes(string $releaseName, string $groupName, array $pipes): CategorizationPassable
    {
        $context = new ReleaseContext(
            releaseName: $releaseName,
            groupId: 0,
            groupName: $groupName,
            poster: ''
        );

        $passable = new CategorizationPassable($context, debug: true);
        foreach ($pipes as $pipe) {
            $passable = $pipe->handle($passable, fn ($p) => $p);
        }

        return $passable;
    }
}
