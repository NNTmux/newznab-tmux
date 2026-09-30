<?php

declare(strict_types=1);

namespace App\Services\Configuration;

use App\Enums\ConfigurationDomain;
use App\Models\IngestionConfiguration;
use App\Models\MetadataConfiguration;
use App\Models\PostProcessingConfiguration;
use App\Models\RegistrationConfiguration;
use App\Models\SiteConfiguration;
use App\Models\TmuxCleanupRule;
use App\Models\TmuxColorExclusion;
use App\Models\TmuxConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final readonly class DomainConfigurationRepository
{
    public function __construct(private ConfigurationProvider $provider) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $cleanupRules
     * @param  list<int>|null  $colorExclusions
     */
    public function update(ConfigurationDomain $domain, array $attributes, ?array $cleanupRules = null, ?array $colorExclusions = null): void
    {
        $modelClass = $this->modelClass($domain);
        $model = new $modelClass;

        if (! Schema::hasTable($model->getTable())) {
            throw new RuntimeException("The {$domain->label()} configuration table is not available.");
        }

        DB::transaction(function () use ($domain, $modelClass, $attributes, $cleanupRules, $colorExclusions): void {
            /** @var Model $configuration */
            $configuration = $modelClass::query()->lockForUpdate()->findOrFail(1);
            $configuration->fill($attributes);
            $configuration->save();

            if ($domain === ConfigurationDomain::Tmux && $cleanupRules !== null) {
                TmuxCleanupRule::query()->where('tmux_configuration_id', 1)->delete();
                TmuxCleanupRule::query()->insert(array_map(
                    static fn (string $rule): array => ['tmux_configuration_id' => 1, 'rule' => $rule],
                    array_values(array_unique($cleanupRules)),
                ));
            }

            if ($domain === ConfigurationDomain::Tmux && $colorExclusions !== null) {
                TmuxColorExclusion::query()->where('tmux_configuration_id', 1)->delete();
                TmuxColorExclusion::query()->insert(array_map(
                    static fn (int $color): array => ['tmux_configuration_id' => 1, 'color' => $color],
                    array_values(array_unique($colorExclusions)),
                ));
            }
        });

        $this->provider->forget($domain);
    }

    /** @return class-string<Model> */
    private function modelClass(ConfigurationDomain $domain): string
    {
        return match ($domain) {
            ConfigurationDomain::Site => SiteConfiguration::class,
            ConfigurationDomain::Registration => RegistrationConfiguration::class,
            ConfigurationDomain::Ingestion => IngestionConfiguration::class,
            ConfigurationDomain::PostProcessing => PostProcessingConfiguration::class,
            ConfigurationDomain::Metadata => MetadataConfiguration::class,
            ConfigurationDomain::Tmux => TmuxConfiguration::class,
        };
    }
}
