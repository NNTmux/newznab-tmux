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
        return $this->resolve(ConfigurationDomain::Site, $fresh, SiteConfigurationData::class, static fn (): SiteConfigurationData => SiteConfigurationData::defaults(), static fn (): SiteConfigurationData => SiteConfigurationData::fromModel(SiteConfiguration::singleton()));
    }

    public function registration(bool $fresh = false): RegistrationConfigurationData
    {
        return $this->resolve(ConfigurationDomain::Registration, $fresh, RegistrationConfigurationData::class, static fn (): RegistrationConfigurationData => RegistrationConfigurationData::defaults(), static fn (): RegistrationConfigurationData => RegistrationConfigurationData::fromModel(RegistrationConfiguration::singleton()));
    }

    public function ingestion(bool $fresh = false): IngestionConfigurationData
    {
        return $this->resolve(ConfigurationDomain::Ingestion, $fresh, IngestionConfigurationData::class, static fn (): IngestionConfigurationData => IngestionConfigurationData::defaults(), static fn (): IngestionConfigurationData => IngestionConfigurationData::fromModel(IngestionConfiguration::singleton()));
    }

    public function postProcessing(bool $fresh = false): PostProcessingConfigurationData
    {
        return $this->resolve(ConfigurationDomain::PostProcessing, $fresh, PostProcessingConfigurationData::class, static fn (): PostProcessingConfigurationData => PostProcessingConfigurationData::defaults(), static fn (): PostProcessingConfigurationData => PostProcessingConfigurationData::fromModel(PostProcessingConfiguration::singleton()));
    }

    public function metadata(bool $fresh = false): MetadataConfigurationData
    {
        return $this->resolve(ConfigurationDomain::Metadata, $fresh, MetadataConfigurationData::class, static fn (): MetadataConfigurationData => MetadataConfigurationData::defaults(), static fn (): MetadataConfigurationData => MetadataConfigurationData::fromModel(MetadataConfiguration::singleton()));
    }

    public function tmux(bool $fresh = false): TmuxConfigurationData
    {
        return $this->resolve(ConfigurationDomain::Tmux, $fresh, TmuxConfigurationData::class, static fn (): TmuxConfigurationData => TmuxConfigurationData::defaults(), static fn (): TmuxConfigurationData => TmuxConfigurationData::fromModel(TmuxConfiguration::query()->with(['cleanupRules', 'colorExclusions'])->findOrFail(TmuxConfiguration::SINGLETON_ID)));
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
     * Serve the cached value when there is one; the table check (an information_schema
     * query) only runs when the configuration has to be read from the database.
     *
     * @template TValue of object
     *
     * @param  class-string<TValue>  $type
     * @param  callable(): TValue  $defaults
     * @param  callable(): TValue  $resolver
     * @return TValue
     */
    private function resolve(ConfigurationDomain $domain, bool $fresh, string $type, callable $defaults, callable $resolver): object
    {
        if (! $fresh) {
            try {
                $cached = Cache::get($this->cacheKey($domain));
                if ($cached instanceof $type) {
                    return $cached;
                }
            } catch (\Throwable $throwable) {
                $this->logCacheBypass($throwable);
            }
        }

        if (! $this->tableExists($domain->table())) {
            return $defaults();
        }

        $value = $resolver();

        if (! $fresh) {
            try {
                Cache::put($this->cacheKey($domain), $value, self::CACHE_TTL_SECONDS);
            } catch (\Throwable $throwable) {
                $this->logCacheBypass($throwable);
            }
        }

        return $value;
    }

    private function logCacheBypass(\Throwable $throwable): void
    {
        if (config('app.debug')) {
            Log::debug('Configuration cache bypassed: '.$throwable->getMessage());
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
