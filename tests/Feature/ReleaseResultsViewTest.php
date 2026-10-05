<?php

namespace Tests\Feature;

use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Blade;
use PDO;
use Tests\TestCase;

class ReleaseResultsViewTest extends TestCase
{
    private string $databasePath;

    public function createApplication(): Application
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'release-views-');
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

    public function test_desktop_and_mobile_preserve_release_tools_and_escape_names(): void
    {
        $user = User::factory()->make();
        $user->setRelation('roles', collect());
        $this->actingAs($user);
        $result = (object) [
            'id' => 17,
            'guid' => str_repeat('a', 32),
            'searchname' => '<script>alert("release")</script>.1080p.WEB-DL',
            'size_formatted' => '2.50 GB',
            'totalpart' => 12,
            'haspreview' => 1,
            'jpgstatus' => 1,
            'nfostatus' => 1,
            'reid' => 9,
            'total_report_count' => 2,
            'report_response_count' => 1,
            'all_report_reasons' => 'Missing files',
            'failed_count' => 3,
            'imdbid' => '1234567',
        ];

        $html = Blade::render('<x-release-results :results="$results" />', ['results' => collect([$result])]);
        $document = HTMLDocument::createFromString('<!doctype html><html><head><title>Releases</title></head><body>'.$html.'</body></html>');
        $this->assertStringNotContainsString('<script>', $html);

        foreach (['.release-result-row', '.release-result-card'] as $selector) {
            $view = $document->querySelector($selector);
            $this->assertSame('Select '.$result->searchname, $view->querySelector('.chkRelease')->getAttribute('aria-label'));
            $this->assertStringEndsWith('/getnzb/'.$result->guid, $view->querySelector('.download-nzb')->getAttribute('href'));
            $this->assertSame('button', $view->querySelector('.add-to-cart')->getAttribute('type'));
            $this->assertSame($result->guid, $view->querySelector('.add-to-cart')->getAttribute('data-guid'));
            $this->assertSame('17', $view->querySelector('.report-trigger')->getAttribute('data-report-release-id'));
            $this->assertSame(1, $view->querySelectorAll('a[title="Add to My Movies"]')->length);
            foreach (['nfo-badge', 'preview-badge', 'sample-badge'] as $badge) {
                $this->assertSame($result->guid, $view->querySelector('.'.$badge)->getAttribute('data-guid'));
            }
            $this->assertSame('17', $view->querySelector('.mediainfo-badge')->getAttribute('data-release-id'));
            $this->assertStringContainsString('Reported (2)', $view->textContent);
            $this->assertStringContainsString('Response', $view->textContent);
            $this->assertStringContainsString('Failed (3)', $view->textContent);
            $this->assertStringContainsString('2.50 GB', $view->textContent);
        }
    }

    public function test_sparse_releases_omit_unavailable_tools_and_guest_report_controls(): void
    {
        $result = (object) ['id' => 18, 'guid' => str_repeat('b', 32), 'searchname' => 'Minimal release'];
        $html = Blade::render('<x-release-results :results="$results" />', ['results' => collect([$result])]);
        $document = HTMLDocument::createFromString('<!doctype html><html><head><title>Releases</title></head><body>'.$html.'</body></html>');

        $this->assertSame(2, $document->querySelectorAll('.download-nzb')->length);
        $this->assertSame(0, $document->querySelectorAll('.report-trigger, .nfo-badge, .preview-badge, .sample-badge, .mediainfo-badge')->length);
        $this->assertSame(0, $document->querySelectorAll('a[title="Add to My Movies"]')->length);
        $this->assertStringContainsString('Unknown', $html);
        $this->assertStringContainsString('0.00 GB', $html);
    }

    public function test_detail_header_keeps_download_and_inspection_links_for_long_names(): void
    {
        $release = (object) [
            'id' => 19,
            'guid' => str_repeat('c', 32),
            'searchname' => str_repeat('Long.Release.Name.', 20),
            'nfostatus' => 1,
            'totalpart' => 12,
        ];
        $html = view('details.partials.cover-actions', compact('release'))->render();
        $document = HTMLDocument::createFromString('<!doctype html><html><head><title>Release</title></head><body>'.$html.'</body></html>');

        $this->assertSame($release->searchname, $document->querySelector('h2')->textContent);
        $this->assertStringEndsWith('/getnzb/'.$release->guid, $document->querySelector('.download-nzb')->getAttribute('href'));
        foreach (['nfo-badge', 'filelist-badge', 'add-to-cart'] as $action) {
            $this->assertSame($release->guid, $document->querySelector('.'.$action)->getAttribute('data-guid'));
        }
        $this->assertSame(0, $document->querySelectorAll('.report-trigger')->length);
    }
}
