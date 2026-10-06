<?php

declare(strict_types=1);

namespace Tests\Unit\Monitoring;

use Tests\TestCase;

/**
 * The provisioned dashboard JSON and config/monitoring.php refer to each other
 * by UID and panel id; this keeps the two in sync.
 */
class GrafanaDashboardDefinitionsTest extends TestCase
{
    private const string DATASOURCE_UID = 'nntmux-prometheus';

    public function test_every_configured_dashboard_has_a_provisioned_file_with_that_uid(): void
    {
        foreach (config('monitoring.grafana.dashboards') as $key => $dashboard) {
            $definition = $this->dashboard($dashboard['uid']);

            $this->assertSame($dashboard['uid'], $definition['uid'], "Dashboard [{$key}] uid mismatch.");
            $this->assertNull($definition['id'], "Dashboard [{$key}] must have \"id\": null to provision cleanly.");
            $this->assertFalse($definition['editable'], "Dashboard [{$key}] must not be editable.");
        }
    }

    public function test_embedded_dashboard_panels_exist(): void
    {
        foreach (config('monitoring.grafana.dashboard_panels') as $panel) {
            $uid = config("monitoring.grafana.dashboards.{$panel['dashboard']}.uid");
            $ids = array_column($this->dashboard($uid)['panels'], 'id');

            $this->assertContains($panel['panel'], $ids, "Panel {$panel['panel']} ({$panel['title']}) is missing from {$uid}.");
        }
    }

    public function test_panels_have_unique_ids_and_use_the_provisioned_datasource(): void
    {
        foreach (glob(base_path('docker/monitoring/grafana/dashboards/*.json')) ?: [] as $file) {
            $definition = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $ids = array_column($definition['panels'], 'id');

            $this->assertSame($ids, array_unique($ids), basename($file).' has duplicate panel ids.');

            foreach ($definition['panels'] as $panel) {
                $this->assertSame(self::DATASOURCE_UID, $panel['datasource']['uid'] ?? null, basename($file)." panel {$panel['id']} datasource.");

                foreach ($panel['targets'] as $target) {
                    $this->assertSame(self::DATASOURCE_UID, $target['datasource']['uid'] ?? null, basename($file)." panel {$panel['id']} target datasource.");
                    $this->assertNotSame('', trim($target['expr'] ?? ''), basename($file)." panel {$panel['id']} has an empty query.");
                }
            }
        }
    }

    public function test_datasource_provisioning_uses_the_same_uid(): void
    {
        $provisioning = (string) file_get_contents(base_path('docker/monitoring/grafana/provisioning/datasources/prometheus.yml'));

        $this->assertStringContainsString('uid: '.self::DATASOURCE_UID, $provisioning);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboard(string $uid): array
    {
        $path = base_path("docker/monitoring/grafana/dashboards/{$uid}.json");
        $this->assertFileExists($path);

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
