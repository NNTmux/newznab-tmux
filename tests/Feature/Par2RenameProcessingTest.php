<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\NntmuxResetDb;
use App\Console\Commands\NntmuxResetTruncate;
use App\Enums\Par2RenameOutcome;
use App\Facades\Search;
use App\Models\Category;
use App\Models\Release;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\NNTP\NNTPService;
use App\Services\Nzb\NzbContentsService;
use App\Services\Nzb\NzbParserService;
use App\Services\Nzb\NzbService;
use App\Services\Par2Processor;
use App\Services\PostProcessService;
use App\Services\Releases\Verification\VerificationResetService;
use App\Support\Par2RenameProgress;
use DariusIII\NetNntp\Error;
use dariusiii\rarinfo\Par2Info;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use SimpleXMLElement;
use Symfony\Component\Process\Process;
use Tests\Support\Par2RenameTestDatabase;
use Tests\TestCase;

class Par2RenameProcessingTest extends TestCase
{
    use Par2RenameTestDatabase;

    /** @var list<string> */
    private array $downloaded = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['tmux.fix_names.cache_store' => 'array', 'tmux.fix_names.par2_max_probes' => 2]);
        Cache::store('array')->flush();
        $this->createPar2RenameSchema();
    }

    public function test_missing_nzb_remains_pending(): void
    {
        $release = $this->release();
        $this->assertFalse($this->service(false)->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
        $this->assertSame([], $this->downloaded);
    }

    public function test_explicit_par2_is_selected_before_downloading_unrelated_entries(): void
    {
        $release = $this->release();
        $nzb = $this->nzb([
            ['"movie.mkv" yEnc (1/10)', 'movie-id'],
            ['"release.par2" yEnc (1/1)', 'par-id'],
            ['"archive.rar" yEnc (1/10)', 'rar-id'],
        ]);
        $this->service($nzb, ['par-id' => file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'))])
            ->checkPar2($release->guid, $release->id, 1, 1, 0);
        $this->assertSame(['par-id'], $this->downloaded);
        $this->assertSame(1, (int) $release->fresh()->proc_par2);
    }

    public function test_article_download_failure_remains_pending(): void
    {
        $release = $this->release();
        $nzb = $this->nzb([['"release.par2" yEnc (1/1)', 'par-id']]);
        $this->assertFalse($this->service($nzb, ['par-id' => new Error('Timed out')])
            ->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    public function test_obfuscated_par2_can_rename_and_reports_the_success(): void
    {
        $release = $this->release();
        $nzb = $this->nzb([['"randomhash" yEnc (1/1)', 'hidden-id']]);
        $data = file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'));
        $this->assertTrue($this->service($nzb, ['hidden-id' => $data], rename: true)
            ->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame('Recovered.Title.2026-GROUP', $release->fresh()->searchname);
        $this->assertSame(1, (int) $release->fresh()->proc_par2);
        $this->assertSame(['hidden-id'], $this->downloaded);
    }

    public function test_obfuscated_probe_budget_resumes_without_discarding_unexamined_entries(): void
    {
        $release = $this->release();
        $nzb = $this->nzb([
            ['"hash-one" yEnc (1/1)', 'one'],
            ['"hash-two" yEnc (1/1)', 'two'],
            ['"hash-three" yEnc (1/1)', 'three'],
        ]);
        $service = $this->service($nzb);
        $this->assertFalse($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(['one', 'two'], $this->downloaded);
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
        $this->assertFalse($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(['one', 'two', 'three'], $this->downloaded);
        $this->assertSame(1, (int) $release->fresh()->proc_par2);
    }

    public function test_another_worker_cannot_download_the_same_release_while_claimed(): void
    {
        $release = $this->release();
        $lock = Cache::store('array')->lock('rename:par2:'.$release->id, 600);
        $this->assertTrue($lock->get());
        try {
            $this->service($this->nzb([['"release.par2" yEnc (1/1)', 'par-id']]))
                ->checkPar2($release->guid, $release->id, 1, 1, 0);
            $this->assertSame([], $this->downloaded);
            $this->assertSame(0, (int) $release->fresh()->proc_par2);
        } finally {
            $lock->release();
        }
    }

    public function test_corrupt_explicit_par2_remains_pending(): void
    {
        $release = $this->release();
        $this->service($this->nzb([['"release.par2" yEnc (1/1)', 'par-id']]))
            ->checkPar2($release->guid, $release->id, 1, 1, 0);
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    public function test_transient_failure_is_retried_and_can_later_recover_a_name(): void
    {
        $release = $this->release();
        $attempt = 0;
        $response = static function () use (&$attempt): mixed {
            return $attempt++ === 0 ? new Error('Article temporarily unavailable', 430)
                : file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'));
        };
        $service = $this->service($this->nzb([['"release.par2" yEnc (1/1)', 'par-id']]), ['par-id' => $response], rename: true);
        $this->assertFalse($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(['candidates_probed' => 1, 'articles_received' => 0], $service->par2Stats());
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
        $this->assertTrue($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(['candidates_probed' => 1, 'articles_received' => 1], $service->par2Stats());
        $this->assertSame(1, (int) $release->fresh()->proc_par2);
        $this->assertSame('Recovered.Title.2026-GROUP', $release->fresh()->searchname);
    }

    public function test_a_worker_that_loses_its_claim_cannot_apply_downloaded_names(): void
    {
        $release = $this->release();
        $response = static function () use ($release): string|false {
            Cache::store('array')->lock('rename:par2:'.$release->id)->forceRelease();

            return file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'));
        };
        $service = $this->service($this->nzb([['"release.par2" yEnc (1/1)', 'par-id']]), ['par-id' => $response], rename: true);
        $this->assertFalse($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(0, (int) $release->fresh()->isrenamed);
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    public function test_expired_batch_deadline_defers_without_downloading_or_completing(): void
    {
        $release = $this->release();
        $service = $this->service($this->nzb([['"release.par2" yEnc (1/1)', 'par-id']]));
        $result = $service->checkPar2Result($release->guid, $release->id, 1, 1, 0, microtime(true) - 1);
        $this->assertSame(Par2RenameOutcome::Deferred, $result);
        $this->assertSame([], $this->downloaded);
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    public function test_interruption_after_a_completed_probe_resumes_at_the_next_candidate(): void
    {
        $release = $this->release();
        $attempt = 0;
        $response = static function () use (&$attempt): string {
            if ($attempt++ === 0) {
                throw new RuntimeException('Worker interrupted');
            }

            return 'Not a PAR2 packet';
        };
        $nzb = $this->nzb([['"hidden-one" yEnc', 'one'], ['"hidden-two" yEnc', 'two']]);
        $service = $this->service($nzb, ['two' => $response]);
        $this->assertFalse($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
        $this->assertFalse($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(['one', 'two', 'two'], $this->downloaded);
        $this->assertSame(1, (int) $release->fresh()->proc_par2);
    }

    public function test_changed_nzb_restarts_candidate_progress(): void
    {
        $release = $this->release();
        config(['tmux.fix_names.par2_max_probes' => 1]);
        $service = $this->service($this->nzb([['"hidden-one" yEnc', 'one'], ['"hidden-two" yEnc', 'two']]));
        $service->checkPar2($release->guid, $release->id, 1, 1, 0);
        $service = $this->service($this->nzb([['"hidden-new" yEnc', 'new'], ['"hidden-two" yEnc', 'two']]));
        $service->checkPar2($release->guid, $release->id, 1, 1, 0);
        $this->assertSame(['one', 'new'], $this->downloaded);
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    /** @return array<string, array{?string}> */
    public static function malformedProbeSizes(): array
    {
        return ['missing' => [null], 'empty' => [''], 'negative' => ['-1'], 'zero' => ['0'],
            'nonnumeric' => ['unknown'], 'fractional' => ['1.5'], 'overflow' => ['999999999999999999999999']];
    }

    #[DataProvider('malformedProbeSizes')]
    public function test_hidden_probes_with_invalid_byte_sizes_remain_pending_without_downloading(?string $bytes): void
    {
        $release = $this->release();
        $attribute = $bytes === null ? '' : ' bytes="'.$bytes.'"';
        $xml = new SimpleXMLElement('<nzb><file subject="hidden yEnc"><segments><segment'.$attribute.' number="1">hidden-id</segment></segments></file></nzb>');
        $result = $this->service($xml)->checkPar2Result($release->guid, $release->id, 1, 1, 0);
        $this->assertSame(Par2RenameOutcome::Retryable, $result);
        $this->assertSame([], $this->downloaded);
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    public function test_throwing_probes_rotate_and_allow_a_later_volume_to_recover(): void
    {
        $release = $this->release();
        config(['tmux.fix_names.par2_max_probes' => 1]);
        $service = $this->service($this->nzb([
            ['"release.par2" yEnc', 'index'], ['"release.vol001+002.par2" yEnc', 'volume'],
        ]), ['index' => static fn (): never => throw new RuntimeException('Decode failed'),
            'volume' => file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'))], rename: true);
        $this->assertFalse($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
        $this->assertTrue($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(['index', 'volume'], $this->downloaded);
    }

    public function test_probe_progress_survives_a_sweep_longer_than_seven_days(): void
    {
        $this->freezeTime();
        $release = $this->release();
        config(['tmux.fix_names.par2_max_probes' => 1]);
        $service = $this->service($this->nzb([
            ['"hidden-one" yEnc', 'one'], ['"hidden-two" yEnc', 'two'],
        ]), ['two' => file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'))], rename: true);
        $this->assertFalse($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->travel(8)->days();
        $this->assertTrue($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(['one', 'two'], $this->downloaded);
    }

    public function test_external_rename_clears_the_permanent_probe_checkpoint(): void
    {
        $release = $this->release();
        config(['tmux.fix_names.par2_max_probes' => 1]);
        $service = $this->service($this->nzb([
            ['"hidden-one" yEnc', 'one'], ['"hidden-two" yEnc', 'two'],
        ]));
        $service->checkPar2($release->guid, $release->id, 1, 1, 0);
        $this->assertNotNull(Par2RenameProgress::get($release->id));
        Release::query()->where('id', $release->id)->update(['isrenamed' => 1]);
        $this->assertSame(Par2RenameOutcome::NoMatch, $service->checkPar2Result($release->guid, $release->id, 1, 1, 0));
        $this->assertNull(Par2RenameProgress::get($release->id));
        $this->assertSame(['one'], $this->downloaded);
    }

    public function test_raw_release_deletion_cascades_to_durable_probe_progress(): void
    {
        $release = $this->release();
        $this->assertTrue(Par2RenameProgress::save($release->id, str_repeat('a', 64), 1, [0]));
        DB::table('releases')->where('id', $release->id)->delete();
        $this->assertNull(Par2RenameProgress::get($release->id));
    }

    public function test_terminal_status_cleanup_waits_for_commit_and_preserves_rolled_back_progress(): void
    {
        $release = $this->release();
        Par2RenameProgress::save($release->id, str_repeat('a', 64), 1, [0]);
        $updates = app(ReleaseUpdateService::class);
        DB::beginTransaction();
        $updates->updateSingleColumn('predb_id', 123, $release->id);
        $this->assertNotNull(Par2RenameProgress::get($release->id));
        DB::rollBack();
        $this->assertNotNull(Par2RenameProgress::get($release->id));
        $updates->updateSingleColumn('predb_id', 123, $release->id);
        $this->assertNull(Par2RenameProgress::get($release->id));
    }

    public function test_concurrent_external_rename_cannot_recreate_probe_progress(): void
    {
        $release = $this->release();
        $response = static function () use ($release): string {
            Release::query()->where('id', $release->id)->update(['isrenamed' => 1]);

            return 'Not a PAR2 packet';
        };
        $service = $this->service($this->nzb([
            ['"hidden-one" yEnc', 'one'], ['"hidden-two" yEnc', 'two'],
        ]), ['one' => $response]);
        $this->assertSame(Par2RenameOutcome::NoMatch, $service->checkPar2Result($release->guid, $release->id, 1, 1, 0));
        $this->assertNull(Par2RenameProgress::get($release->id));
        $this->assertSame(['one'], $this->downloaded);
    }

    public function test_corrupt_nzb_file_remains_pending_without_network_access(): void
    {
        $release = $this->release();
        $path = tempnam(sys_get_temp_dir(), 'rename-corrupt-nzb-');
        file_put_contents($path, gzencode('<nzb><broken>'));
        $nzbs = $this->createStub(NzbService::class);
        $nzbs->method('nzbPath')->willReturn($path);
        $service = new NzbContentsService(nzbService: $nzbs, nntp: $this->createStub(NNTPService::class));
        try {
            $this->assertFalse($service->checkPar2($release->guid, $release->id, 1, 1, 0));
            $this->assertSame(0, (int) $release->fresh()->proc_par2);
            $this->assertSame(['candidates_probed' => 0, 'articles_received' => 0], $service->par2Stats());
        } finally {
            unlink($path);
        }
    }

    public function test_small_single_part_par2_disguised_as_an_archive_is_still_recovered(): void
    {
        $release = $this->release();
        $service = $this->service($this->nzb([['"randomhash.rar" yEnc (1/1)', 'hidden-id']]),
            ['hidden-id' => file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'))], rename: true);
        $this->assertTrue($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame('Recovered.Title.2026-GROUP', $release->fresh()->searchname);
    }

    public function test_small_hidden_index_candidates_are_prioritized_before_large_unknown_articles(): void
    {
        $release = $this->release();
        config(['tmux.fix_names.par2_max_probes' => 1]);
        $service = $this->service($this->nzb([
            ['"hash-large" yEnc (1/1)', 'large', 750000],
            ['"hash-small" yEnc (1/1)', 'small', 1000],
        ]));
        $service->checkPar2($release->guid, $release->id, 1, 1, 0);
        $this->assertSame(['small'], $this->downloaded);
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    public function test_explicit_index_is_prioritized_before_recovery_volumes(): void
    {
        $release = $this->release();
        $service = $this->service($this->nzb([
            ['"release.vol001+002.par2" yEnc (1/10)', 'volume'],
            ['"release.par2" yEnc (1/1)', 'index'],
        ]), ['index' => file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'))], rename: true);
        $this->assertTrue($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(['index'], $this->downloaded);
    }

    public function test_a_separate_php_worker_respects_the_shared_release_claim(): void
    {
        $directory = sys_get_temp_dir().'/rename-process-claim-'.bin2hex(random_bytes(8));
        config(['cache.stores.rename_process_test' => ['driver' => 'file', 'path' => $directory]]);
        $lock = Cache::store('rename_process_test')->lock('rename:par2:1', 600);
        $this->assertTrue($lock->get());
        $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config([
    'cache.stores.rename_process_test' => ['driver' => 'file', 'path' => $argv[2]],
    'tmux.fix_names.cache_store' => 'rename_process_test',
]);
$service = (new ReflectionClass(App\Services\Nzb\NzbContentsService::class))->newInstanceWithoutConstructor();
echo $service->checkPar2Result('test-guid', 1, 1, 1, 0)->value;
PHP;
        try {
            $worker = new Process([PHP_BINARY, '-r', $script, base_path(), $directory], base_path());
            $worker->setTimeout(30);
            $worker->mustRun();
            $this->assertSame('deferred', $worker->getOutput());
        } finally {
            $lock->release();
            File::deleteDirectory($directory);
        }
    }

    public function test_real_unmatched_checker_does_not_complete_a_deferred_release(): void
    {
        $release = $this->release();
        config(['tmux.fix_names.par2_max_probes' => 1]);
        $service = $this->service($this->nzb([
            ['"index-one.par2" yEnc', 'one'], ['"index-two.par2" yEnc', 'two'],
        ]), ['one' => file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'))], names: new NameFixingService);

        $this->assertSame(Par2RenameOutcome::Deferred, $service->checkPar2Result($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    public function test_real_checker_does_not_reuse_another_releases_success(): void
    {
        Search::shouldReceive('updateRelease')->andReturnNull();
        $first = $this->release();
        $first->releases_id = $first->id;
        $first->textstring = 'Show.Name.S01E01.1080p.WEB-DL-GROUP.mkv';
        $names = new NameFixingService;
        $this->assertTrue($names->checkName($first, true, 'PAR2, ', true, false));
        $this->assertSame(1, (int) $first->fresh()->isrenamed);
        $second = $this->release();
        $service = $this->service($this->nzb([['"release.par2" yEnc', 'index']]),
            ['index' => file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'))], names: $names);

        $this->assertSame(Par2RenameOutcome::NoMatch, $service->checkPar2Result($second->guid, $second->id, 1, 1, 0));
        $this->assertSame(0, (int) $second->fresh()->isrenamed);
    }

    public function test_failed_index_does_not_prevent_recovery_from_a_volume(): void
    {
        $release = $this->release();
        $service = $this->service($this->nzb([
            ['"release.par2" yEnc', 'index'], ['"release.vol001+002.par2" yEnc', 'volume'],
        ]), ['index' => new Error('Article unavailable', 430),
            'volume' => file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'))], rename: true);

        $this->assertTrue($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(['index', 'volume'], $this->downloaded);
        $this->assertSame('Recovered.Title.2026-GROUP', $release->fresh()->searchname);
    }

    public function test_missing_segment_structure_remains_pending(): void
    {
        $release = $this->release();
        $service = $this->service(new SimpleXMLElement('<nzb><file subject="release.par2"><segments/></file></nzb>'));

        $this->assertSame(Par2RenameOutcome::Retryable, $service->checkPar2Result($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    public function test_known_category_is_not_reported_as_a_new_name_recovery(): void
    {
        $release = $this->pendingPar2Release(['categories_id' => Category::MOVIE_HD]);
        $service = $this->service($this->nzb([['"release.par2" yEnc', 'index']]),
            ['index' => file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'))]);

        $this->assertSame(Par2RenameOutcome::NoMatch, $service->checkPar2Result($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(0, (int) $release->fresh()->isrenamed);
    }

    public function test_failed_candidates_remain_retryable_after_other_candidates_are_checked(): void
    {
        $release = $this->release();
        config(['tmux.fix_names.par2_max_probes' => 1]);
        $attempt = 0;
        $index = static function () use (&$attempt): mixed {
            return $attempt++ === 0 ? new Error('Temporarily missing', 430)
                : file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'));
        };
        $service = $this->service($this->nzb([
            ['"release.par2" yEnc', 'index'], ['"release.vol001+002.par2" yEnc', 'volume'],
        ]), ['index' => $index, 'volume' => file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'))], names: new NameFixingService);

        $this->assertSame(Par2RenameOutcome::Deferred, $service->checkPar2Result($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(Par2RenameOutcome::Deferred, $service->checkPar2Result($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
        $this->assertSame(Par2RenameOutcome::NoMatch, $service->checkPar2Result($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(['index', 'volume', 'index'], $this->downloaded);
        $this->assertSame(1, (int) $release->fresh()->proc_par2);
    }

    public function test_failed_retries_rotate_so_a_recovered_candidate_is_not_starved(): void
    {
        $release = $this->release();
        config(['tmux.fix_names.par2_max_probes' => 1]);
        $attempt = 0;
        $volume = static function () use (&$attempt): mixed {
            return $attempt++ === 0 ? new Error('Initially unavailable', 430)
                : file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'));
        };
        $service = $this->service($this->nzb([
            ['"release.par2" yEnc', 'index'], ['"release.vol001+002.par2" yEnc', 'volume'],
        ]), ['index' => new Error('Permanently missing', 430), 'volume' => $volume], rename: true);
        for ($i = 0; $i < 3; $i++) {
            $this->assertFalse($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        }
        $this->assertTrue($service->checkPar2($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(['index', 'volume', 'index', 'volume'], $this->downloaded);
    }

    public function test_truncated_hidden_par2_data_remains_retryable(): void
    {
        $release = $this->release();
        $data = substr(file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2')), 0, 96);
        $service = $this->service($this->nzb([['"randomhash" yEnc', 'hidden']]), ['hidden' => $data]);
        $this->assertSame(Par2RenameOutcome::Retryable, $service->checkPar2Result($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    public function test_valid_par2_header_without_file_descriptions_remains_pending(): void
    {
        $release = $this->release();
        $data = file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2'));
        $packet = '';
        for ($offset = 0; $offset < strlen($data); $offset += $length) {
            $length = unpack('V', substr($data, $offset + 8, 4))[1];
            if (substr($data, $offset + 48, 16) === Par2Info::PACKET_MAIN) {
                $packet = substr($data, $offset, $length);
                break;
            }
        }
        $this->assertNotSame('', $packet);
        $service = $this->service($this->nzb([['"hidden-header" yEnc', 'header']]), ['header' => $packet]);
        $this->assertSame(Par2RenameOutcome::Retryable, $service->checkPar2Result($release->guid, $release->id, 1, 1, 0));
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    public function test_legacy_parser_keeps_its_bool_contract_without_leaking_success(): void
    {
        $known = $this->pendingPar2Release(['categories_id' => Category::MOVIE_HD]);
        $unknown = $this->release();
        $nntp = $this->createPartialMock(NNTPService::class, ['getMessages', 'doQuit']);
        $nntp->expects($this->exactly(2))->method('getMessages')
            ->willReturn(file_get_contents(base_path('tests/Fixtures/par2/rename-index.par2')));
        $nntp->method('doQuit')->willReturn(true);
        $post = app(PostProcessService::class);

        $this->assertTrue($post->parsePAR2('index', $known->id, 1, $nntp, 0));
        $this->assertFalse($post->parsePAR2('index', $unknown->id, 1, $nntp, 0));
        $this->assertSame(0, (int) $known->fresh()->isrenamed);
        $this->assertSame(0, (int) $unknown->fresh()->isrenamed);
    }

    private function release(): Release
    {
        return $this->pendingPar2Release();
    }

    public function test_article_reset_cascades_checkpoint_cleanup_for_deleted_releases(): void
    {
        $verificationRoot = sys_get_temp_dir().'/par2-reset-'.bin2hex(random_bytes(8));
        config(['release_verification.storage_path' => $verificationRoot]);
        $this->addGroupResetColumns();
        $removed = $this->pendingPar2Release(['nzbstatus' => 0]);
        $retained = $this->pendingPar2Release(['nzbstatus' => 1]);
        foreach ([$removed, $retained] as $release) {
            Par2RenameProgress::save($release->id, 'signature', 1, [0]);
        }
        $connection = DB::connection();
        DB::swap(\Mockery::mock(DB::getFacadeRoot())->makePartial());
        DB::shouldReceive('statement')->andReturnUsing(static function (string $sql) use ($connection): bool {
            if (str_starts_with($sql, 'SET FOREIGN_KEY_CHECKS')) {
                return $connection->statement(str_contains($sql, '= 0') ? 'PRAGMA foreign_keys = OFF' : 'PRAGMA foreign_keys = ON');
            }

            return true;
        });
        /** @var NntmuxResetTruncate&MockInterface $command */
        $command = \Mockery::mock(NntmuxResetTruncate::class)->makePartial();
        $command->shouldReceive('info');
        try {
            $this->assertSame(0, $command->handle(app(VerificationResetService::class)));
            $this->assertNull($removed->fresh());
            $this->assertNull(Par2RenameProgress::get($removed->id));
            $this->assertNotNull($retained->fresh());
            $this->assertNotNull(Par2RenameProgress::get($retained->id));
        } finally {
            DB::connection()->statement('PRAGMA foreign_keys = ON');
            File::deleteDirectory($verificationRoot);
        }
    }

    public function test_full_reset_removes_checkpoints_even_while_foreign_keys_are_disabled(): void
    {
        $verificationRoot = sys_get_temp_dir().'/par2-reset-'.bin2hex(random_bytes(8));
        config(['release_verification.storage_path' => $verificationRoot]);
        $this->addGroupResetColumns();
        $release = $this->release();
        Par2RenameProgress::save($release->id, 'signature', 1, [0]);
        $connection = DB::connection();
        DB::swap(\Mockery::mock(DB::getFacadeRoot())->makePartial());
        DB::shouldReceive('statement')->andReturnUsing(static function (string $sql) use ($connection): bool {
            if (str_contains($sql, 'FOREIGN_KEY_CHECKS = 0')) {
                return $connection->statement('PRAGMA foreign_keys = OFF');
            }
            if ($sql === 'TRUNCATE TABLE par2_rename_progress') {
                $connection->table('par2_rename_progress')->delete();
            }
            if ($sql === 'TRUNCATE TABLE releases') {
                $connection->table('releases')->delete();
                throw new RuntimeException('Stop before unrelated search and filesystem reset');
            }

            return true;
        });
        /** @var NntmuxResetDb&MockInterface $command */
        $command = \Mockery::mock(NntmuxResetDb::class)->makePartial();
        $command->shouldReceive('confirm')->once()->andReturn(true);
        $command->shouldReceive('info');
        $command->shouldReceive('error')->once()->with('Database reset stopped: Stop before unrelated search and filesystem reset');
        $this->app['env'] = 'local';
        try {
            $this->assertSame(1, $command->handle(app(VerificationResetService::class)));
            $this->assertNull($release->fresh());
            $this->assertNull(Par2RenameProgress::get($release->id));
        } finally {
            $this->app['env'] = 'testing';
            DB::connection()->statement('PRAGMA foreign_keys = ON');
            File::deleteDirectory($verificationRoot);
        }
    }

    private function addGroupResetColumns(): void
    {
        Schema::table('usenet_groups', static function (Blueprint $table): void {
            $table->unsignedInteger('first_record')->default(0);
            $table->unsignedInteger('last_record')->default(0);
            foreach (['first_record_postdate', 'last_record_postdate', 'last_updated'] as $column) {
                $table->timestamp($column)->nullable();
            }
        });
    }

    /** @param list<array{0: string, 1: string, 2?: int}> $files */
    private function nzb(array $files): SimpleXMLElement
    {
        $xml = new SimpleXMLElement('<nzb/>');
        foreach ($files as $values) {
            [$subject, $id] = $values;
            $file = $xml->addChild('file');
            $file->addAttribute('subject', $subject);
            $segment = $file->addChild('segments')->addChild('segment', $id);
            $segment->addAttribute('bytes', (string) ($values[2] ?? 1000));
            $segment->addAttribute('number', '1');
        }

        return $xml;
    }

    /** @param array<string, mixed> $responses */
    private function service(SimpleXMLElement|false $xml, array $responses = [], bool $rename = false, ?NameFixingService $names = null): NzbContentsService
    {
        $fetch = function (string $group, mixed $id) use ($responses): mixed {
            $this->downloaded[] = (string) $id;
            $response = $responses[(string) $id] ?? 'This is an ordinary article, not a PAR2 packet.';

            return $response instanceof \Closure ? $response() : $response;
        };
        $nntp = new class($fetch) extends NNTPService
        {
            public function __construct(private \Closure $fetch) {}

            public function getMessages(string $groupName, mixed $identifiers, bool $alternate = false): mixed
            {
                return ($this->fetch)($groupName, $identifiers);
            }

            public function doQuit(bool $force = false): mixed
            {
                return true;
            }
        };
        if ($names === null) {
            $names = $this->createStub(NameFixingService::class);
            $names->method('checkName')->willReturnCallback(static function (object $release) use ($rename): bool {
                if ($rename) {
                    Release::query()->where('id', $release->releases_id)->update([
                        'searchname' => 'Recovered.Title.2026-GROUP', 'isrenamed' => 1,
                    ]);
                }

                return $rename;
            });
        }
        $processor = new Par2Processor($names, new Par2Info, false, false);
        $post = new PostProcessService(nameFixingService: $names, par2Processor: $processor);

        return new class($xml, $nntp, $post) extends NzbContentsService
        {
            public function __construct(private SimpleXMLElement|false $xml, NNTPService $nntp, PostProcessService $post)
            {
                $this->nntp = $nntp;
                $this->postProcessService = $post;
                $this->parserService = new NzbParserService;
            }

            public function loadNzb(string $guid): SimpleXMLElement|false
            {
                return $this->xml;
            }
        };
    }
}
