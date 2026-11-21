<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\TypeScriptTransformerServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class TypeScriptGenerationTest extends TestCase
{
    private string $outputDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputDirectory = sys_get_temp_dir().'/nntmux-types-'.bin2hex(random_bytes(8));
        $this->app->register($this->providerWritingTo($this->outputDirectory), force: true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->outputDirectory);

        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_generation_exports_application_types_without_loading_development_mcp_tools(): void
    {
        $this->assertFalse(class_exists('Laravel\\Mcp\\Server\\Tool', false));
        $this->artisan('typescript:transform')->assertSuccessful();
        $this->assertFalse(class_exists('Laravel\\Mcp\\Server\\Tool', false));
        $this->assertFileDoesNotExist($this->outputDirectory.'/typescript-transformer-manifest.json');
        $definitions = file_get_contents($this->outputDirectory.'/generated.d.ts');
        foreach (['ReleaseData', 'CategoryData', 'DetailsData', 'ReleaseCreationResult', 'NameFixResult', 'ProcessingOutcome', 'UserRole'] as $type) {
            $this->assertStringContainsString('type '.$type.' =', $definitions);
        }
    }

    public function test_generation_creates_the_output_directory_on_a_clean_checkout(): void
    {
        $this->assertDirectoryDoesNotExist($this->outputDirectory);

        $this->artisan('typescript:transform')->assertSuccessful();

        $this->assertFileExists($this->outputDirectory.'/generated.d.ts');
    }

    public function test_generation_accepts_the_force_flag_passed_by_older_updaters(): void
    {
        $this->artisan('typescript:transform', ['--force' => true])->assertSuccessful();

        $this->assertFileExists($this->outputDirectory.'/generated.d.ts');
    }

    /**
     * Build the application's provider with its output redirected away from resources/.
     */
    private function providerWritingTo(string $outputDirectory): TypeScriptTransformerServiceProvider
    {
        return new class($this->app, $outputDirectory) extends TypeScriptTransformerServiceProvider
        {
            public function __construct(Application $app, private readonly string $redirectedOutputDirectory)
            {
                parent::__construct($app);
            }

            protected function outputDirectory(): string
            {
                return $this->redirectedOutputDirectory;
            }
        };
    }
}
