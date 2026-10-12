<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\VerifyReleaseCandidates;
use App\Facades\Search;
use App\Services\NNTP\NNTPService;
use App\Services\ReleaseCleaningService;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\ManifestStore;
use App\Services\Releases\Verification\ReleaseMetadataResolver;
use App\Services\Releases\Verification\ScratchInventory;
use App\Services\YencService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

class PublishedVerificationScratchCleanupTest extends ReleaseVerificationCandidatesTest
{
    /** @return iterable<string, array{bool}> */
    public static function publicationInterruptions(): iterable
    {
        yield 'worker killed after publication' => [false];
        yield 'after-commit callback failed' => [true];
    }

    #[DataProvider('publicationInterruptions')]
    public function test_next_worker_cleans_durable_publication_after_interruption(bool $callbackFailure): void
    {
        $directory = $this->candidateScratch(1, 'pending');
        try {
            DB::transaction(function () use ($callbackFailure): void {
                DB::table('release_verification_candidates')->where('id', 1)->update(['status' => 'complete', 'release_id' => 77]);
                if ($callbackFailure) {
                    DB::afterCommit(static function (): void {
                        throw new RuntimeException('Simulated failed publication callback.');
                    });
                }
            });
        } catch (RuntimeException $error) {
            $this->assertTrue($callbackFailure);
            $this->assertSame('Simulated failed publication callback.', $error->getMessage());
        }
        $this->assertFileExists($directory.'/payload');

        $this->artisan('releases:verify-candidates')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($directory);
        $this->assertSame(77, DB::table('release_verification_candidates')->value('release_id'));
    }

    public function test_failed_file_removal_is_retried_on_next_worker(): void
    {
        $directory = $this->candidateScratch(1);
        $filesystem = new Filesystem;
        $attempts = 0;
        File::partialMock()->shouldReceive('delete')->andReturnUsing(function ($path) use ($filesystem, &$attempts): bool {
            return ++$attempts === 1 ? false : $filesystem->delete($path);
        });

        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertFileExists($directory.'/payload');
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($directory);
        $this->assertSame(2, $attempts);
    }

    public function test_sweep_preserves_pending_active_and_newer_reimport_versions(): void
    {
        $published = $this->candidateScratch(1);
        $pending = $this->candidateScratch(2, 'pending');
        $active = $this->candidateScratch(3, 'verifying');
        $unrepairable = $this->candidateScratch(4, 'unrepairable');
        DB::table('release_verification_candidates')->where('id', 3)->update(['lease_token' => 'active', 'lease_until' => now()->addHour()]);
        $newer = dirname($published).'/1-2';
        mkdir($newer);
        file_put_contents($newer.'/payload', 'new version');
        $filesystem = new Filesystem;
        File::partialMock()->shouldReceive('delete')->andReturnUsing(function ($path) use ($filesystem): bool {
            DB::table('release_verification_candidates')->where('id', 1)->update(['status' => 'pending', 'release_id' => null, 'version' => 2]);

            return $filesystem->delete($path);
        });

        $this->artisan('releases:verify-candidates')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($published);
        foreach ([$pending, $active, $unrepairable, $newer] as $directory) {
            $this->assertFileExists($directory.'/payload');
        }
    }

    public function test_sweep_recovers_expired_filtered_and_repaired_scratch_left_after_cleanup_failure(): void
    {
        $directories = [];
        foreach (['expired', 'filtered', 'repaired'] as $index => $status) {
            $directories[] = $this->candidateScratch($index + 1, $status);
        }

        $this->artisan('releases:verify-candidates')->assertSuccessful();

        foreach ($directories as $directory) {
            $this->assertDirectoryDoesNotExist($directory);
        }
    }

