<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ElasticSearchQueryTest extends TestCase
{
    #[Test]
    public function it_uses_release_text_fields_constant_with_searchname_variants_only(): void
    {
        $driverSource = file_get_contents(__DIR__.'/../../app/Services/Search/Drivers/ElasticSearchDriver.php');

        $this->assertIsString($driverSource);
        $this->assertStringContainsString(
            "private const RELEASE_TEXT_FIELDS = ['searchname^3', 'plainsearchname^2'];",
            $driverSource
        );
    }

    #[Test]
    public function search_releases_filtered_uses_cross_fields_and_operator_and_for_release_text_queries(): void
    {
        $driverSource = file_get_contents(__DIR__.'/../../app/Services/Search/Drivers/ElasticSearchDriver.php');

        $this->assertIsString($driverSource);
        $this->assertStringContainsString("'fields' => self::RELEASE_TEXT_FIELDS", $driverSource);
        $this->assertStringContainsString("'type' => 'cross_fields'", $driverSource);
        $this->assertStringContainsString("'operator' => 'and'", $driverSource);
        $this->assertStringNotContainsString(
            "'fields' => ['searchname^3', 'name^2', 'filename', 'plainsearchname']",
            $driverSource
        );
        $this->assertStringNotContainsString("'type' => 'best_fields'", $driverSource);
    }

    #[Test]
    public function releases_index_search_entrypoints_use_release_text_fields_constant(): void
    {
        $driverSource = file_get_contents(__DIR__.'/../../app/Services/Search/Drivers/ElasticSearchDriver.php');

        $this->assertIsString($driverSource);
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($driverSource, 'fields: self::RELEASE_TEXT_FIELDS'),
            'Expected indexSearch/indexSearchApi/indexSearchTMA to use RELEASE_TEXT_FIELDS.'
        );
    }
}
