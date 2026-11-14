<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Binaries\CrossPostOwnership;
use App\Services\XrefService;
use PHPUnit\Framework\TestCase;

final class CrossPostOwnershipTest extends TestCase
{
    private const string XREF = 'news.example.com alt.binaries.boneless:5000 alt.binaries.kenpsx:900 alt.binaries.misc:70';

    public function test_xref_numbers_are_read_in_posting_order(): void
    {
        $this->assertSame(
            ['alt.binaries.boneless' => 5000, 'alt.binaries.kenpsx' => 900, 'alt.binaries.misc' => 70],
            (new XrefService)->extractGroupArticleNumbers(self::XREF.' alt.binaries.boneless:1 alt.binaries.nonumber')
        );
        $this->assertSame([], (new XrefService)->extractGroupArticleNumbers(''));
    }

    public function test_first_active_group_covering_the_article_owns_it(): void
    {
        $ownership = new CrossPostOwnership(['alt.binaries.boneless' => 4000, 'alt.binaries.kenpsx' => 100, 'alt.binaries.misc' => 1]);

        $this->assertTrue($ownership->isOwnedBy($this->header(self::XREF), 'alt.binaries.boneless'));
        $this->assertFalse($ownership->isOwnedBy($this->header(self::XREF), 'alt.binaries.kenpsx'));
        $this->assertFalse($ownership->isOwnedBy($this->header(self::XREF), 'alt.binaries.misc'));
    }

    public function test_groups_that_cannot_cover_the_article_are_passed_over(): void
    {
        // boneless inactive (absent), kenpsx starts after its number, misc has no first_record yet.
        $ownership = new CrossPostOwnership(['alt.binaries.kenpsx' => 1000, 'alt.binaries.misc' => 0, 'alt.binaries.other' => 1]);

        $this->assertTrue($ownership->isOwnedBy($this->header(self::XREF), 'alt.binaries.boneless'), 'no covering group: keep');
        $this->assertTrue($ownership->isOwnedBy($this->header(self::XREF), 'alt.binaries.kenpsx'), 'no covering group: keep');

        $ownership = new CrossPostOwnership(['alt.binaries.kenpsx' => 1000, 'alt.binaries.misc' => 50]);

        $this->assertTrue($ownership->isOwnedBy($this->header(self::XREF), 'alt.binaries.misc'));
        $this->assertFalse($ownership->isOwnedBy($this->header(self::XREF), 'alt.binaries.boneless'));
    }

    public function test_articles_without_a_cross_post_are_always_kept(): void
    {
        $ownership = new CrossPostOwnership(['alt.binaries.kenpsx' => 1]);

        $this->assertTrue($ownership->isOwnedBy($this->header(''), 'alt.binaries.boneless'));
        $this->assertTrue($ownership->isOwnedBy([], 'alt.binaries.boneless'));
        $this->assertTrue($ownership->isOwnedBy($this->header('news.example.com alt.binaries.boneless:5000'), 'alt.binaries.boneless'));
    }

    /**
     * @return array<string, mixed>
     */
    private function header(string $xref): array
    {
        return ['Number' => 1, 'Xref' => $xref];
    }
}
