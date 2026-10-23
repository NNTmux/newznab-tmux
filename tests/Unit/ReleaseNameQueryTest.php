<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Search\DTO\ReleaseSearchQuery;
use App\Services\Search\Support\ReleaseNameQuery;
use PDO;
use Pdo\Sqlite;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReleaseNameQueryTest extends TestCase
{
    public const NAMES = [
        1 => 'The.Legend.of.Hanuman.S07.1080p.HS.WEB-DL.DDP5.1.x264-N3WBR0',
        2 => 'The.Legend.of.Hanuman.S07E01.1080p.HS.WEB-DL.DDP5.1.x264-N3WBR0',
        3 => 'The Legend of Hanuman S07E02 1080p HS WEB DL DDP5 1 x264 N3WBR0',
        4 => 'The.Legend.of.Hanuman.S01.1080p.HS.WEB-DL.DDP5.1.x264-N3WBR0',
        5 => 'The.Legend.of.Hanuman.S070.1080p.HS.WEB-DL.DDP5.1.x264-N3WBR0',
        6 => 'The.Legend.of.Another.S07.1080p.HS.WEB-DL.DDP5.1.x264-N3WBR0',
        7 => 'The.Legend.of.Hanuman.S07E01.1080p.HS.WEB-DL.DDP5.1.x264-N3WBR0LONG',
        8 => 'The.Legend.of.Hanuman.S07E01.1080p.HS.WEB-DL.DDP5.1.x265-N3WBR0',
        9 => 'The.Legend.of.Hanuman.S07E010.1080p.HS.WEB-DL.DDP5.1.x264-N3WBR0',
    ];

    /** @param list<int> $expected */
    #[DataProvider('queries')]
    public function test_matching_requires_the_supplied_release_details(string $input, array $expected): void
    {
        $pdo = PDO::connect('sqlite::memory:');
        self::assertInstanceOf(Sqlite::class, $pdo);
        $pdo->createFunction('regexp', static fn (string $pattern, string $value): int => (int) preg_match('~'.$pattern.'~u', $value));
        $pdo->exec('CREATE TABLE releases (id INTEGER PRIMARY KEY, searchname TEXT)');
        $insert = $pdo->prepare('INSERT INTO releases VALUES (?, ?)');
        foreach (self::NAMES as $id => $name) {
            $insert->execute([$id, $name]);
        }

        [$sql, $bindings] = ReleaseNameQuery::fromString($input)->sqlPredicate('searchname');
        $select = $pdo->prepare('SELECT id FROM releases WHERE '.$sql.' ORDER BY id');
        $select->execute($bindings);

        self::assertSame($expected, $select->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<string, array{string, list<int>}> */
    public static function queries(): array
    {
        return [
            'season includes packs and episodes' => ['The.Legend.of.Hanuman.S07', [1, 2, 3, 7, 8, 9]],
            'case and separators' => ['THE_legend-OF_hanuman_s07', [1, 2, 3, 7, 8, 9]],
            'season before quality' => ['The.Legend.of.Hanuman.S07 1080p', [1, 2, 3, 7, 8, 9]],
            'quality and source are still partial' => ['The.Legend.of.Hanuman.S07 1080p HS', [1, 2, 3, 7, 8, 9]],
            'partial title' => ['The Legend of Hanum', [1, 2, 3, 4, 5, 7, 8, 9]],
            'complete episode code' => ['The.Legend.of.Hanuman.S07E01', [2, 7, 8]],
            'explicit wildcard stays explicit' => ['The.Legend.of.Hanuman.S07*', [1, 2, 3, 5, 7, 8, 9]],
            'full episode release' => [self::NAMES[2], [2]],
            'full season pack' => [self::NAMES[1], [1]],
            'full season pack equivalent separators' => [str_replace(['.', '-'], ' ', self::NAMES[1]), [1]],
            'full release with extension' => [self::NAMES[2].'.mkv', [2]],
            'full release equivalent separators' => [str_replace(['.', '-'], ' ', self::NAMES[2]), [2]],
            'quoted season is exact' => ['"The Legend of Hanuman S07"', [1]],
            'negation is retained' => ['The.Legend.of.Hanuman.S07* -x265', [1, 2, 3, 5, 7, 9]],
            'grouping and OR' => ['(Hanuman | Another) S07 !x265', [1, 6]],
            'OR has existing Manticore precedence' => ['Legend Hanuman | Another S07', [1, 6]],
            'miss does not relax' => ['The.Legend.of.Hanuman.S08', []],
            'empty query' => ['', []],
            'wildcard only' => ['*', []],
            'invalid group' => ['(Hanuman', []],
            'incomplete OR' => ['Hanuman |', []],
        ];
    }

    public function test_season_is_expanded_as_a_required_alternative_in_both_engines(): void
    {
        $query = ReleaseNameQuery::fromString('The.Legend.of.Hanuman.S07');
        self::assertStringContainsString('the legend of hanuman (s07 | s07e0*', $query->manticore());
        self::assertStringNotContainsString('s07*', $query->manticore());
        $elastic = $query->elasticsearch();
        $season = $elastic['bool']['must'][0]['bool']['must'][4]['bool'] ?? $elastic['bool']['must'][4]['bool'];
        self::assertSame(1, $season['minimum_should_match']);
        self::assertCount(11, $season['should']);
        self::assertStringNotContainsString('fuzziness', json_encode($elastic, JSON_THROW_ON_ERROR));
    }

    public function test_partial_mode_is_opt_in_and_round_trips_in_the_query_dto(): void
    {
        self::assertFalse((new ReleaseSearchQuery)->partialReleaseName);
        $query = ReleaseSearchQuery::fromCriteria(['phrases' => 'Hanuman S07', 'partial_release_name' => true], 25, 50);
        self::assertTrue($query->partialReleaseName);
        self::assertTrue($query->criteria()['partial_release_name']);
        self::assertSame(25, $query->limit);
        self::assertSame(50, $query->offset);
    }

    public function test_partial_title_words_with_digits_expand_but_complete_technical_codes_do_not(): void
    {
        self::assertSame('*area51*', ReleaseNameQuery::fromString('Area51')->manticore());
        self::assertSame('s07e01', ReleaseNameQuery::fromString('S07E01')->manticore());
        self::assertSame('ddp5', ReleaseNameQuery::fromString('DDP5')->manticore());
        self::assertSame('2026', ReleaseNameQuery::fromString('2026')->manticore());
    }

    public function test_complete_names_keep_the_supplied_details_together(): void
    {
        $query = ReleaseNameQuery::fromString(self::NAMES[2]);
        self::assertStringStartsWith('"the legend of hanuman s07e01 ', $query->manticore());
        self::assertSame('phrase', $query->elasticsearch()['multi_match']['type']);
        [, $bindings] = $query->sqlPredicate('searchname');
        self::assertCount(1, $bindings);
        self::assertSame(0, preg_match('~'.$bindings[0].'~u', mb_strtolower(str_replace('DDP5.1', 'DDP5.2.1', self::NAMES[2]))));
    }

    public function test_plain_search_terms_use_wildcards_on_both_sides(): void
    {
        $query = ReleaseNameQuery::fromString('acronis');
        self::assertSame('*acronis*', $query->manticore());
        self::assertSame('*acronis*', $query->elasticsearch()['query_string']['query']);
        [, $bindings] = $query->sqlPredicate('searchname');
        foreach (['Acronis True Image 41393 Incl Patch', 'Boot.AcronisTrueImage.2026', 'Boot.MyAcronisTools.2026'] as $name) {
            self::assertSame(1, preg_match('~'.$bindings[0].'~u', mb_strtolower($name)), $name);
        }
        self::assertSame(0, preg_match('~'.$bindings[0].'~u', 'wondershare recoverit'));
        self::assertSame('acronis*', ReleaseNameQuery::fromString('acronis*')->manticore());
        self::assertSame('"acronis"', ReleaseNameQuery::fromString('"acronis"')->manticore());
    }
}
