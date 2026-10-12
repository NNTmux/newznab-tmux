<?php

namespace Tests\Feature;

use App\Facades\Search;
use App\Models\User;
use App\Services\MovieBrowseService;
use App\Services\Releases\ReleaseBrowseService;
use Dom\HTMLDocument;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class MovieCoverReleaseToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
        DB::purge();

        $browse = Mockery::mock(ReleaseBrowseService::class);
        $browse->shouldReceive('showPasswords')->andReturn('<= 0');
        $this->app->instance(ReleaseBrowseService::class, $browse);
        Search::shouldReceive('isAvailable')->andReturn(false);

        Schema::create('movieinfo', function (Blueprint $table): void {
            $table->id();
            foreach (['imdbid', 'tmdbid', 'traktid', 'title', 'year', 'rating', 'plot', 'genre', 'director', 'actors', 'cover'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            $table->string('imdbid');
            $table->string('guid');
            $table->string('searchname');
            $table->bigInteger('size');
            $table->timestamp('postdate');
            $table->timestamp('adddate');
            $table->integer('categories_id')->default(2030);
            $table->integer('passwordstatus')->default(0);
            $table->integer('haspreview')->default(0);
            $table->integer('jpgstatus')->default(0);
            $table->integer('nfostatus')->default(0);
        });
        Schema::create('video_data', function (Blueprint $table): void {
            $table->unsignedBigInteger('releases_id')->primary();
        });
        DB::table('movieinfo')->insert([
            ['imdbid' => '1234567', 'title' => 'First movie'],
            ['imdbid' => '7654321', 'title' => 'Second movie'],
        ]);
        foreach ([1, 2, 3, 4] as $id) {
            DB::table('releases')->insert([
                'id' => $id,
                'imdbid' => $id === 4 ? '7654321' : '1234567',
                'guid' => str_repeat((string) $id, 32),
                'searchname' => 'Movie.Release.'.$id,
                'size' => 1073741824,
                'postdate' => '2026-10-'.(13 - $id).' 12:00:00',
                'adddate' => '2026-10-12 12:00:00',
                'haspreview' => $id === 1 ? 1 : 0,
                'jpgstatus' => $id <= 2 ? 1 : 0,
                'nfostatus' => $id === 1 ? 1 : 0,
            ]);
        }
        DB::table('video_data')->insert([['releases_id' => 1], ['releases_id' => 2]]);

        $user = User::factory()->make();
        $user->setRelation('roles', collect());
        $this->actingAs($user);
    }

    public function test_movie_release_query_renders_only_available_tools_with_their_modal_identifiers(): void
    {
        $releases = (new MovieBrowseService)->getMovieReleases('1234567');

        $this->assertSame([1, 2, 3], array_column($releases, 'id'));
        $this->assertReleaseTools($releases[0], ['preview-badge', 'sample-badge', 'mediainfo-badge', 'nfo-badge']);
        $this->assertReleaseTools($releases[1], ['sample-badge', 'mediainfo-badge']);
        $this->assertReleaseTools($releases[2], []);
        $this->assertNull($releases[2]->reid);
    }

    public function test_movie_cover_range_keeps_top_two_releases_and_loads_their_available_tools(): void
    {
        $connection = DB::connection();
        DB::shouldReceive('connection')->andReturn($connection);
        DB::shouldReceive('select')->andReturnUsing(function (string $query, array $bindings = []) use ($connection): array {
            if (str_starts_with($query, '(SELECT')) {
                // SQLite requires a SELECT wrapper around each per-movie LIMIT branch.
                $query = 'SELECT * FROM '.str_replace(' UNION ALL (SELECT', ' UNION ALL SELECT * FROM (SELECT', $query);
            }

            return $connection->select($query, $bindings);
        });

        $movies = (new MovieBrowseService)->getMovieRange(1, [], 0, 10, 'title_asc');

        $this->assertCount(2, $movies);
        $this->assertSame(3, (int) $movies[0]->total_releases);
        $this->assertSame([1, 2], array_column($movies[0]->releases, 'id'));
        $this->assertReleaseTools($movies[0]->releases[0], ['preview-badge', 'sample-badge', 'mediainfo-badge', 'nfo-badge']);
        $this->assertReleaseTools($movies[0]->releases[1], ['sample-badge', 'mediainfo-badge']);
        $this->assertReleaseTools($movies[1]->releases[0], []);
    }

    /**
     * @param  list<string>  $availableTools
     */
    private function assertReleaseTools(object $release, array $availableTools): void
    {
        $html = view('movies.partials.release-item', compact('release'))->render();
        $document = HTMLDocument::createFromString('<!doctype html><html><head><title>Movie release</title></head><body>'.$html.'</body></html>');

        foreach (['preview-badge', 'sample-badge', 'mediainfo-badge', 'nfo-badge'] as $tool) {
            $control = $document->querySelector('.'.$tool);
            if (! in_array($tool, $availableTools, true)) {
                $this->assertNull($control);

                continue;
            }

            $this->assertNotNull($control);
            $this->assertSame('button', $control->getAttribute('type'));
            if ($tool === 'mediainfo-badge') {
                $this->assertSame((string) $release->id, $control->getAttribute('data-release-id'));
            } else {
                $this->assertSame($release->guid, $control->getAttribute('data-guid'));
            }
        }
        $this->assertStringEndsWith('/getnzb/'.$release->guid, $document->querySelector('.download-nzb')->getAttribute('href'));
        $this->assertSame($release->guid, $document->querySelector('.add-to-cart')->getAttribute('data-guid'));
        $this->assertSame((string) $release->id, $document->querySelector('.report-trigger')->getAttribute('data-report-release-id'));
    }
}
