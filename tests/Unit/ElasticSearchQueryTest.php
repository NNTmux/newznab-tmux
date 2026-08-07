<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Search\Drivers\ElasticSearchDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

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
        $methodSource = strstr($driverSource, 'public function searchReleasesFiltered');
        $this->assertIsString($methodSource);
        $methodSource = strstr($methodSource, 'private function buildReleaseFieldSpecificMustClauses', true);
        $this->assertIsString($methodSource);

        $this->assertStringContainsString("'fields' => self::RELEASE_TEXT_FIELDS", $methodSource);
        $this->assertStringContainsString("'type' => 'cross_fields'", $methodSource);
        $this->assertStringContainsString("'operator' => 'and'", $methodSource);
        $this->assertStringNotContainsString(
            "'fields' => ['searchname^3', 'name^2', 'filename', 'plainsearchname']",
            $methodSource
        );
        $this->assertStringNotContainsString("'type' => 'best_fields'", $methodSource);
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

    #[Test]
    public function filtered_release_search_supports_search_after_without_loading_source(): void
    {
        $driverSource = file_get_contents(__DIR__.'/../../app/Services/Search/Drivers/ElasticSearchDriver.php');

        $this->assertIsString($driverSource);
        $methodSource = strstr($driverSource, 'public function searchReleasesFiltered');
        $this->assertIsString($methodSource);
        $this->assertStringContainsString("\$body['search_after']", $methodSource);
        $this->assertStringContainsString("'_source' => false", $methodSource);
        $this->assertStringContainsString("'track_total_hits' => (bool)", $methodSource);
    }

    #[Test]
    public function filtered_release_search_supports_media_info_text_and_numeric_filters(): void
    {
        $driverSource = file_get_contents(__DIR__.'/../../app/Services/Search/Drivers/ElasticSearchDriver.php');

        $this->assertIsString($driverSource);
        $this->assertStringContainsString("'media_video_codec' => ['media_video_codec']", $driverSource);
        $this->assertStringContainsString("'term' => ['media_unique_id' => \$mediaUniqueId]", $driverSource);
        $this->assertStringContainsString("'term' => ['has_media_info' =>", $driverSource);
        $this->assertStringContainsString("'range' => ['media_video_width' => \$range]", $driverSource);
        $this->assertStringContainsString("'range' => ['media_video_height' => \$range]", $driverSource);
    }

    #[Test]
    public function media_info_unique_id_uses_an_exact_keyword_filter(): void
    {
        $reflection = new ReflectionClass(ElasticSearchDriver::class);
        $driver = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('buildElasticsearchReleaseFilters');

        $filters = $method->invoke($driver, ['media_unique_id' => ' 0x123ABC ']);

        $this->assertContains(['term' => ['media_unique_id' => '0x123ABC']], $filters);
    }

    #[Test]
    public function incremental_release_updates_use_the_canonical_projection_and_document(): void
    {
        $driverSource = file_get_contents(__DIR__.'/../../app/Services/Search/Drivers/ElasticSearchDriver.php');

        $this->assertIsString($driverSource);
        $this->assertStringContainsString('ReleaseIndexProjection::forId((int) $releaseID)', $driverSource);
        $this->assertStringContainsString('ReleaseSearchIndexDocument::normalizeForBulk($parameters)', $driverSource);
        $this->assertStringContainsString("'body' => \$document", $driverSource);
    }
}
