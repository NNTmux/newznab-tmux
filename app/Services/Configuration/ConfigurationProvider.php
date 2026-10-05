<?php

declare(strict_types=1);

namespace App\Services\Configuration;

use App\Enums\ConfigurationDomain;
use App\Models\IngestionConfiguration;
use App\Models\MetadataConfiguration;
use App\Models\PostProcessingConfiguration;
use App\Models\RegistrationConfiguration;
use App\Models\SiteConfiguration;
use App\Models\TmuxConfiguration;
use App\Support\Configuration\IngestionConfigurationData;
use App\Support\Configuration\MetadataConfigurationData;
use App\Support\Configuration\PostProcessingConfigurationData;
use App\Support\Configuration\RegistrationConfigurationData;
use App\Support\Configuration\SiteConfigurationData;
use App\Support\Configuration\TmuxConfigurationData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class ConfigurationProvider
{
    private const int CACHE_TTL_SECONDS = 300;

    public function site(bool $fresh = false): SiteConfigurationData
    {
        if (! $this->tableExists('site_configurations')) {
            return SiteConfigurationData::defaults();
        }

        return $this->resolve(ConfigurationDomain::Site, $fresh, SiteConfigurationData::class, static fn (): SiteConfigurationData => SiteConfigurationData::fromModel(SiteConfiguration::singleton()));
    }

    public function registration(bool $fresh = false): RegistrationConfigurationData
    {
        if (! $this->tableExists('registration_configurations')) {
            return RegistrationConfigurationData::defaults();
        }

        return $this->resolve(ConfigurationDomain::Registration, $fresh, RegistrationConfigurationData::class, static fn (): RegistrationConfigurationData => RegistrationConfigurationData::fromModel(RegistrationConfiguration::singleton()));
    }

    public function ingestion(bool $fresh = false): IngestionConfigurationData
    {
        if (! $this->tableExists('ingestion_configurations')) {
            return IngestionConfigurationData::defaults();
        }

        return $this->resolve(ConfigurationDomain::Ingestion, $fresh, IngestionConfigurationData::class, static fn (): IngestionConfigurationData => IngestionConfigurationData::fromModel(IngestionConfiguration::singleton()));
    }

    public function postProcessing(bool $fresh = false): PostProcessingConfigurationData
    {
        if (! $this->tableExists('post_processing_configurations')) {
            return PostProcessingConfigurationData::defaults();
        }

        return $this->resolve(ConfigurationDomain::PostProcessing, $fresh, PostProcessingConfigurationData::class, static fn (): PostProcessingConfigurationData => PostProcessingConfigurationData::fromModel(PostProcessingConfiguration::singleton()));
    }

    public function metadata(bool $fresh = false): MetadataConfigurationData
    {
        if (! $this->tableExists('metadata_configurations')) {
            return MetadataConfigurationData::defaults();
        }

        return $this->resolve(ConfigurationDomain::Metadata, $fresh, MetadataConfigurationData::class, static fn (): MetadataConfigurationData => MetadataConfigurationData::fromModel(MetadataConfiguration::singleton()));
    }

    public function tmux(bool $fresh = false): TmuxConfigurationData
    {
        if (! $this->tableExists('tmux_configurations')) {
            return TmuxConfigurationData::defaults();
        }

        return $this->resolve(ConfigurationDomain::Tmux, $fresh, TmuxConfigurationData::class, static fn (): TmuxConfigurationData => TmuxConfigurationData::fromModel(TmuxConfiguration::query()->with(['cleanupRules', 'colorExclusions'])->findOrFail(TmuxConfiguration::SINGLETON_ID)));
    }

    public function forget(ConfigurationDomain $domain): void
    {
        try {
            Cache::forget($this->cacheKey($domain));
        } catch (\Throwable $throwable) {
            if (config('app.debug')) {
                Log::debug('Configuration cache invalidation bypassed: '.$throwable->getMessage());
            }
        }
    }

    /**
     * @template TValue of object
     *
     * @param  class-string<TValue>  $type
     * @param  callable(): TValue  $resolver
     * @return TValue
     */
    private function resolve(ConfigurationDomain $domain, bool $fresh, string $type, callable $resolver): object
    {
        if ($fresh) {
            return $resolver();
        }

        try {
            $cached = Cache::get($this->cacheKey($domain));
            if ($cached instanceof $type) {
                return $cached;
            }

            $value = $resolver();
            Cache::put($this->cacheKey($domain), $value, self::CACHE_TTL_SECONDS);

            return $value;
        } catch (\Throwable $throwable) {
            if (config('app.debug')) {
                Log::debug('Configuration cache bypassed: '.$throwable->getMessage());
            }

            return $resolver();
        }
    }

    private function cacheKey(ConfigurationDomain $domain): string
    {
        return 'configuration:'.$domain->value;
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
