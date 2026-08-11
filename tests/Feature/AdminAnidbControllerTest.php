<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AnidbService;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

final class AdminAnidbControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', static function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->text('value')->nullable();
            });
        }

        $this->setGlobalViewData([
            'serverroot' => 'http://localhost',
            'site' => ['dereferrer_link' => ''],
            'usefulLinks' => collect(),
            'isadmin' => true,
            'ismod' => false,
            'loggedin' => false,
            'userTheme' => 'light',
            'userColorScheme' => 'blue',
        ]);
    }

    protected function tearDown(): void
    {
        $this->setGlobalViewData(null);
        Schema::dropIfExists('anidb_info');
        Schema::dropIfExists('anidb_titles');
        Schema::dropIfExists('settings');

        parent::tearDown();
    }

    public function test_edit_view_renders_database_result_object(): void
    {
        $anime = (object) [
            'anidbid' => 123,
            'title' => 'Regression Test Anime',
            'type' => 'TV Series',
            'startdate' => '2026-01-01',
            'enddate' => '2026-03-01',
            'rating' => 825,
            'related' => 'Related title',
            'similar' => 'Similar title',
            'anilist_id' => 456,
            'mal_id' => 789,
            'description' => 'Anime description',
            'creators' => 'Anime creator',
            'categories' => 'Action',
            'characters' => 'Main character',
        ];

        $html = view('admin.anidb.edit', [
            'anime' => $anime,
            'title' => 'AniDB Edit',
            'meta_title' => 'AniDB Edit',
        ])->render();

        $this->assertStringContainsString('Regression Test Anime', $html);
        $this->assertStringContainsString('value="123"', $html);
        $this->assertStringContainsString('Display: 8.3 / 10', $html);
    }

    public function test_list_query_and_view_include_anime_metadata(): void
    {
        Schema::create('anidb_titles', static function (Blueprint $table): void {
            $table->unsignedInteger('anidbid');
            $table->string('type');
            $table->string('lang');
            $table->string('title');
        });
        Schema::create('anidb_info', static function (Blueprint $table): void {
            $table->unsignedInteger('anidbid')->primary();
            $table->string('description')->nullable();
            $table->string('type')->nullable();
            $table->date('startdate')->nullable();
            $table->date('enddate')->nullable();
            $table->string('rating')->nullable();
        });

        try {
            DB::table('anidb_titles')->insert([
                'anidbid' => 123,
                'type' => 'main',
                'lang' => 'en',
                'title' => 'Regression Test Anime',
            ]);
            DB::table('anidb_info')->insert([
                'anidbid' => 123,
                'description' => 'Anime description',
                'type' => 'ONA',
                'startdate' => '2026-01-01',
                'enddate' => '2026-03-01',
                'rating' => '825',
            ]);

            $animeList = (new AnidbService)->getAnimeRange();
            $anime = $animeList->first();

            self::assertNotNull($anime);
            self::assertSame('ONA', $anime->type);
            self::assertSame('2026-01-01', $anime->startdate);
            self::assertSame('2026-03-01', $anime->enddate);
            self::assertSame('825', $anime->rating);

            $html = view('admin.anidb.index', [
                'anidblist' => $animeList,
                'animetitle' => '',
                'title' => 'AniDB List',
                'meta_title' => 'AniDB List',
            ])->render();

            self::assertStringContainsString('ONA', $html);
            self::assertStringContainsString('2026-01-01', $html);
            self::assertStringContainsString('2026-03-01', $html);
            self::assertStringContainsString('8.3', $html);
        } finally {
            Schema::dropIfExists('anidb_info');
            Schema::dropIfExists('anidb_titles');
        }
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function setGlobalViewData(?array $data): void
    {
        $reflection = new ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');

        $property->setValue(null, $data);
    }
}
