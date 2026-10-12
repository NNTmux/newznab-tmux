<?php

namespace Tests\Feature;

use App\Http\Controllers\SearchController;
use App\Http\Requests\SearchReleaseListRequest;
use App\Models\User;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseSearchService;
use App\Services\Search\Contracts\SearchServiceInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SearchReleasesControllerTest extends TestCase
{
    private array $searchCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'testing', 'nntmux.items_per_page' => 20]);
        DB::purge();
        DB::reconnect();
        Cache::flush();
        Schema::create('categories', function (Blueprint $table): void {
            $table->integer('id');
            $table->string('title');
            $table->integer('status')->default(1);
            $table->integer('root_categories_id')->nullable();
        });
        Schema::create('root_categories', function (Blueprint $table): void {
            $table->integer('id');
            $table->string('title');
        });
    }

    #[DataProvider('invalidInputs')]
    public function test_search_rejects_invalid_filter_input(array $parameters, string $field): void
    {
        $request = SearchReleaseListRequest::create('/search', 'GET', $parameters);
        $validator = Validator::make($parameters, $request->rules());

        $this->assertTrue($validator->fails(), $field);
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    public static function invalidInputs(): array
    {
        return [
            [['search' => ['bad']], 'search'],
            [['ob' => 'size_desc; SELECT 1'], 'ob'],
            [['t' => '2000; SELECT 1'], 't'],
            [['t' => ['1000']], 't'],
            [['t' => '1000,bad,2000,1000'], 't'],
            [['minfiles' => '-1'], 'minfiles'],
            [['maxgrabs' => '1.5'], 'maxgrabs'],
            [['mincomments' => '9', 'maxcomments' => '2'], 'maxcomments'],
            [['minsize' => '10', 'maxsize' => '2'], 'maxsize'],
            [['added_from' => '2026-02-31'], 'added_from'],
            [['added_to' => '1969-12-31'], 'added_to'],
            [['added_from' => '2026-10-15', 'added_to' => '2026-10-12'], 'added_to'],
        ];
    }

    public function test_basic_search_applies_actual_mb_dates_and_count_filters_before_paging(): void
    {
        $parameters = [
            'search' => 'framestor', 't' => '2040', 'ob' => 'files_asc', 'page' => '2',
            'minsize' => '1.5', 'maxsize' => '25', 'minfiles' => '3', 'maxfiles' => '9',
            'mingrabs' => '0', 'maxgrabs' => '20', 'mincomments' => '0', 'maxcomments' => '0',
            'added_from' => '2026-10-10', 'added_to' => '2026-10-12',
        ];
        $view = $this->searchView($parameters);
        $call = $this->searchCalls[0];

        $this->assertSame('framestor', $call[0]['searchname']);
        $this->assertSame(20, $call[6]);
        $this->assertSame('files_asc', $call[8]);
        $this->assertSame([2040], $call[12]);
        $ranges = $call[14]['ranges'] ?? [];
        $this->assertSame(['min' => 1572864, 'max' => 26214400], $ranges['size'] ?? []);
        $this->assertSame(['min' => 3, 'max' => 9], $ranges['totalpart'] ?? []);
        $this->assertSame(['min' => 0, 'max' => 20], $ranges['grabs'] ?? []);
        $this->assertSame(['min' => 0, 'max' => 0], $ranges['comments'] ?? []);
        $this->assertSame(172800 + 86399, $ranges['adddate_ts']['max'] - $ranges['adddate_ts']['min']);
        parse_str(parse_url($view->getData()['results']->url(3), PHP_URL_QUERY), $query);
        $this->assertSame('0', $query['maxcomments']);
        $this->assertSame('files_asc', $query['ob']);
        $this->assertSame('1.5', $query['minsize']);
        $this->assertSame('3', $query['page']);
    }

    public function test_filter_only_request_searches_without_a_required_name(): void
    {
        $this->searchView(['t' => '2040', 'minfiles' => '3']);

        $this->assertCount(1, $this->searchCalls);
        $this->assertTrue($this->searchCalls[0][14]['filter_only']);
        $this->assertSame(['min' => 3], $this->searchCalls[0][14]['ranges']['totalpart']);
    }

    public function test_advanced_search_keeps_text_category_sort_and_aliases(): void
    {
        $view = $this->searchView([
            'search_type' => 'adv', 'searchadvr' => 'framestor', 'searchadvcat' => '2040',
            'searchadvgroups' => 'alt.binaries.movies', 'ob' => 'name_desc', 'minsize' => '250',
        ]);

        $call = $this->searchCalls[0];
        $this->assertSame('framestor', $call[0]['searchname']);
        $this->assertSame('alt.binaries.movies', $call[1]);
        $this->assertSame('name_desc', $call[8]);
        $this->assertSame([2040], $call[12]);
        $this->assertSame('framestor', $view->getData()['listQuery']['search']);
        $this->assertSame('2040', $view->getData()['listQuery']['t']);
        $this->assertSame(262144000, $call[14]['ranges']['size']['min']);
    }

    public function test_unsubmitted_form_does_not_search_just_because_sort_is_present(): void
    {
        $view = $this->searchView(['ob' => 'size_asc']);

        $this->assertSame([], $this->searchCalls);
        $this->assertFalse($view->getData()['searchSubmitted'] ?? true);
    }

    private function searchView(array $parameters): View
    {
        $request = SearchReleaseListRequest::create('/search', 'GET', $parameters);
        $request->setContainer($this->app)->setRedirector(app('redirect'));
        $this->app->instance('request', $request);
        app('url')->setRequest($request);
        $request->validateResolved();
        $index = Mockery::mock(SearchServiceInterface::class);
        $index->shouldReceive('isSuggestEnabled')->andReturnFalse();
        $index->shouldReceive('isAutocompleteEnabled')->andReturnFalse();
        $service = Mockery::mock(ReleaseSearchService::class);
        $service->shouldReceive('search')->andReturnUsing(function (...$args) {
            $this->searchCalls[] = $args;

            return collect([(object) ['id' => 1, '_totalrows' => 81, 'searchname' => 'Test release']]);
        });
        $controller = new SearchController($index, $service, new ReleaseBrowseService);
        $controller->userdata = new User;
        $controller->userdata->lastlogin = now();
        $controller->userdata->categoryexclusions = [];

        return $controller->search($request);
    }
}
