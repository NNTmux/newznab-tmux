<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Services\NNTP\NNTPService;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use App\Services\YencService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReleaseVerificationAuditDeletionTest extends TestCase
{
    private string $directory;

    private string $nzbPath;

    private string $imagePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/audit-deletion-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'release_verification.storage_path' => $this->directory.'/verification',
            'release_verification.scratch_bytes' => 2147483648, 'release_verification.free_bytes' => 0,
            'release_verification.bytes_per_second' => 0]);
        DB::purge();
        (require database_path('migrations/2026_10_11_154028_create_release_verification_candidates_table.php'))->up();
        (require database_path('migrations/2026_10_11_175742_create_release_verification_audit_retries_table.php'))->up();
        (require database_path('migrations/2026_10_12_004819_create_release_verification_scratch_table.php'))->up();
        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            $table->string('guid');
            $table->string('searchname');
            $table->string('fromname');
            $table->integer('groups_id');
            $table->string('postdate');
        });
        Schema::create('release_children', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_id')->constrained('releases')->cascadeOnDelete();
        });
        DB::table('releases')->insert(['id' => 1, 'guid' => 'audit-delete', 'searchname' => 'Movie',
            'fromname' => 'poster', 'groups_id' => 1, 'postdate' => '2026-10-11 12:00:00']);
        DB::table('release_children')->insert(['release_id' => 1]);
        $nzb = new class($this->directory) extends NzbService
        {
            public function __construct(string $directory)
            {
                $this->nzbSplitLevel = 1;
                $this->siteNzbPath = $directory.'/nzbs/';
                $this->siteNzbPaths = [$this->siteNzbPath];
            }
        };
        $this->app->instance(NzbService::class, $nzb);
        $this->nzbPath = $nzb->getNzbPath('audit-delete', createIfNotExist: true);
        $images = new ReleaseImageService;
        $images->audSavePath = $images->vidSavePath = $images->imgSavePath = $images->jpgSavePath = $this->directory.'/images/';
        mkdir($images->imgSavePath);
        $this->imagePath = $images->imgSavePath.'audit-delete_thumb.jpg';
        file_put_contents($this->imagePath, 'thumbnail');
        $this->app->instance(ReleaseImageService::class, $images);
        Search::shouldReceive('deleteRelease')->with(1)->andReturnNull();
        file_put_contents($this->directory.'/movie.mkv', str_repeat('movie-data', 64));
        (new Process(['par2', 'c', '-q', '-t1', '-s64', '-r100', '-n1', '--', 'set.par2', 'movie.mkv'], $this->directory))->mustRun();
        $parity = file_get_contents($this->directory.'/set.par2');
        $xml = '<nzb><file subject="Release [1/1] &quot;set.par2&quot; (1/1)"><groups><group>alt.test</group></groups><segments><segment bytes="'.strlen($parity).'" number="1">parity@article</segment></segments></file></nzb>';
        file_put_contents($this->nzbPath, gzencode($xml));
        $this->mock(NNTPService::class, static function ($mock) use ($parity): void {
            $mock->shouldReceive('doConnect')->with(false)->andReturnTrue();
            $mock->shouldReceive('verificationArticle')->with('parity@article', 16777216)->andReturn((new YencService)->encode($parity, 'set.par2'));
            $mock->shouldReceive('doQuit')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        File::swap(new Filesystem);
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    /** @return iterable<string, array{string}> */
    public static function databaseFailurePoints(): iterable
    {
        yield 'release delete' => ['BEFORE DELETE ON releases'];
        yield 'cursor update after release delete' => ['BEFORE UPDATE ON release_verification_state'];
    }

    #[DataProvider('databaseFailurePoints')]
    public function test_database_delete_failure_preserves_release_children_nzb_and_proven_audit_for_retry(string $failurePoint): void
    {
        DB::unprepared("CREATE TRIGGER fail_audit_delete {$failurePoint} BEGIN SELECT RAISE(ABORT, 'fixture delete failure'); END");
        try {
            $this->audit();
        } catch (QueryException $error) {
            $this->assertStringContainsString('fixture delete failure', $error->getMessage());
        }
        $this->assertFileExists($this->nzbPath);
        $this->assertFileExists($this->imagePath);
        $this->assertSame(1, DB::table('releases')->count());
        $this->assertSame(1, DB::table('release_children')->count());
        $this->assertSame(0, (int) DB::table('release_verification_state')->value('audit_cursor'));
        $entry = DB::table('release_verification_scratch')->sole();
        $this->assertSame('unrepairable', $entry->audit_verdict);
        $this->assertFalse((bool) $entry->terminal);
        $this->assertNull($entry->delete_guid);
        DB::unprepared('DROP TRIGGER fail_audit_delete');
        $this->audit();
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame(0, DB::table('release_children')->count());
        $this->assertFileExists($this->nzbPath);
        $this->worker();
        $this->assertFileDoesNotExist($this->nzbPath);
        $this->assertFileDoesNotExist($this->imagePath);
        $this->assertSame(0, DB::table('release_verification_scratch')->count());
    }

    public function test_committed_deletion_survives_interruption_before_report_and_worker_recovers_all_artifacts(): void
    {
        DB::listen(static function ($query): void {
            if (str_starts_with($query->sql, 'delete from "releases"')) {
                DB::afterCommit(static function (): void {
                    throw new \RuntimeException('simulated interruption after database commit');
                });
            }
        });
        try {
            $this->audit();
        } catch (\RuntimeException $error) {
            $this->assertSame('simulated interruption after database commit', $error->getMessage());
        }
        $this->assertSame(0, DB::table('releases')->count());
        $this->assertSame(0, DB::table('release_children')->count());
        $entry = DB::table('release_verification_scratch')->sole();
        $this->assertTrue((bool) $entry->terminal);
        $this->assertSame('audit-delete', $entry->delete_guid);
        $this->assertSame('unrepairable', $entry->audit_verdict);
        $this->assertSame(1, (int) DB::table('release_verification_state')->value('audit_cursor'));
        $this->assertFileExists($this->nzbPath);
        $this->assertFileDoesNotExist($this->directory.'/verification/audit.jsonl');
        $scratch = $this->scratch((int) $entry->id);
        $this->assertDirectoryExists($scratch);
        $this->worker();
        $this->assertFileDoesNotExist($this->nzbPath);
        $this->assertFileDoesNotExist($this->imagePath);
        $this->assertDirectoryDoesNotExist($scratch);
        $this->assertSame(0, DB::table('release_verification_scratch')->count());
    }

    /** @return iterable<string, array{bool}> */
    public static function artifactFailurePoints(): iterable
    {
        yield 'NZB removal' => [false];
        yield 'thumbnail removal after NZB is removed' => [true];
    }

    #[DataProvider('artifactFailurePoints')]
    public function test_failed_artifact_removal_retains_durable_work_even_when_scratch_is_absent(bool $failImage): void
    {
        $this->audit();
        $entry = DB::table('release_verification_scratch')->sole();
        (new Filesystem)->deleteDirectory($this->scratch((int) $entry->id));
        $failedPath = $failImage ? $this->imagePath : $this->nzbPath;
        $filesystem = \Mockery::mock(Filesystem::class)->makePartial();
        $filesystem->shouldReceive('delete')->with($failedPath)->once()->andReturnFalse();
        File::swap($filesystem);
        $this->worker();
        $this->assertFileExists($failedPath);
        $this->assertSame(1, DB::table('release_verification_scratch')->count());
        File::swap(new Filesystem);
        $this->worker();
        $this->assertFileDoesNotExist($this->nzbPath);
        $this->assertFileDoesNotExist($this->imagePath);
        $this->assertSame(0, DB::table('release_verification_scratch')->count());
    }

    public function test_dry_run_keeps_proven_unrepairable_release_and_artifacts(): void
    {
        $this->assertSame(0, Artisan::call('releases:verify-repairable', ['--delete' => true, '--dry-run' => true]));
        $record = json_decode(trim(file_get_contents($this->directory.'/verification/audit.jsonl')), true);
        $this->assertSame('unrepairable', $record['verdict']);
        $this->worker();
        $this->assertSame(1, DB::table('releases')->count());
        $this->assertSame(1, DB::table('release_children')->count());
        $this->assertFileExists($this->nzbPath);
        $this->assertFileExists($this->imagePath);
    }

    private function audit(): void
    {
        $this->assertSame(0, Artisan::call('releases:verify-repairable', ['--delete' => true, '--resume' => true]));
    }

    private function worker(): void
    {
        $this->assertSame(0, Artisan::call('releases:verify-candidates'));
    }

    private function scratch(int $entryId): string
    {
        return $this->directory.'/verification/scratch/audit-1-'.$entryId;
    }
}
