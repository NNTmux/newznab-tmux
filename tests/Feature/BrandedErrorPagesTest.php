<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BrandedErrorPagesTest extends TestCase
{
    private string $databasePath;

    public function createApplication(): Application
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'error-pages-');
        new PDO('sqlite:'.$this->databasePath);

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
        if (isset($this->app) && $this->app->maintenanceMode()->active()) {
            $this->app->maintenanceMode()->deactivate();
        }

        parent::tearDown();

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    #[Test]
    public function an_unknown_url_renders_the_branded_not_found_page(): void
    {
        $this->get('/definitely-missing-'.uniqid())
            ->assertNotFound()
            ->assertSee('class="error-page ', false)
            ->assertSee('Page not found')
            ->assertSee('href="'.url('search').'"', false)
            ->assertSee('href="'.url('/').'"', false);
    }

    #[Test]
    public function maintenance_mode_shows_the_retry_hint(): void
    {
        $this->app->maintenanceMode()->activate(['retry' => 60, 'status' => 503, 'except' => [], 'secret' => null, 'template' => null, 'redirect' => null, 'refresh' => null]);

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Service unavailable')
            ->assertSee('Please try again in 60 seconds.');
    }

    #[Test]
    public function every_error_page_renders_without_touching_the_database(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        foreach ([401, 402, 403, 404, 419, 429, 500, 503] as $status) {
            $html = view("errors.{$status}", ['exception' => new HttpException($status)])->render();

            $this->assertStringContainsString('class="error-page ', $html, (string) $status);
            $this->assertStringContainsString('>'.$status.'</p>', $html, (string) $status);
        }

        $html = view('errors.category-disabled', ['category' => 'Movies > HD', 'isSubcategory' => true])->render();
        $this->assertStringContainsString('Movies &gt; HD', $html);
        $this->assertStringContainsString('subcategory disabled', $html);
        $this->assertStringContainsString('href="'.url('profileedit').'"', $html);

        $this->assertSame(0, $queries);
    }

    #[Test]
    public function error_pages_stay_readable_when_built_assets_are_missing(): void
    {
        $this->app->instance(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                throw new ViteManifestNotFoundException('Vite manifest not found');
            }
        });

        $html = view('errors.500', ['exception' => new HttpException(500)])->render();

        $this->assertStringContainsString('.error-page__code { font-size', $html);
        $this->assertStringContainsString('Something went wrong', $html);
    }

    #[Test]
    public function the_error_layout_never_reads_the_session_user_or_site_settings(): void
    {
        $layout = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path('views/errors/layout.blade.php')));

        foreach (['auth(', 'Auth::', '@auth', '$site', 'Settings::', 'session('] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $layout, $forbidden);
        }

        $this->assertStringContainsString("@include('partials.theme-init', ['static' => true])", $layout);
        $this->assertFileDoesNotExist(resource_path('views/errors/minimal.blade.php'));
    }
}
