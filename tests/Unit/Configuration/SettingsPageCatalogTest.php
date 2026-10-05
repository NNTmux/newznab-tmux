<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use App\Enums\ConfigurationDomain;
use App\Support\Configuration\SettingsPageCatalog;
use Database\Support\LegacySettingsManifest;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../../database/support/LegacySettingsManifest.php';

final class SettingsPageCatalogTest extends TestCase
{
    public function test_every_editable_domain_column_has_exactly_one_complete_descriptor(): void
    {
        $catalog = new SettingsPageCatalog;

        foreach (ConfigurationDomain::cases() as $domain) {
            $fields = $catalog->fields($domain);
            $columns = array_map(static fn ($field): string => $field->column, $fields);
            $table = $domain->value === 'post-processing' ? 'post_processing_configurations' : str_replace('-', '_', $domain->value).'_configurations';
            $expected = array_keys(LegacySettingsManifest::DOMAINS[$table]);
            if ($domain === ConfigurationDomain::Tmux) {
                array_push($expected, 'cleanup_rules', 'color_exclusions');
            }

            sort($columns);
            sort($expected);
            $this->assertSame($expected, $columns, "Catalog mismatch for {$domain->value}");
            $this->assertCount(count(array_unique($columns)), $columns);

            foreach ($fields as $field) {
                $this->assertNotSame('', $field->label);
                $this->assertNotSame('', $field->help);
                $this->assertNotSame([], $field->rules);
                $this->assertNotSame('', $field->guidance);
            }
        }
    }

    public function test_runtime_and_retired_fields_cannot_appear_in_forms(): void
    {
        $catalog = new SettingsPageCatalog;
        $columns = [];
        foreach (ConfigurationDomain::cases() as $domain) {
            array_push($columns, ...$catalog->writableColumns($domain));
        }

        $this->assertSame([], array_values(array_intersect($columns, array_column(LegacySettingsManifest::RUNTIME, 'column'))));
        $this->assertSame([], array_values(array_intersect($columns, LegacySettingsManifest::RETIRED)));
    }

    public function test_post_processing_page_includes_movie_controls_from_metadata(): void
    {
        $catalog = new SettingsPageCatalog;
        $fields = $catalog->fieldsForPage(ConfigurationDomain::PostProcessing);
        $columns = array_map(static fn ($field): string => $field->column, $fields);

        $this->assertCount(count(array_unique($columns)), $columns);
        $this->assertContains('movie_lookup', $columns);
        $this->assertContains('max_movies_processed', $columns);
        $this->assertNotContains('movie_lookup', $catalog->writableColumns(ConfigurationDomain::PostProcessing));
        $this->assertSame('TV, Anime and Movie Panes', $fields[0]->label);
        $this->assertSame([0 => 'Disabled', 1 => 'Enabled'], $fields[0]->options);
        $this->assertSame('Process Movies', $fields[1]->label);
        $this->assertSame([0 => 'Disabled', 1 => 'All releases', 2 => 'Renamed releases only'], $fields[1]->options);
    }
}