    public function test_worker_lock_prevents_recovery_during_another_worker_run(): void
    {
        $directory = $this->candidateScratch(1);
        $lock = fopen(config('release_verification.storage_path').'/worker.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $this->artisan('releases:verify-candidates')->assertSuccessful();
            $this->assertFileExists($directory.'/payload');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($directory);
    }

    public function test_reclaimed_space_is_available_before_next_payload_download(): void
    {
        $directory = $this->candidateScratch(1);
        file_put_contents($directory.'/payload', str_repeat('x', 1024));
        config(['release_verification.scratch_bytes' => 512, 'release_verification.free_bytes' => 0, 'release_verification.bytes_per_second' => 0]);
        app(CandidateRepository::class)->enqueue(['identity' => sha1('new-payload'), 'groups_id' => 1, 'expected_files' => 2,
            'files' => [['key' => 'file', 'subject' => 'movie.mkv', 'totalparts' => 1,
                'segments' => [['number' => 1, 'messageid' => 'new@article', 'bytes' => 5]]]]]);
        $this->mock(NNTPService::class, function ($mock): void {
            $mock->shouldReceive('doConnect')->once()->with(false)->andReturn(true);
            $mock->shouldReceive('verificationArticle')->once()->andReturn((new YencService)->encode('hello', 'movie.mkv'));
            $mock->shouldReceive('doQuit')->andReturn(null);
        });

        $this->artisan('releases:verify-candidates')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($directory);
        $candidate = DB::table('release_verification_candidates')->whereNull('release_id')->sole();
        $this->assertSame('Declared file inventory cannot be proved complete.', $candidate->reason);
        $this->assertFileExists(dirname($directory).'/'.$candidate->id.'-1/articles/'.hash('sha256', 'new@article'));
    }

    public function test_sweep_is_bounded_and_resumes_after_an_entry_budget(): void
    {
        $directory = $this->candidateScratch(1);
        unlink($directory.'/payload');
        foreach (range(1, 1001) as $number) {
            file_put_contents($directory.'/'.$number, 'x');
        }
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertGreaterThan(0, count(glob($directory.'/*')));
        $this->assertLessThan(1001, count(glob($directory.'/*')));
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($directory);
    }

    public function test_large_low_id_directory_does_not_starve_later_terminal_cleanup(): void
    {
        $large = $this->candidateScratch(1);
        foreach (range(1, 2500) as $entry) {
            file_put_contents($large.'/'.$entry, 'x');
        }
        $later = $this->candidateScratch(2);

        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertDirectoryExists($large);
        $this->assertDirectoryExists($later);
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertDirectoryExists($large);
        $this->assertDirectoryDoesNotExist($later);
        $this->assertTrue(DB::table('release_verification_scratch')->where('candidate_id', 1)->exists());
    }

    public function test_sweep_advances_past_one_hundred_actual_directories(): void
    {
        foreach (range(1, 101) as $id) {
            $this->candidateScratch($id);
        }
        $root = config('release_verification.storage_path').'/scratch';
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertCount(1, glob($root.'/*'));
        $this->candidateScratch(102);
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertCount(1, glob($root.'/*'));
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertCount(0, glob($root.'/*'));
    }

    public function test_historical_rows_without_scratch_do_not_delay_recovery(): void
    {
        foreach (range(1, 101) as $id) {
            $directory = $this->candidateScratch($id);
            File::deleteDirectory($directory);
            DB::table('release_verification_scratch')->where('candidate_id', $id)->delete();
        }
        $directory = $this->candidateScratch(102);

        $this->artisan('releases:verify-candidates')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($directory);
    }

    public function test_terminal_candidate_recovers_older_versions_but_preserves_future_versions(): void
    {
        $directory = $this->candidateScratch(1, 'expired');
        DB::table('release_verification_candidates')->where('id', 1)->update(['version' => 2]);
        app(ScratchInventory::class)->register(1, 3);
        $future = dirname($directory).'/1-3';
        mkdir($future);
        file_put_contents($future.'/payload', 'future');

        $this->artisan('releases:verify-candidates')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($directory);
        $this->assertFileExists($future.'/payload');
    }

    public function test_sweep_resumes_past_preserved_pending_directories(): void
    {
        foreach (range(1, 101) as $id) {
            $this->candidateScratch($id, 'pending');
        }
        $last = config('release_verification.storage_path').'/scratch/101-1';
        $id = 101;
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        DB::table('release_verification_candidates')->where('id', $id)->update(['status' => 'filtered']);

        $this->artisan('releases:verify-candidates')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($last);
        $this->assertCount(100, glob(config('release_verification.storage_path').'/scratch/*'));
    }

    public function test_failed_cleanup_is_retried_even_while_pending_directories_keep_arriving(): void
    {
        foreach (range(1, 101) as $id) {
            $this->candidateScratch($id, 'pending');
        }
        $root = config('release_verification.storage_path').'/scratch';
        $failed = $root.'/1-1';
        $id = 1;
        DB::table('release_verification_candidates')->where('id', $id)->update(['status' => 'filtered']);
        $filesystem = new Filesystem;
        $attempts = 0;
        File::partialMock()->shouldReceive('delete')->andReturnUsing(function ($path) use ($filesystem, &$attempts): bool {
            return ++$attempts === 1 ? false : $filesystem->delete($path);
        });
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertFileExists($failed.'/payload');

        foreach (range(1, 3) as $run) {
            foreach (range(1, 101) as $newId) {
                $this->candidateScratch($run * 101 + $newId, 'pending');
            }
            $this->artisan('releases:verify-candidates')->assertSuccessful();
        }

        $this->assertDirectoryDoesNotExist($failed);
        $this->assertSame(2, $attempts);
        $this->assertCount(403, glob($root.'/*'));
    }

    public function test_sweep_does_not_follow_candidate_or_child_symlinks(): void
    {
        $linked = $this->candidateScratch(1);
        $directory = $this->candidateScratch(2);
        $outside = config('release_verification.storage_path').'/outside';
        mkdir($outside);
        file_put_contents($outside.'/sentinel', 'preserved');
        File::deleteDirectory($linked);
        symlink($outside, $linked);
        symlink($outside, $directory.'/linked');

        $this->artisan('releases:verify-candidates')->assertSuccessful();

        $this->assertFileExists($outside.'/sentinel');
        $this->assertTrue(is_link($linked));
        $this->assertDirectoryDoesNotExist($directory);
    }

    public function test_sweep_does_not_follow_a_symlinked_scratch_root(): void
    {
        $directory = $this->candidateScratch(1);
        $root = config('release_verification.storage_path');
        rename($root.'/scratch', $root.'/outside');
        symlink($root.'/outside', $root.'/scratch');

        $this->artisan('releases:verify-candidates')->assertFailed();

        $this->assertFileExists($directory.'/payload');
        $this->assertFileExists($root.'/outside/1-1/payload');
    }

    public function test_cleanup_never_enumerates_the_top_level_scratch_directory(): void
    {
        stream_wrapper_register('scratch-enumeration', ScratchEnumerationProbe::class);
        ScratchEnumerationProbe::$reads = 0;
        try {
            $this->assertTrue(is_readable('scratch-enumeration://root/scratch'));
            $this->assertTrue(is_executable('scratch-enumeration://root/scratch'));
            $command = app(VerifyReleaseCandidates::class);
            (new \ReflectionMethod($command, 'cleanupTerminalScratch'))->invoke($command, 'scratch-enumeration://root');
            $this->assertSame(0, ScratchEnumerationProbe::$reads);
        } finally {
            stream_wrapper_unregister('scratch-enumeration');
        }
    }

    public function test_external_unregistered_scratch_and_missing_candidate_metadata_are_preserved(): void
    {
        $missing = $this->candidateScratch(1);
        DB::table('release_verification_candidates')->where('id', 1)->delete();
        $unregistered = $this->candidateScratch(2);
        DB::table('release_verification_scratch')->where('candidate_id', 2)->delete();

        $this->artisan('releases:verify-candidates')->assertSuccessful();

        $this->assertFileExists($missing.'/payload');
        $this->assertFileExists($unregistered.'/payload');
        $this->assertSame(1, DB::table('release_verification_scratch')->count());
    }

    public function test_worker_registers_scratch_before_any_payload_directory_is_created(): void
    {
        app(CandidateRepository::class)->enqueue(['identity' => sha1('register-first'), 'groups_id' => 1, 'files' => []]);
        $id = (int) DB::table('release_verification_candidates')->value('id');
        $directory = config('release_verification.storage_path').'/scratch/'.$id.'-1';
        $registered = false;
        DB::listen(function ($query) use ($directory, &$registered): void {
            if (str_starts_with($query->sql, 'insert or ignore into "release_verification_scratch"')) {
                $this->assertDirectoryDoesNotExist($directory);
                $registered = true;
            }
        });
        $reference = DB::table('release_verification_candidates')->value('manifest_path');
        unlink(app(ManifestStore::class)->path($reference));

        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertTrue($registered);
        $this->assertDirectoryDoesNotExist($directory);
        $this->assertSame(1, DB::table('release_verification_scratch')->count());
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertSame(0, DB::table('release_verification_scratch')->count());
    }

    public function test_failed_immediate_publication_cleanup_retains_index_for_next_worker(): void
    {
        config(['nntmux.release_dedupe_enabled' => false, 'release_verification.bytes_per_second' => 0, 'release_verification.free_bytes' => 0,
            'nntmux_settings.path_to_nzbs' => config('release_verification.storage_path').'/nzbs']);
        File::ensureDirectoryExists(config('nntmux_settings.path_to_nzbs'));
        Schema::create('releases', static function (Blueprint $table): void {
            $table->id();
            foreach (['name', 'searchname', 'guid', 'leftguid', 'fromname', 'postdate', 'adddate'] as $column) {
                $table->string($column);
            }
            foreach (['totalpart', 'groups_id', 'size', 'passwordstatus', 'haspreview', 'categories_id', 'nfostatus', 'nzbstatus', 'isrenamed', 'iscategorized', 'predb_id'] as $column) {
                $table->integer($column);
            }
        });
        Schema::create('release_regexes', static function (Blueprint $table): void {
            $table->integer('releases_id');
            $table->integer('collection_regex_id');
            $table->integer('naming_regex_id');
        });
        Schema::create('releases_groups', static function (Blueprint $table): void {
            $table->integer('releases_id');
            $table->integer('groups_id');
        });
        $this->app->instance(ReleaseMetadataResolver::class, new class(app(ReleaseCleaningService::class)) extends ReleaseMetadataResolver
        {
            public function resolve(array $manifest): array
            {
                return ['name' => 'Movie', 'searchname' => 'Movie', 'fromname' => 'poster', 'predb_id' => 0,
                    'isrenamed' => 1, 'categories_id' => 2000, 'naming_regex_id' => 0];
            }
        });
        Search::shouldReceive('updateRelease')->once();
        app(CandidateRepository::class)->enqueue(['identity' => sha1('publish-cleanup'), 'subject' => 'Movie', 'fromname' => 'poster',
            'date' => '2026-10-11 12:00:00', 'groups_id' => 1, 'groups' => ['alt.test'], 'expected_files' => 1, 'regex_id' => 0,
            'files' => [['key' => 'file', 'subject' => 'movie.mkv', 'totalparts' => 1,
                'segments' => [['number' => 1, 'messageid' => 'publish@article', 'bytes' => 5]]]]]);
        $id = (int) DB::table('release_verification_candidates')->value('id');
        $directory = config('release_verification.storage_path').'/scratch/'.$id.'-1';
        $filesystem = new Filesystem;
        File::partialMock()->shouldReceive('deleteDirectory')->andReturnUsing(static fn ($path): bool => $path === $directory ? false : $filesystem->deleteDirectory($path));
        $this->mock(NNTPService::class, static function ($mock): void {
            $mock->shouldReceive('doConnect')->once()->andReturn(true);
            $mock->shouldReceive('verificationArticle')->once()->andReturn((new YencService)->encode('hello', 'movie.mkv'));
            $mock->shouldReceive('doQuit')->andReturn(null);
        });

        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertSame('complete', DB::table('release_verification_candidates')->value('status'), (string) DB::table('release_verification_candidates')->value('reason'));
        $this->assertSame(1, DB::table('releases')->count());
        $this->assertDirectoryExists($directory);
        $this->assertSame(1, DB::table('release_verification_scratch')->count());
        $this->artisan('releases:verify-candidates')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($directory);
        $this->assertSame(0, DB::table('release_verification_scratch')->count());
        $this->assertSame(1, DB::table('releases')->count());
    }

    public function test_unsearchable_scratch_parent_is_not_mistaken_for_absent_inventory(): void
    {
        $directory = $this->candidateScratch(1);
        $root = (string) config('release_verification.storage_path');
        chmod($root, 0755);
        chmod($root.'/scratch', 0000);
        $script = <<<'PHP'
require 'vendor/autoload.php';
if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    posix_setgid(65534);
    posix_setuid(65534);
}
try {
    $command = new App\Console\Commands\VerifyReleaseCandidates;
    (new ReflectionMethod($command, 'cleanupTerminalScratch'))->invoke($command, $argv[1]);
    echo 'Cleanup was not blocked.';
} catch (RuntimeException $error) {
    echo $error->getMessage();
}
PHP;
        try {
            $process = new Process([PHP_BINARY, '-r', $script, $root], base_path());
            $process->mustRun();
            $this->assertSame('Verification scratch root is inaccessible or linked; inventory retained.', $process->getOutput());
            $this->assertSame(1, DB::table('release_verification_scratch')->count());
        } finally {
            chmod($root.'/scratch', 0700);
        }
        $this->assertFileExists($directory.'/payload');
    }

