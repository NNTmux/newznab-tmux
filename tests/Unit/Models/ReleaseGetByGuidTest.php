<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Release;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReleaseGetByGuidTest extends TestCase
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

        $schema = $this->capsule->schema();
        $schema->create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('guid', 40);
            $table->string('searchname')->default('');
            $table->unsignedInteger('groups_id')->nullable();
            $table->integer('categories_id')->nullable();
            $table->unsignedInteger('videos_id')->nullable();
            $table->integer('tv_episodes_id')->nullable();
        });
        $schema->create('categories', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('title');
            $table->unsignedBigInteger('root_categories_id')->nullable();
        });
        $schema->create('root_categories', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('title');
        });
        $schema->create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });
        $schema->create('videos', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->unsignedInteger('tvdb')->default(0);
            $table->unsignedInteger('trakt')->default(0);
            $table->unsignedInteger('tvrage')->default(0);
            $table->unsignedInteger('tvmaze')->default(0);
            $table->unsignedTinyInteger('source')->default(0);
        });
        $schema->create('tv_info', function (Blueprint $table): void {
            $table->unsignedInteger('videos_id')->primary();
            $table->text('summary')->nullable();
            $table->unsignedTinyInteger('image')->default(0);
        });
        $schema->create('tv_episodes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->date('firstaired')->nullable();
            $table->string('se_complete')->default('');
        });
        $schema->create('releases_groups', function (Blueprint $table): void {
            $table->unsignedInteger('releases_id');
            $table->unsignedInteger('groups_id');
            $table->primary(['releases_id', 'groups_id']);
        });

        $this->capsule->table('root_categories')->insert(['id' => 2000, 'title' => 'Movies']);
        $this->capsule->table('categories')->insert(['id' => 2040, 'title' => 'HD', 'root_categories_id' => 2000]);
    }

    #[Test]
    public function category_ids_join_the_root_category_and_category_ids(): void
    {
        $this->capsule->table('releases')->insert(['guid' => 'guid-with-category', 'categories_id' => 2040]);

        $release = Release::getByGuid('guid-with-category');

        self::assertInstanceOf(Release::class, $release);
        self::assertSame('2000,2040', $release->category_ids);
        self::assertSame('Movies > HD', $release->category_name);
    }

    #[Test]
    public function category_ids_are_empty_when_the_release_has_no_category(): void
    {
        $this->capsule->table('releases')->insert(['guid' => 'guid-without-category', 'categories_id' => 9999]);

        $release = Release::getByGuid('guid-without-category');

        self::assertInstanceOf(Release::class, $release);
        self::assertSame('', $release->category_ids);
    }

    #[Test]
    public function category_ids_are_set_on_every_release_when_looking_up_several_guids(): void
    {
        $this->capsule->table('releases')->insert([
            ['guid' => 'guid-a', 'categories_id' => 2040],
            ['guid' => 'guid-b', 'categories_id' => 2040],
        ]);

        $releases = Release::getByGuid(['guid-a', 'guid-b']);

        self::assertSame(['2000,2040', '2000,2040'], $releases->pluck('category_ids')->all());
    }
}
