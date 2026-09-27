<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Part;
use App\Models\ReleasesGroups;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompositePrimaryKeyTest extends TestCase
{
    private Capsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Application(sys_get_temp_dir());
        $this->capsule = new Capsule($container);
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->capsule->setEventDispatcher(new Dispatcher($container));
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $this->capsule->schema()->create('releases_groups', function (Blueprint $table): void {
            $table->unsignedInteger('releases_id');
            $table->unsignedInteger('groups_id');
            $table->primary(['releases_id', 'groups_id']);
        });
        $this->capsule->schema()->create('parts', function (Blueprint $table): void {
            $table->unsignedBigInteger('binaries_id');
            $table->string('messageid')->default('');
            $table->unsignedBigInteger('number')->default(0);
            $table->unsignedInteger('partnumber')->default(0);
            $table->unsignedInteger('size')->default(0);
            $table->primary(['binaries_id', 'partnumber']);
        });

        $this->capsule->table('releases_groups')->insert([
            ['releases_id' => 1, 'groups_id' => 10],
            ['releases_id' => 1, 'groups_id' => 20],
        ]);
        $this->capsule->table('parts')->insert([
            ['binaries_id' => 5, 'partnumber' => 1, 'size' => 100],
            ['binaries_id' => 5, 'partnumber' => 2, 'size' => 200],
        ]);
    }

    #[Test]
    public function saving_updates_only_the_row_matching_every_key_column(): void
    {
        $part = Part::query()->where(['binaries_id' => 5, 'partnumber' => 2])->firstOrFail();
        $part->size = 999;
        $part->save();

        self::assertSame(
            [1 => 100, 2 => 999],
            $this->capsule->table('parts')->orderBy('partnumber')->pluck('size', 'partnumber')->map(fn ($size): int => (int) $size)->all()
        );
    }

    #[Test]
    public function changing_a_key_column_updates_the_originally_loaded_row(): void
    {
        $membership = ReleasesGroups::query()->where(['releases_id' => 1, 'groups_id' => 20])->firstOrFail();
        $membership->groups_id = 30;
        $membership->save();

        self::assertSame([10, 30], $this->groupIdsForRelease(1));
    }

    #[Test]
    public function deleting_removes_only_the_row_matching_every_key_column(): void
    {
        ReleasesGroups::query()->where(['releases_id' => 1, 'groups_id' => 20])->firstOrFail()->delete();

        self::assertSame([10], $this->groupIdsForRelease(1));
    }

    #[Test]
    public function refresh_reloads_the_row_matching_every_key_column(): void
    {
        $part = Part::query()->where(['binaries_id' => 5, 'partnumber' => 2])->firstOrFail();
        $this->capsule->table('parts')->where(['binaries_id' => 5, 'partnumber' => 2])->update(['size' => 250]);

        $part->refresh();

        self::assertSame(2, (int) $part->partnumber);
        self::assertSame(250, (int) $part->size);
    }

    #[Test]
    public function creating_a_record_keeps_the_given_key_values(): void
    {
        $membership = ReleasesGroups::query()->create(['releases_id' => 2, 'groups_id' => 40]);

        self::assertSame(2, (int) $membership->releases_id);
        self::assertSame([40], $this->groupIdsForRelease(2));
    }

    #[Test]
    public function persisting_without_every_key_column_is_refused(): void
    {
        $partial = ReleasesGroups::query()->select('releases_id')->where('groups_id', 20)->firstOrFail();

        try {
            $partial->delete();
            self::fail('Deleting without the full composite key should throw.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('groups_id', $exception->getMessage());
        }

        self::assertSame([10, 20], $this->groupIdsForRelease(1));
    }

    /**
     * @return list<int>
     */
    private function groupIdsForRelease(int $releaseId): array
    {
        return $this->capsule->table('releases_groups')
            ->where('releases_id', $releaseId)
            ->orderBy('groups_id')
            ->pluck('groups_id')
            ->map(fn ($groupId): int => (int) $groupId)
            ->values()
            ->all();
    }
}
