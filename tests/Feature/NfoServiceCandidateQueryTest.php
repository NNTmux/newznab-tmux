<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\NfoService;
use App\Services\NNTP\NNTPService;
use App\Services\Nzb\NzbContentsService;
use App\Services\Nzb\NzbParserService;
use App\Services\Nzb\NzbService;
use DariusIII\NetNntp\Error as NntpError;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class NfoServiceCandidateQueryTest extends TestCase
{
    /** Default max_nfo_retries (5) gives a retry window of -6..-1. */
    private const int DEFAULT_FLOOR = -6;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'nntmux.echocli' => false]);
        $this->app['cache']->setDefaultDriver('array');
        Cache::flush();

        DB::statement('CREATE TABLE releases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            guid VARCHAR(40) NOT NULL DEFAULT "",
            leftguid CHAR(1) NOT NULL DEFAULT "a",
            name VARCHAR(255) NOT NULL DEFAULT "",
            groups_id INTEGER NOT NULL DEFAULT 1,
            size INTEGER NOT NULL DEFAULT 104857600,
            postdate DATETIME NULL,
            nzbstatus INTEGER NOT NULL DEFAULT 1,
            nfostatus INTEGER NOT NULL DEFAULT -1
        )');
    }

    #[Test]
    public function releases_without_a_written_nzb_are_not_nfo_candidates(): void
    {
        DB::table('releases')->insert([
            ['id' => 1, 'nzbstatus' => NzbService::NZB_ADDED],
            ['id' => 2, 'nzbstatus' => NzbService::NZB_NONE],
        ]);

        $query = (new ReflectionMethod(NfoService::class, 'buildNfoProcessingQuery'))->invoke(new NfoService, '', 'a');

        $this->assertSame([1], $query->pluck('id')->map(intval(...))->all());
        $this->assertStringContainsString('AND r.nzbstatus = 1 AND', NfoService::NfoQueryString());
    }

    #[Test]
    #[DataProvider('downloadFailures')]
    public function a_download_failure_counts_down_and_moves_to_failed_at_the_floor(int $before, int $after): void
    {
        DB::table('releases')->insert(['id' => 1, 'nfostatus' => $before]);

        $this->contentsServiceWithFailingDownload()->getNfoFromNzb(str_repeat('a', 40), 1, 1, 'alt.binaries.test');

        $this->assertSame($after, (int) DB::table('releases')->where('id', 1)->value('nfostatus'));
    }

    /** @return array<string, array{int, int}> */
    public static function downloadFailures(): array
    {
        return [
            'first failure' => [NfoService::NFO_UNPROC, -2],
            'above the floor' => [self::DEFAULT_FLOOR + 1, self::DEFAULT_FLOOR],
            'at the floor' => [self::DEFAULT_FLOOR, NfoService::NFO_FAILED],
        ];
    }

    #[Test]
    public function the_failed_archive_pass_runs_at_most_once_an_hour_per_bucket(): void
    {
        $nfo = new NfoService;
        $due = new ReflectionMethod(NfoService::class, 'failedArchivePassIsDue');

        $this->assertTrue($due->invoke($nfo, '', 'a'));
        $this->assertFalse($due->invoke($nfo, '', 'a'));
        $this->assertTrue($due->invoke($nfo, '', 'b'));

        $this->travel(3601)->seconds();
        $this->assertTrue($due->invoke($nfo, '', 'a'));
    }

    private function contentsServiceWithFailingDownload(): NzbContentsService
    {
        $nntp = $this->createPartialMock(NNTPService::class, ['getMessages']);
        $nntp->method('getMessages')->willReturn(new NntpError('article not found', 430));

        $service = $this->getMockBuilder(NzbContentsService::class)
            ->setConstructorArgs([
                $this->createMock(NzbService::class),
                $this->createMock(NzbParserService::class),
                $nntp,
                new NfoService,
                null,
            ])
            ->onlyMethods(['parseNzb'])
            ->getMock();
        $service->method('parseNzb')->willReturn(['id' => 'nfo@test', 'hidden' => false]);

        return $service;
    }
}
