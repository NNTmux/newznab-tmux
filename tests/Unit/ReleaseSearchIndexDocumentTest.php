<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ReleaseSearchIndexDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReleaseSearchIndexDocumentTest extends TestCase
{
    #[Test]
    public function normalize_builds_the_complete_public_projection_without_sensitive_fields(): void
    {
        $document = ReleaseSearchIndexDocument::normalize([
            'id' => '42',
            'guid' => 'guid-42',
            'name' => 'Release.Name',
            'searchname' => 'Release.Name',
            'fromname' => 'poster',
            'filename' => 'file.nzb',
            'categories_id' => '1',
            'category_name' => 'TV',
            'parent_category' => 'Video',
            'sub_category' => 'TV',
            'groups_id' => '2',
            'group_name' => 'alt.example',
            'tmdbid' => '123',
            'tv_episodes_id' => '456',
            'passwordstatus' => '-1',
            'comments' => '3',
            'postdate' => '2026-01-02 03:04:05',
            'adddate' => '2026-01-03 04:05:06',
            'nzb_password' => 'must-not-be-indexed',
        ]);

        self::assertSame(42, $document['id']);
        self::assertSame('Release Name', $document['plainsearchname']);
        self::assertSame(123, $document['tmdbid']);
        self::assertSame(456, $document['tv_episodes_id']);
        self::assertSame(-1, $document['passwordstatus']);
        self::assertGreaterThan(0, $document['postdate_ts']);
        self::assertGreaterThan(0, $document['adddate_ts']);
        self::assertArrayNotHasKey('nzb_password', $document);
        $documentFields = array_keys($document);
        $declaredFields = ReleaseSearchIndexDocument::fields();
        sort($documentFields);
        sort($declaredFields);
        self::assertSame($declaredFields, $documentFields);
    }

    #[Test]
    public function indexed_documents_can_be_hydrated_for_legacy_release_consumers(): void
    {
        $row = ReleaseSearchIndexDocument::toReleaseRow([
            'id' => 42,
            'postdate_ts' => 1767323045,
            'adddate_ts' => 1767413106,
            'name' => 'Release',
        ]);

        self::assertSame('2026-01-02 03:04:05', $row['postdate']);
        self::assertSame('2026-01-03 04:05:06', $row['adddate']);
        self::assertSame(42, $row['id']);
    }

    #[Test]
    public function normalize_for_bulk_preserves_timestamps_when_row_is_already_normalized(): void
    {
        $first = ReleaseSearchIndexDocument::normalize([
            'id' => 42,
            'name' => 'n',
            'searchname' => 's',
            'fromname' => 'f',
            'categories_id' => 1,
            'filename' => '',
            'imdbid' => '',
            'tmdbid' => 0,
            'traktid' => 0,
            'tvdb' => 0,
            'tvmaze' => 0,
            'tvrage' => 0,
            'videos_id' => 0,
            'movieinfo_id' => 0,
            'size' => 100,
            'postdate' => '2025-01-15 12:00:00',
            'adddate' => '2025-01-16 08:30:00',
            'totalpart' => 0,
            'grabs' => 0,
            'passwordstatus' => -1,
            'groups_id' => 1,
            'nzbstatus' => 1,
            'haspreview' => 0,
        ]);

        $second = ReleaseSearchIndexDocument::normalizeForBulk($first);

        self::assertSame($first['postdate_ts'], $second['postdate_ts']);
        self::assertSame($first['adddate_ts'], $second['adddate_ts']);
        self::assertGreaterThan(0, $second['postdate_ts']);
        self::assertGreaterThan(0, $second['adddate_ts']);
    }
}
