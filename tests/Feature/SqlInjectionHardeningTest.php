<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MusicService;
use App\Services\RegexService;
use App\Services\Releases\ReleaseBrowseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SqlInjectionHardeningTest extends TestCase
{
    private const string INJECTED_ID = '1 OR SLEEP(5)';

    /**
     * @var array<string, mixed>
     */
    private array $originalRequest = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalRequest = $_REQUEST;
    }

    protected function tearDown(): void
    {
        $_REQUEST = $this->originalRequest;

        parent::tearDown();
    }

    #[Test]
    public function music_genre_filter_only_ever_adds_an_integer_to_the_sql(): void
    {
        $_REQUEST = ['genre' => self::INJECTED_ID];

        $clause = $this->musicService()->getBrowseBy();

        $this->assertSame('  AND m.genres_id = 1', $clause);
    }

    #[Test]
    public function music_genre_filter_ignores_array_input(): void
    {
        $_REQUEST = ['genre' => ['1']];

        $clause = $this->musicService()->getBrowseBy();

        $this->assertSame(' ', $clause);
    }

    #[Test]
    public function music_order_rejects_directions_that_only_start_with_asc(): void
    {
        $order = $this->musicService()->getMusicOrder('posted_asc, (SELECT 1)');

        $this->assertSame('desc', $order[1]);
    }

    #[Test]
    public function category_regex_add_and_update_store_only_an_integer_category_id(): void
    {
        Schema::create('category_regexes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('group_regex');
            $table->string('regex');
            $table->integer('status');
            $table->string('description');
            $table->integer('ordinal');
            $table->string('categories_id');
        });
        $regexes = new RegexService('category_regexes');
        $data = [
            'group_regex' => 'alt.binaries.test',
            'regex' => '/test/',
            'status' => 1,
            'description' => 'test',
            'ordinal' => 0,
            'categories_id' => "5010, (SELECT 'injected')",
        ];

        $regexes->addRegex($data);
        $afterAdd = DB::table('category_regexes')->value('categories_id');
        $regexes->updateRegex([...$data, 'id' => 1, 'categories_id' => "5020, description = 'injected'"]);
        $afterUpdate = DB::table('category_regexes')->first();

        $this->assertSame('5010', (string) $afterAdd);
        $this->assertSame('5020', (string) $afterUpdate->categories_id);
        $this->assertSame('test', $afterUpdate->description);
    }

    #[Test]
    public function watchlist_category_lists_only_ever_add_integers_to_the_sql(): void
    {
        $sql = (new ReleaseBrowseService)->uSQL([(object) ['videos_id' => 7, 'categories' => '5030|5040) OR (1=1']], 'videos_id');

        $this->assertSame('(1=2 OR (r.videos_id = 7 AND r.categories_id IN (5030,5040)) ) ', $sql);
    }

    private function musicService(): MusicService
    {
        return (new \ReflectionClass(MusicService::class))->newInstanceWithoutConstructor();
    }
}
