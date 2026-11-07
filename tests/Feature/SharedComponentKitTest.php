<?php

namespace Tests\Feature;

use App\Models\MovieInfo;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

class SharedComponentKitTest extends TestCase
{
    private string $databasePath;

    public function createApplication(): Application
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'component-kit-');
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
        parent::tearDown();
        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    #[Test]
    public function buttons_render_links_or_buttons_with_the_scheme_accent(): void
    {
        $link = Blade::render('<x-button href="/browse" icon="fa-solid fa-film">Browse</x-button>');
        $this->assertStringContainsString('<a href="/browse"', $link);
        $this->assertStringContainsString('bg-accent text-on-accent', $link);
        $this->assertStringContainsString('<i aria-hidden="true" class="fa-solid fa-film"></i>', $link);

        $busy = Blade::render('<x-button type="submit" variant="danger" loading>Delete</x-button>');
        $this->assertStringContainsString('<button type="submit"', $busy);
        $this->assertStringContainsString('bg-danger-600', $busy);
        $this->assertStringContainsString('disabled aria-busy="true"', $busy);
        $this->assertStringContainsString('fa-spinner fa-spin', $busy);

        $admin = Blade::render('<x-admin.button tone="gray" href="/admin">Back</x-admin.button>');
        $this->assertStringContainsString('<a href="/admin"', $admin);
        $this->assertStringContainsString('border-line bg-surface text-fg', $admin);
    }

    #[Test]
    public function icon_buttons_always_have_an_accessible_name(): void
    {
        $html = Blade::render('<x-icon-button icon="fa-solid fa-trash" label="Delete release" variant="danger" />');

        $this->assertStringContainsString('aria-label="Delete release"', $html);
        $this->assertStringContainsString('title="Delete release"', $html);

        try {
            Blade::render('<x-icon-button icon="fa-solid fa-trash" />');
            $this->fail('An icon button without a label must not render.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('label', $exception->getMessage());
        }
    }

    #[Test]
    public function form_controls_expose_validation_errors_to_assistive_technology(): void
    {
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['settings.email' => ['Enter a valid e-mail address.']])));

        $html = Blade::render(
            '<x-field label="E-mail" for="email" name="settings[email]" help="Used for notifications"><x-input id="email" name="settings[email]" /></x-field>'
        );

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="email-error"', $html);
        $this->assertStringContainsString('id="email-error"', $html);
        $this->assertStringContainsString('Enter a valid e-mail address.', $html);
        $this->assertStringContainsString('id="email-help"', $html);

        $summary = Blade::render('<x-form-errors />');
        $this->assertStringContainsString('role="alert"', $summary);
        $this->assertStringContainsString('<li>Enter a valid e-mail address.</li>', $summary);

        view()->share('errors', new ViewErrorBag);
        $this->assertStringNotContainsString('aria-invalid="true"', Blade::render('<x-input id="email" name="settings[email]" />'));
        $this->assertSame('', trim(Blade::render('<x-form-errors />')));
    }

    #[Test]
    public function toggles_always_submit_an_explicit_on_or_off_value(): void
    {
        $on = Blade::render('<x-toggle name="settings[enabled]" :checked="true" label="Enabled" />');
        $this->assertStringContainsString('<input type="hidden" name="settings[enabled]" value="0">', $on);
        $this->assertMatchesRegularExpression('/role="switch"[^>]*value="1"[^>]*checked/', $on);
        $this->assertStringContainsString('for="toggle-settings-enabled"', $on);

        $off = Blade::render('<x-toggle name="flag" :checked="false" />');
        $this->assertDoesNotMatchRegularExpression('/role="switch"[^>]*checked/', $off);
    }

    #[Test]
    public function table_headers_have_scope_sort_state_and_static_widths(): void
    {
        $sorted = Blade::render('<x-th href="/admin/users?ob=name_desc" sort="asc" width="24">Name</x-th>');
        $this->assertStringContainsString('scope="col"', $sorted);
        $this->assertStringContainsString('aria-sort="ascending"', $sorted);
        $this->assertStringContainsString('fa-sort-up', $sorted);
        $this->assertStringContainsString('w-24', $sorted);

        $plain = Blade::render('<x-admin.th width="99">Size</x-admin.th>');
        $this->assertStringContainsString('scope="col"', $plain);
        $this->assertStringNotContainsString('aria-sort', $plain);
        $this->assertStringNotContainsString('w-99', $plain);
    }

    #[Test]
    public function badges_alerts_and_skeletons_use_semantic_tones(): void
    {
        $this->assertStringContainsString('bg-success-50', Blade::render('<x-admin.badge tone="green">Active</x-admin.badge>'));
        $this->assertStringContainsString('bg-warning-50', Blade::render('<x-badge tone="warning">Pending</x-badge>'));
        $this->assertStringContainsString('role="status"', Blade::render('<x-alert tone="info">Heads up</x-alert>'));
        $this->assertStringContainsString('role="alert"', Blade::render('<x-alert tone="danger">Broken</x-alert>'));
        $this->assertSame(2, substr_count(Blade::render('<x-skeleton :lines="2" />'), 'class="skeleton'));
        $this->assertStringContainsString('aria-current="page"', Blade::render('<x-nav-link href="/" :active="true">Home</x-nav-link>'));
    }

    #[Test]
    public function link_dropdowns_show_the_current_choice_and_plain_links(): void
    {
        $html = Blade::render('<x-link-dropdown label="Category" :items="$items" placeholder="All categories" />', [
            'items' => [
                ['label' => 'All categories', 'url' => '/XXX', 'active' => false],
                ['label' => 'HD', 'url' => '/XXX/HD?t=6040', 'active' => true],
            ],
        ]);

        $this->assertStringContainsString('<span>Category: HD</span>', $html);
        $this->assertStringContainsString('aria-haspopup="menu"', $html);
        $this->assertMatchesRegularExpression('/<a href="\/XXX\/HD\?t=6040"\s+role="menuitem"\s+aria-current="true"/', $html);
        $this->assertStringContainsString('href="/XXX"', $html);

        $adult = (string) file_get_contents(resource_path('views/xxx/index.blade.php'));
        $this->assertStringContainsString('<x-link-dropdown label="Category"', $adult);
        $this->assertStringContainsString('<x-sort-dropdown :ordering="$ordering ?? []" />', $adult);
        $this->assertStringNotContainsString('Order by:', $adult);
    }

    #[Test]
    public function rotten_tomatoes_scores_show_fresh_or_rotten_and_hide_when_unknown(): void
    {
        $fresh = Blade::render('<x-rotten-tomatoes-score score="78%" />');
        $this->assertStringContainsString('78%</span>', $fresh);
        $this->assertStringContainsString('Rotten Tomatoes · Fresh', $fresh);
        $this->assertStringContainsString('fa-apple-whole', $fresh);
        $this->assertStringContainsString('text-success-700', $fresh, 'Fresh is green (good).');

        $rotten = Blade::render('<x-rotten-tomatoes-score score="17%" />');
        $this->assertStringContainsString('Rotten Tomatoes · Rotten', $rotten);
        $this->assertStringContainsString('fa-splotch', $rotten);
        $this->assertStringContainsString('text-danger-600', $rotten, 'Rotten is red (bad).');

        foreach (['N/A', '', 'abc'] as $missing) {
            $this->assertSame('', trim(Blade::render('<x-rotten-tomatoes-score :score="$score" />', ['score' => $missing])), $missing);
        }

        $chip = Blade::render('<x-rotten-tomatoes-score score="42%" size="chip" />');
        $this->assertStringContainsString('movie-meta-chip movie-meta-chip--rt-rotten', $chip);
        $this->assertStringContainsString('42%', $chip);
        $this->assertStringContainsString('<span class="sr-only">Rotten Tomatoes, Rotten</span>', $chip);
        $this->assertSame('', trim(Blade::render('<x-rotten-tomatoes-score score="N/A" size="chip" />')));
        $this->assertStringContainsString(
            '<x-rotten-tomatoes-score :score="$result->rtrating ?? null" size="chip" />',
            (string) file_get_contents(resource_path('views/movies/partials/movie-card.blade.php')),
        );

        $page = (string) file_get_contents(resource_path('views/movies/viewmoviefull.blade.php'));
        $this->assertStringContainsString('<x-rotten-tomatoes-score :score="$movie[\'rtrating\'] ?? null" />', $page);
        $this->assertStringContainsString('lg:grid-cols-[15rem_minmax(0,1fr)]', $page, 'The poster sits in a narrow fixed column');
        $this->assertStringNotContainsString('lg:grid-cols-3', $page);
    }

    #[Test]
    public function release_details_show_the_rotten_tomatoes_score_for_movies(): void
    {
        $movie = (new MovieInfo)->forceFill(['title' => 'Pixels', 'rating' => '5.6', 'rtrating' => '17%']);

        $html = view('details.partials.movie-info', ['movie' => $movie])->render();
        $this->assertStringContainsString('Rotten Tomatoes, Rotten', $html);
        $this->assertStringContainsString('movie-meta-chip--rt-rotten', $html);
        $this->assertStringContainsString('17%', $html);

        $movie->rtrating = 'N/A';
        $this->assertStringNotContainsString('Rotten Tomatoes', view('details.partials.movie-info', ['movie' => $movie])->render());
    }

    #[Test]
    public function components_that_read_errors_are_never_folded_at_compile_time(): void
    {
        foreach (['field', 'input', 'select', 'textarea', 'form-errors'] as $component) {
            $source = (string) file_get_contents(resource_path("views/components/{$component}.blade.php"));
            $this->assertStringNotContainsString('fold: true', $source, $component);
        }

        foreach (['button' => '<x-button', 'badge' => '<x-badge', 'th' => '<x-th'] as $component => $delegate) {
            $this->assertStringContainsString($delegate, (string) file_get_contents(resource_path("views/components/admin/{$component}.blade.php")));
        }
    }
}
