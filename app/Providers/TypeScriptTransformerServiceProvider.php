<?php

declare(strict_types=1);

namespace App\Providers;

use DateTimeInterface;
use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\Transformers\LaravelAttributedClassTransformer;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    /**
     * Export #[TypeScript] DTOs, Laravel Data classes and backed enums to
     * resources/js/types/generated.d.ts.
     *
     * Only the listed directories are scanned: discovery autoloads every class
     * it finds, and optional development integrations elsewhere in app/ depend
     * on packages that are absent from production builds.
     */
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->transformer(LaravelAttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformDirectories(
                app_path('Data'),
                app_path('Enums'),
                app_path('Support/Data'),
                app_path('Services/AdditionalProcessing/Enums'),
                app_path('Services/NameFixing/Data'),
            )
            ->replaceType(DateTimeInterface::class, 'string')
            ->outputDirectory(resource_path('js/types'))
            ->writer(new GlobalNamespaceWriter('generated.d.ts'))
            ->withoutManifest();
    }
}
