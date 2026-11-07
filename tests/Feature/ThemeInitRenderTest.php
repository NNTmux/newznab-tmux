<?php

namespace Tests\Feature;

use App\Enums\ColorScheme;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThemeInitRenderTest extends TestCase
{
    private string $databasePath;

    public function createApplication(): Application
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'theme-init-');
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
    public function guests_get_defaults_and_a_first_paint_rule_for_every_scheme(): void
    {
        $html = view('partials.theme-init')->render();

        $this->assertStringContainsString('var t = "light";', $html);
        $this->assertStringContainsString('var scheme = "blue";', $html);
        $this->assertStringContainsString('var allowedSchemes = '.json_encode(ColorScheme::values()).';', $html);
        $this->assertStringContainsString('html, body { background-color: #f8fafc; color: #1e293b; }', $html);

        foreach (ColorScheme::cases() as $scheme) {
            if ($scheme === ColorScheme::default()) {
                continue;
            }

            $this->assertStringContainsString(
                "html.dark[data-color-scheme=\"{$scheme->value}\"], html.dark[data-color-scheme=\"{$scheme->value}\"] body { background-color: {$scheme->canvas(true)};",
                $html,
            );
        }
    }

    #[Test]
    public function signed_in_users_get_their_saved_theme_and_unknown_schemes_fall_back(): void
    {
        $user = User::factory()->make(['theme_preference' => 'dark', 'color_scheme' => 'amber']);
        $this->actingAs($user);

        $html = view('partials.theme-init')->render();
        $this->assertStringContainsString('var t = "dark";', $html);
        $this->assertStringContainsString('var scheme = "amber";', $html);

        $user->color_scheme = '</script><script>alert(1)</script>';
        $html = view('partials.theme-init')->render();
        $this->assertStringContainsString('var scheme = "blue";', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    #[Test]
    public function static_mode_ignores_the_signed_in_user_and_reads_browser_storage(): void
    {
        $this->actingAs(User::factory()->make(['theme_preference' => 'dark', 'color_scheme' => 'amber']));

        $html = view('partials.theme-init', ['static' => true])->render();

        $this->assertStringContainsString('var t = "light";', $html);
        $this->assertStringContainsString("localStorage.getItem('color_scheme')", $html);
        $this->assertStringContainsString('var scheme = "blue";', $html);
        $this->assertStringNotContainsString('var t = "dark";', $html);
    }

    #[Test]
    public function theme_meta_marks_signed_in_users_so_admin_pages_keep_their_scheme(): void
    {
        $guest = view('partials.theme-meta')->render();
        $this->assertStringContainsString('<meta name="color-scheme-preference" content="blue">', $guest);
        $this->assertStringNotContainsString('user-authenticated', $guest);

        $this->actingAs(User::factory()->make());
        $member = view('partials.theme-meta', ['userTheme' => 'system', 'userColorScheme' => 'violet'])->render();

        $this->assertStringContainsString('<meta name="theme-preference" content="system">', $member);
        $this->assertStringContainsString('<meta name="color-scheme-preference" content="violet">', $member);
        $this->assertStringContainsString('<meta name="user-authenticated" content="true">', $member);
        $this->assertStringContainsString('name="update-theme-url"', $member);
    }
}
