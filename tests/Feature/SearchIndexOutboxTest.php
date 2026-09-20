<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Jobs\FlushSearchIndexOutbox;
use App\Services\Search\SearchIndexOutbox;
use App\Services\Search\SearchIndexOutboxDispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class SearchIndexOutboxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('search_index_outbox');
        Schema::dropIfExists('search_index_failures');

        Schema::create('search_index_outbox', static function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 16);
            $table->json('payload')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('search_index_failures', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('release_id')->unique();
            $table->string('operation', 32);
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    #[Test]
    public function release_event_is_transactional_and_dispatches_only_after_commit(): void
    {
        Queue::fake();
        $outbox = app(SearchIndexOutbox::class);

        try {
            DB::transaction(function () use ($outbox): void {
                $outbox->upsertRelease(42);

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        self::assertSame(0, DB::table('search_index_outbox')->count());
        Queue::assertNotPushed(FlushSearchIndexOutbox::class);

        DB::transaction(static function () use ($outbox): void {
            $outbox->upsertReleases([42, 42, 0]);
        });

        self::assertSame(1, DB::table('search_index_outbox')->count());
        Queue::assertPushed(FlushSearchIndexOutbox::class, 1);
    }

    #[Test]
    public function dispatcher_compacts_actions_bulk_writes_and_preserves_newer_rows(): void
    {
        DB::table('search_index_outbox')->insert([
            $this->outboxRow(10, 'upsert'),
            $this->outboxRow(10, 'update'),
            $this->outboxRow(20, 'delete'),
        ]);

        Search::shouldReceive('bulkInsertReleases')
            ->once()
            ->withArgs(static fn (array $documents): bool => count($documents) === 1 && $documents[0]['id'] === 10)
            ->andReturnUsing(function (): array {
                DB::table('search_index_outbox')->insert($this->outboxRow(10, 'upsert'));

                return ['success' => 1, 'errors' => 0];
            });
        Search::shouldReceive('deleteReleases')
            ->once()
            ->with([20])
            ->andReturn(['success' => 1, 'errors' => 0]);

        $dispatcher = new SearchIndexOutboxDispatcher(
            static fn (array $releaseIds): array => $releaseIds === [10]
                ? [['id' => 10, 'searchname' => 'Example release']]
                : [],
        );

        self::assertSame(3, $dispatcher->dispatchReleaseBatch());
        self::assertSame(1, DB::table('search_index_outbox')->count());
        self::assertSame('upsert', DB::table('search_index_outbox')->value('action'));
    }

    #[Test]
    public function dispatcher_retains_rows_and_records_failures_when_bulk_indexing_fails(): void
    {
        DB::table('search_index_outbox')->insert($this->outboxRow(99, 'upsert'));
        Search::shouldReceive('bulkInsertReleases')
            ->once()
            ->andReturn(['success' => 0, 'errors' => 1]);

        $dispatcher = new SearchIndexOutboxDispatcher(
            static fn (array $releaseIds): array => [['id' => $releaseIds[0], 'searchname' => 'Failure']],
        );

        try {
            $dispatcher->dispatchReleaseBatch();
            self::fail('Expected the failed bulk operation to throw.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('reported 1 error', $exception->getMessage());
        }

        self::assertSame(1, DB::table('search_index_outbox')->count());
        $failure = DB::table('search_index_failures')->where('release_id', 99)->first();
        self::assertNotNull($failure);
        self::assertSame('upsert', $failure->operation);
        self::assertNull($failure->resolved_at);

        Search::shouldReceive('bulkInsertReleases')
            ->once()
            ->andReturn(['success' => 1, 'errors' => 0]);

        self::assertSame(1, $dispatcher->dispatchReleaseBatch());
        self::assertSame(0, DB::table('search_index_outbox')->count());
        self::assertNotNull(DB::table('search_index_failures')->where('release_id', 99)->value('resolved_at'));
    }

    #[Test]
    public function missing_release_projection_is_removed_from_the_search_index(): void
    {
        DB::table('search_index_outbox')->insert($this->outboxRow(77, 'upsert'));
        Search::shouldReceive('deleteReleases')
            ->once()
            ->with([77])
            ->andReturn(['success' => 1, 'errors' => 0]);

        $dispatcher = new SearchIndexOutboxDispatcher(static fn (array $releaseIds): array => []);

        self::assertSame(1, $dispatcher->dispatchReleaseBatch());
        self::assertSame(0, DB::table('search_index_outbox')->count());
    }

    /**
     * @return array{entity_type: string, entity_id: int, action: string, payload: null, created_at: Carbon}
     */
    private function outboxRow(int $releaseId, string $action): array
    {
        return [
            'entity_type' => 'release',
            'entity_id' => $releaseId,
            'action' => $action,
            'payload' => null,
            'created_at' => now(),
        ];
    }
}