    private function candidateScratch(int $id, string $status = 'complete'): string
    {
        DB::table('release_verification_candidates')->insert(['id' => $id, 'identity' => sha1('scratch-'.$id), 'groups_id' => 1,
            'version' => 1, 'status' => $status, 'release_id' => in_array($status, ['complete', 'repaired'], true) ? 77 : null,
            'manifest_path' => '', 'manifest_hash' => str_repeat('a', 64), 'manifest_bytes' => 0,
            'last_unique_at' => now(), 'next_attempt_at' => now()->addHour()]);
        app(ScratchInventory::class)->register($id, 1);
        $directory = config('release_verification.storage_path').'/scratch/'.$id.'-1';
        mkdir($directory, 0700, true);
        file_put_contents($directory.'/payload', 'old payload');

        return $directory;
    }
}

/** A directory whose oversized listing exposes unbounded pre-claim enumeration. */
final class ScratchEnumerationProbe
{
    public mixed $context;

    public static int $reads = 0;

    /** @return array<string, int> */
    public function url_stat(string $path, int $flags): array
    {
        return ['mode' => 0040000 | 0755];
    }

    public function dir_opendir(string $path, int $options): bool
    {
        return true;
    }

    public function dir_readdir(): string
    {
        if (++self::$reads > 102) {
            throw new RuntimeException('Pre-claim sweep exceeded its top-level metadata budget.');
        }

        return self::$reads.'-1';
    }

    public function dir_closedir(): bool
    {
        return true;
    }
}
