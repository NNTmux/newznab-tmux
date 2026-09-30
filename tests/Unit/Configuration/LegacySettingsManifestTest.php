<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Database\Support\LegacySettingsManifest;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../../database/support/LegacySettingsManifest.php';

final class LegacySettingsManifestTest extends TestCase
{
    public function test_every_legacy_seeded_key_is_classified_exactly_once(): void
    {
        $classified = LegacySettingsManifest::classifiedKeys();
        $missing = array_values(array_diff(LegacySettingsManifest::LEGACY_SEEDED_KEYS, $classified));

        $this->assertSame([], $missing, 'Unclassified legacy keys: '.implode(', ', $missing));
        $this->assertCount(count(array_unique($classified)), $classified);
    }

    public function test_active_unseeded_keys_and_aliases_are_classified(): void
    {
        $classified = LegacySettingsManifest::classifiedKeys();

        foreach (['amazonpubkey', 'amazonprivkey', 'amazonassociatetag', 'amazonsleep', 'extractusingrarinfo', 'imdburl', 'imdblanguage', 'lookuplanguage', 'lookuppar2', 'write_logs', 'deletepasswordedrelease'] as $key) {
            $this->assertContains($key, $classified);
        }
    }

    public function test_runtime_and_retired_keys_are_not_domain_columns(): void
    {
        $domainKeys = [];
        foreach (LegacySettingsManifest::DOMAINS as $definitions) {
            foreach ($definitions as $definition) {
                array_push($domainKeys, ...$definition['keys']);
            }
        }

        $this->assertSame([], array_values(array_intersect(array_keys(LegacySettingsManifest::RUNTIME), $domainKeys)));
        $this->assertSame([], array_values(array_intersect(LegacySettingsManifest::RETIRED, $domainKeys)));
    }
}
