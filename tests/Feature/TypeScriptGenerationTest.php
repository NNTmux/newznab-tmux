<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;
use Tests\TestCase;

class TypeScriptGenerationTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_generation_exports_application_types_without_loading_development_mcp_tools(): void
    {
        $outputDirectory = sys_get_temp_dir().'/nntmux-types-'.bin2hex(random_bytes(8));
        $this->app->extend(
            TypeScriptTransformerConfig::class,
            fn (TypeScriptTransformerConfig $config): TypeScriptTransformerConfig => new TypeScriptTransformerConfig(
                ...[...get_object_vars($config), 'outputDirectory' => $outputDirectory],
            ),
        );

        try {
            $this->assertFalse(class_exists('Laravel\\Mcp\\Server\\Tool', false));
            $this->artisan('typescript:transform')->assertSuccessful();
            $this->assertFalse(class_exists('Laravel\\Mcp\\Server\\Tool', false));
            $this->assertFileDoesNotExist($outputDirectory.'/typescript-transformer-manifest.json');
            $definitions = file_get_contents($outputDirectory.'/generated.d.ts');
            foreach (['ReleaseData', 'CategoryData', 'DetailsData', 'ReleaseCreationResult', 'NameFixResult', 'ProcessingOutcome', 'UserRole'] as $type) {
                $this->assertStringContainsString('type '.$type.' =', $definitions);
            }
        } finally {
            File::deleteDirectory($outputDirectory);
        }
    }
}
