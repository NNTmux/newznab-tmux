<?php

namespace Tests\Feature;

use Dom\HTMLDocument;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use PDO;
use Tests\TestCase;

class SearchResultsControlsViewTest extends TestCase
{
    private string $databasePath;

    public function createApplication(): Application
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'search-controls-');
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['config']->set([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $this->databasePath,
                'cache.default' => 'array',
                'session.driver' => 'array',
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function test_advanced_link_keeps_search_category_filters_and_sort_without_page(): void
    {
        $this->bindRequest(['search' => 'Some & Release', 't' => '2000', 'minsize' => '0.5', 'ob' => 'name_desc', 'page' => '3']);
        $document = $this->renderSearch();
        $link = $document->querySelector('a[aria-label="Advanced search filters"]');
        $this->assertNotNull($link);
        $this->assertSame([
            'search' => 'Some & Release', 't' => '2000', 'minsize' => '0.5', 'ob' => 'name_desc', 'search_type' => 'adv',
        ], $this->linkQuery($link->getAttribute('href')));
        $this->assertSame('name_desc', $document->querySelector('#searchForm input[name="ob"]')->getAttribute('value'));
        $this->assertSame(0, $document->querySelectorAll('#searchForm input[name="page"]')->length);
    }

    public function test_empty_results_keep_all_filter_controls_and_restore_zero_and_decimal_bounds(): void
    {
        $this->bindRequest(['search_type' => 'adv', 'search' => 'No matches', 'minfiles' => '0', 'maxfiles' => '0', 'minsize' => '0.25', 'added_from' => '2026-10-01']);
        $document = $this->renderSearch();
        foreach (['group', 'searchadvposter', 'minage', 'maxage', 'minsize', 'maxsize', 'added_from', 'added_to', 'minfiles', 'maxfiles', 'mingrabs', 'maxgrabs', 'mincomments', 'maxcomments'] as $field) {
            $this->assertNotNull($document->querySelector('#searchForm input[name="'.$field.'"]'), $field);
        }
        $this->assertSame('0', $document->querySelector('input[name="minfiles"]')->getAttribute('value'));
        $this->assertSame('0', $document->querySelector('input[name="maxfiles"]')->getAttribute('value'));
        $this->assertSame('0.25', $document->querySelector('input[name="minsize"]')->getAttribute('value'));
        $this->assertSame('any', $document->querySelector('input[name="minsize"]')->getAttribute('step'));
        $this->assertSame('date', $document->querySelector('input[name="added_from"]')->getAttribute('type'));
        $this->assertStringContainsString('Posted Age', $document->querySelector('label[for="minage"]')->textContent);
        $this->assertStringContainsString('No results found', $document->body->textContent);
    }

    public function test_initial_advanced_form_does_not_report_an_unperformed_search(): void
    {
        $this->bindRequest(['search_type' => 'adv']);
        $document = $this->renderSearch(['searchSubmitted' => false]);

        $this->assertNotNull($document->querySelector('input[name="minfiles"]'));
        $this->assertStringNotContainsString('No results found', $document->body->textContent);
    }

    public function test_old_input_and_legacy_aliases_render_safely_after_validation_errors(): void
    {
        $this->bindRequest(['subject' => 'Legacy subject', 't' => '2000', 'search_type' => 'adv']);
        session()->flashInput(['search' => 'Edited query', 'minfiles' => '0', 'minsize' => ['bad'], 't' => ['bad'], 'ob' => ['bad']]);
        $document = $this->renderSearch();
        $this->assertSame('Edited query', $document->querySelector('input[name="search"]')->getAttribute('value'));
        $this->assertSame('0', $document->querySelector('input[name="minfiles"]')->getAttribute('value'));
        $this->assertSame('', $document->querySelector('input[name="minsize"]')->getAttribute('value'));
        session()->flush();
        $document = $this->renderSearch();
        $this->assertSame('Legacy subject', $document->querySelector('input[name="search"]')->getAttribute('value'));
        foreach (['id' => 'Legacy id', 'searchadvr' => 'Advanced alias'] as $alias => $value) {
            $this->bindRequest([$alias => $value]);
            $this->assertSame($value, $this->renderSearch()->querySelector('input[name="search"]')->getAttribute('value'));
        }
    }

    public function test_sort_headers_toggle_direction_preserve_filters_and_reset_page(): void
    {
        $query = ['search' => 'Release', 't' => '2000', 'search_type' => 'adv', 'minfiles' => '0', 'maxcomments' => '4', 'added_from' => '2026-10-01', 'ob' => 'name_asc', 'page' => '2'];
        $this->bindRequest($query);
        $results = new LengthAwarePaginator([(object) ['id' => 1, 'guid' => str_repeat('a', 32), 'searchname' => 'Release']], 3, 1, 2, ['path' => '/search']);
        $html = Blade::render('<x-release-results-panel :results="$results" :query-params="$query" />', compact('results', 'query'));
        $document = $this->document($html);
        foreach (['name' => 'name_desc', 'cat' => 'cat_asc', 'added' => 'added_asc', 'size' => 'size_asc', 'files' => 'files_asc', 'stats' => 'stats_asc'] as $key => $order) {
            $header = $document->querySelector('th[data-sort="'.$key.'"]');
            $this->assertNotNull($header, $key);
            $this->assertSame($key === 'name' ? 'ascending' : 'none', $header->getAttribute('aria-sort'));
            $params = $this->linkQuery($header->querySelector('a')->getAttribute('href'));
            $this->assertSame($order, $params['ob']);
            $this->assertArrayNotHasKey('page', $params);
            $this->assertSame('0', $params['minfiles']);
            $this->assertSame('4', $params['maxcomments']);
            $this->assertSame('2026-10-01', $params['added_from']);
            $this->assertSame('Release', $params['search']);
        }
        foreach (['Category (Ascending)' => 'cat_asc', 'Comments (Most)' => 'comments_desc', 'Grabs (Most)' => 'stats_desc'] as $label => $sort) {
            $found = false;
            foreach ($document->querySelectorAll('a[role="menuitem"]') as $link) {
                if (trim($link->textContent) === $label) {
                    $found = true;
                    $params = $this->linkQuery($link->getAttribute('href'));
                    $this->assertSame($sort, $params['ob']);
                    $this->assertArrayNotHasKey('page', $params);
                    $this->assertSame('0', $params['minfiles']);
                }
            }
            $this->assertTrue($found, $label);
        }
        $next = $document->querySelector('a[rel="next"]');
        $this->assertNotNull($next);
        $params = $this->linkQuery($next->getAttribute('href'));
        $this->assertSame('3', $params['page']);
        $this->assertSame('name_asc', $params['ob']);
        $this->assertSame('0', $params['minfiles']);

        foreach (['name', 'cat', 'added', 'size', 'files', 'stats'] as $key) {
            $query['ob'] = $key.'_desc';
            $this->bindRequest($query);
            $html = Blade::render('<x-release-results :results="$results" :query-params="$query" />', compact('results', 'query'));
            $header = $this->document($html)->querySelector('th[data-sort="'.$key.'"]');
            $this->assertSame('descending', $header->getAttribute('aria-sort'));
            $this->assertNotNull($header->querySelector('.fa-arrow-down'));
            $this->assertSame($key.'_asc', $this->linkQuery($header->querySelector('a')->getAttribute('href'))['ob']);
        }
    }

    public function test_posted_date_header_and_malformed_sort_are_safe(): void
    {
        $this->bindRequest(['ob' => ['name_asc'], 'search' => ['bad']]);
        $results = collect();
        $html = Blade::render('<x-release-results :results="$results" date-field="postdate" /><x-sort-dropdown />', compact('results'));
        $document = $this->document($html);
        $header = $document->querySelector('th[data-sort="posted"]');
        $this->assertNotNull($header);
        $this->assertStringContainsString('Posted', $header->textContent);
        $this->assertSame('descending', $header->getAttribute('aria-sort'));
        $this->assertSame('posted_asc', $this->linkQuery($header->querySelector('a')->getAttribute('href'))['ob']);

        $this->bindRequest(['ob' => 'unsupported_direction']);
        $html = Blade::render('<x-release-results :results="$results" date-field="postdate" /><x-sort-dropdown />', compact('results'));
        $document = $this->document($html);
        $this->assertSame('descending', $document->querySelector('th[data-sort="posted"]')->getAttribute('aria-sort'));
    }

    public function test_navigation_uses_validated_query_while_form_displays_flashed_invalid_inputs(): void
    {
        $this->bindRequest(['search' => 'Current request', 'searchadvsizefrom' => '2', 'maxfiles' => '999']);
        session()->flashInput(['search_type' => 'adv', 'search' => 'Edited query', 't' => '4000', 'ob' => 'size_desc', 'maxfiles' => 'invalid', 'minfiles' => ['bad']]);
        $listQuery = ['search' => 'Validated query', 't' => '2000', 'ob' => 'name_asc', 'search_type' => 'adv', 'minsize' => '1024', 'minfiles' => '0', 'maxfiles' => '5'];
        $results = new LengthAwarePaginator([(object) ['id' => 1, 'guid' => str_repeat('a', 32), 'searchname' => 'Release']], 3, 1, 1, ['path' => '/search']);
        $document = $this->renderSearch(compact('listQuery', 'results'));

        $this->assertSame('Edited query', $document->querySelector('input[name="search"]')->getAttribute('value'));
        $this->assertSame('invalid', $document->querySelector('input[name="maxfiles"]')->getAttribute('value'));
        $this->assertSame('', $document->querySelector('input[name="minfiles"]')->getAttribute('value'));
        $this->assertSame('size_desc', $document->querySelector('#searchForm input[name="ob"]')->getAttribute('value'));
        foreach (['a[aria-label="Advanced search filters"]', 'th[data-sort="name"] a', 'a[rel="next"]', 'a[role="menuitem"]'] as $selector) {
            foreach ($document->querySelectorAll($selector) as $link) {
                $query = $this->linkQuery($link->getAttribute('href'));
                $this->assertSame('Validated query', $query['search']);
                $this->assertSame('2000', $query['t']);
                $this->assertSame('1024', $query['minsize']);
                $this->assertSame('0', $query['minfiles']);
                $this->assertSame('5', $query['maxfiles']);
                $this->assertArrayNotHasKey('searchadvsizefrom', $query);
            }
        }
        $this->assertSame('1024', $document->querySelector('input[name="minsize"]')->getAttribute('value'));
        $this->assertSame('ascending', $document->querySelector('th[data-sort="name"]')->getAttribute('aria-sort'));
        $this->assertSame('name_asc', $this->linkQuery($document->querySelector('a[rel="next"]')->getAttribute('href'))['ob']);
        $this->assertSame('name_asc', $this->linkQuery($document->querySelector('a[aria-label="Advanced search filters"]')->getAttribute('href'))['ob']);
    }

    public function test_legacy_subject_and_filename_criteria_survive_form_submission(): void
    {
        $this->bindRequest(['search_type' => 'adv', 'searchadvsubject' => 'Original subject', 'searchadvfilename' => 'original.mkv']);
        session()->flashInput(['searchadvsubject' => 'Edited subject']);
        $document = $this->renderSearch(['listQuery' => ['search_type' => 'adv', 'searchadvsubject' => 'Original subject', 'searchadvfilename' => 'original.mkv']]);
        foreach (['searchadvsubject' => 'Edited subject', 'searchadvfilename' => 'original.mkv'] as $field => $value) {
            $input = $document->querySelector('#searchForm input[name="'.$field.'"]');
            $this->assertNotNull($input);
            $this->assertSame('hidden', $input->getAttribute('type'));
            $this->assertSame($value, $input->getAttribute('value'));
        }
    }

    /** @param array<string, mixed> $query */
    private function bindRequest(array $query): void
    {
        $request = Request::create('/search', 'GET', $query);
        $request->setLaravelSession(app('session')->driver());
        $this->app->instance('request', $request);
        app('url')->setRequest($request);
    }

    /** @param array<string, mixed> $data */
    private function renderSearch(array $data = []): HTMLDocument
    {
        // The DB-backed menu composer is unrelated to search controls; render the real guest layout.
        app('events')->forget('composing: search.*');
        app('events')->forget('composing: layouts.main');

        return $this->document(view('search.index', array_replace([
            'results' => [], 'site' => (object) [], 'userTheme' => 'light', 'userColorScheme' => 'blue',
            'isadmin' => false, 'ismod' => false, 'loggedin' => false, 'catlist' => [2000 => 'Movies'], 'errors' => new ViewErrorBag,
        ], $data))->render());
    }

    private function document(string $html): HTMLDocument
    {
        if (str_starts_with(ltrim($html), '<!DOCTYPE html>')) {
            return HTMLDocument::createFromString($html);
        }

        return HTMLDocument::createFromString('<!doctype html><html><head><title>Search</title></head><body>'.$html.'</body></html>');
    }

    /** @return array<string, mixed> */
    private function linkQuery(string $href): array
    {
        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);

        return $query;
    }
}
