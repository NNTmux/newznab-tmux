<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationSchemaDumpTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function schemaDumps(): array
    {
        return [
            'MariaDB' => ['mariadb-schema.sql'],
            'MySQL' => ['mysql-schema.sql'],
        ];
    }

    #[DataProvider('schemaDumps')]
    public function test_typed_configuration_schema_is_present_without_the_legacy_table(string $filename): void
    {
        $schema = (string) file_get_contents(__DIR__.'/../../../database/schema/'.$filename);

        $this->assertStringNotContainsString('CREATE TABLE `settings`', $schema);
        $this->assertStringContainsString('CREATE TABLE `site_configurations`', $schema);
        $this->assertStringContainsString('`trailers_display` tinyint(1)', $schema);
        $this->assertStringContainsString('`max_messages` bigint unsigned', $schema);
        $this->assertStringContainsString('`safe_backfill_date` date', $schema);
        $this->assertStringContainsString('`terms` longtext', $schema);
        $this->assertStringContainsString('CREATE TABLE `processing_runtime_states`', $schema);
        $this->assertStringContainsString('CREATE TABLE `tmux_cleanup_rules`', $schema);
        $this->assertStringContainsString('FOREIGN KEY (`tmux_configuration_id`) REFERENCES `tmux_configurations`', $schema);
    }
}
