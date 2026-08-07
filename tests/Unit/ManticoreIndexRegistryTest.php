<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Search\Support\ManticoreIndexRegistry;
use App\Services\Search\Support\ManticoreSchemaInspector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManticoreIndexRegistryTest extends TestCase
{
    #[Test]
    public function it_defines_every_search_table_with_relevance_settings(): void
    {
        $definitions = ManticoreIndexRegistry::definitions();

        self::assertSame(
            ['releases', 'predb', 'movies', 'tvshows', 'music', 'books', 'games', 'console', 'steam', 'anime'],
            array_keys($definitions)
        );

        foreach ($definitions as $definition) {
            self::assertSame(2, $definition['settings']['min_infix_len']);
            self::assertSame(1, $definition['settings']['exact_words']);
            self::assertSame(1, $definition['settings']['index_field_lengths']);
        }

        self::assertSame('bigint', $definitions['releases']['columns']['passwordstatus']['type']);
        self::assertSame('bigint', $definitions['releases']['columns']['haspreview']['type']);
        self::assertSame('text', $definitions['releases']['columns']['media_movie_name']['type']);
        self::assertSame('string', $definitions['releases']['columns']['media_unique_id']['type']);
        self::assertSame('integer', $definitions['releases']['columns']['media_video_width']['type']);
        self::assertSame('text', $definitions['releases']['columns']['media_audio_language']['type']);
        self::assertSame('integer', $definitions['releases']['columns']['has_media_info']['type']);
    }

    #[Test]
    public function it_provides_title_first_field_weights(): void
    {
        self::assertGreaterThan(
            ManticoreIndexRegistry::profile('movies')['fields']['plot'],
            ManticoreIndexRegistry::profile('movies')['fields']['title']
        );
        self::assertGreaterThan(
            ManticoreIndexRegistry::profile('releases')['fields']['fromname'],
            ManticoreIndexRegistry::profile('releases')['fields']['searchname']
        );
    }

    #[Test]
    public function it_reports_schema_drift_that_requires_a_rebuild(): void
    {
        $result = ManticoreSchemaInspector::compareColumns(
            [['Field' => 'passwordstatus', 'Type' => 'uint']],
            ['passwordstatus' => ['type' => 'bigint'], 'haspreview' => ['type' => 'bigint']]
        );

        self::assertSame(['haspreview'], $result['missing']);
        self::assertSame('bigint', $result['incompatible']['passwordstatus']['expected']);
    }

    #[Test]
    public function it_accepts_the_php_clients_associative_describe_response(): void
    {
        $result = ManticoreSchemaInspector::compareColumns(
            [
                'passwordstatus' => ['Type' => 'bigint', 'Properties' => []],
                'haspreview' => ['Type' => 'bigint', 'Properties' => []],
            ],
            ['passwordstatus' => ['type' => 'bigint'], 'haspreview' => ['type' => 'bigint']]
        );

        self::assertSame([], $result['missing']);
        self::assertSame([], $result['incompatible']);
    }

    #[Test]
    public function it_treats_manticore_uint_as_the_integer_schema_type(): void
    {
        $result = ManticoreSchemaInspector::compareColumns(
            ['categories_id' => ['Type' => 'uint']],
            ['categories_id' => ['type' => 'integer']]
        );

        self::assertSame([], $result['incompatible']);
    }

    #[Test]
    public function it_compares_settings_by_name_and_value(): void
    {
        $actual = [
            'min_infix_len' => ['Value' => '2'],
            'exact_words' => ['Value' => '1'],
            'index_field_lengths' => ['Value' => '1'],
        ];

        self::assertSame([], ManticoreSchemaInspector::missingSettings($actual, [
            'min_infix_len' => 2,
            'exact_words' => 1,
            'index_field_lengths' => 1,
        ]));
    }

    #[Test]
    public function it_parses_manticore_28_bundled_settings_response(): void
    {
        $actual = ['settings' => "min_infix_len = 2\nindex_field_lengths = 1"];

        self::assertSame([], ManticoreSchemaInspector::missingSettings(
            $actual,
            ManticoreIndexRegistry::inspectableSettings()
        ));
    }

    #[Test]
    public function benchmark_fixture_is_versioned_and_covers_every_table(): void
    {
        $fixture = json_decode((string) file_get_contents(__DIR__.'/../Fixtures/Search/manticore-relevance-v1.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $fixture['version']);
        self::assertSame(array_keys(ManticoreIndexRegistry::definitions()), array_values(array_unique(array_column($fixture['queries'], 'index'))));
        self::assertArrayHasKey('top_5_precision', $fixture['thresholds']);
    }
}
