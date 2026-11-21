<?php

declare(strict_types=1);

namespace App\Providers;

use DateTimeInterface;
use Illuminate\Support\Facades\File;
use Spatie\LaravelTypeScriptTransformer\Commands\TransformTypeScriptCommand;
use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\Transformers\LaravelAttributedClassTransformer;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;
use Symfony\Component\Console\Input\InputOption;

class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    /**
     * Keep `typescript:transform --force` working.
     *
     * Updaters released between 2026-10-14 and 2026-11-20 run that exact
     * command, and `nntmux:all` is already loaded in memory when it pulls a
     * newer checkout, so the old flag reaches this version of the command.
     * Version 3 of the transformer always rewrites its output, which makes the
     * flag a no-op here.
     */
    public function boot(): void
    {
        $this->app->resolving(
            TransformTypeScriptCommand::class,
            static function (TransformTypeScriptCommand $command): void {
                $command->addOption('force', null, InputOption::VALUE_NONE, 'Ignored; accepted for updaters that still pass it');
            },
        );
    }

    /**
     * Export #[TypeScript] DTOs, Laravel Data classes and backed enums to
     * resources/js/types/generated.d.ts.
     *
     * Only the listed directories are scanned: discovery autoloads every class
     * it finds, and optional development integrations elsewhere in app/ depend
     * on packages that are absent from production builds.
     *
     * The output directory holds nothing but the git-ignored generated file,
     * so it is missing from a clean checkout and the transformer refuses to
     * run until it exists.
     */
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $outputDirectory = $this->outputDirectory();

        File::ensureDirectoryExists($outputDirectory);

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
            ->outputDirectory($outputDirectory)
            ->writer(new GlobalNamespaceWriter('generated.d.ts'))
            ->withoutManifest();
    }

    /**
     * Directory that receives generated.d.ts.
     */
    protected function outputDirectory(): string
    {
        return resource_path('js/types');
    }
}
