<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class TypeScriptGenerationTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_generation_exports_application_types_without_loading_development_mcp_tools(): void
    {
        $output = tempnam(sys_get_temp_dir(), 'nntmux-types-');
        config(['typescript-transformer.output_file' => $output]);

        try {
            $this->assertFalse(class_exists('Laravel\\Mcp\\Server\\Tool', false));
            $this->artisan('typescript:transform')->assertSuccessful();
            $this->assertFalse(class_exists('Laravel\\Mcp\\Server\\Tool', false));
            $definitions = file_get_contents($output);
            foreach (['ReleaseData', 'CategoryData', 'DetailsData', 'ReleaseCreationResult', 'NameFixResult', 'ProcessingOutcome', 'UserRole'] as $type) {
                $this->assertStringContainsString('type '.$type.' =', $definitions);
            }
        } finally {
            unlink($output);
        }
    }
}
