<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class ViteDockerConfigurationTest extends TestCase
{
    public function test_production_image_sets_a_utf8_locale(): void
    {
        $dockerfile = $this->projectFile('Dockerfile');

        $this->assertSame(1, preg_match('/^FROM[^\r\n]+ AS production\R(.*)\z/sm', $dockerfile, $matches));
        $this->assertMatchesRegularExpression('/^ENV LANG=C\.UTF-8$/m', $matches[1]);
    }

    public function test_compose_uses_latest_tags_for_services_that_publish_them(): void
    {
        $this->assertServicesUseLatestTags('docker-compose.yml.prod-dist');
    }

    /**
     * docker-compose.yml is the developer's local Sail file and is gitignored, so it only exists outside CI.
     */
    public function test_local_sail_compose_file_matches_the_expected_configuration(): void
    {
        if (! file_exists(__DIR__.'/../../docker-compose.yml')) {
            $this->markTestSkipped('docker-compose.yml is a gitignored local Sail file.');
        }

        $this->assertServicesUseLatestTags('docker-compose.yml');

        $composeConfig = $this->projectFile('docker-compose.yml');
        $localServices = Yaml::parse($composeConfig)['services'];
        $this->assertSame(
            ['CMD', 'mariadb-admin', 'ping', '-p${DB_PASSWORD}'],
            $localServices['mariadb']['healthcheck']['test'],
        );
        $this->assertStringContainsString("'\${VITE_PORT:-5173}:\${VITE_PORT:-5173}'", $composeConfig);
    }

    public function test_docker_build_targets_always_pull_images_before_building(): void
    {
        foreach (['build', 'rebuild', 'update', 'fresh'] as $target) {
            $process = new Process([
                'make', '--dry-run', '--no-print-directory', $target,
                'SAIL=mock-sail', 'DOCKER_COMPOSE=mock-compose',
            ], __DIR__.'/../..');
            $process->mustRun();

            $commands = $process->getOutput();
            $pullPosition = strpos($commands, 'mock-compose pull --ignore-buildable --policy always');
            $buildCommand = in_array($target, ['rebuild', 'fresh'], true)
                ? 'mock-sail build --no-cache --pull'
                : 'mock-sail build --pull';
            $buildPosition = strpos($commands, $buildCommand);

            $this->assertNotFalse($pullPosition, "{$target} must refresh service images.");
            $this->assertNotFalse($buildPosition, "{$target} must refresh build base images.");
            $this->assertLessThan($buildPosition, $pullPosition, "{$target} must pull before building.");
        }
    }

    public function test_vite_uses_the_docker_published_port_without_fallback(): void
    {
        $viteConfig = $this->projectFile('vite.config.js');

        $this->assertStringContainsString("loadEnv(mode, process.cwd(), '')", $viteConfig);
        $this->assertStringContainsString("environment.VITE_PORT || '5173'", $viteConfig);
        $this->assertStringContainsString("environment.VITE_DEV_SERVER_HOST || 'localhost'", $viteConfig);
        $this->assertStringContainsString("host: '0.0.0.0'", $viteConfig);
        $this->assertStringContainsString('strictPort: true', $viteConfig);
        $this->assertStringContainsString('origin: `http://${viteDevServerHost}:${vitePort}`', $viteConfig);
        $this->assertStringContainsString('VITE_DEV_SERVER_HOST=localhost', $this->projectFile('.env.example'));
    }

    public function test_container_startup_clears_stale_vite_hot_state(): void
    {
        $startContainer = $this->projectFile('docker/8.5/start-container');

        $unlinkPosition = strpos($startContainer, 'unlink /var/www/html/public/hot');
        $supervisorPosition = strpos($startContainer, 'exec /usr/bin/supervisord');

        $this->assertNotFalse($unlinkPosition);
        $this->assertNotFalse($supervisorPosition);
        $this->assertLessThan($supervisorPosition, $unlinkPosition);
    }

    public function test_container_startup_aligns_runtime_directory_ownership_with_the_host_user(): void
    {
        $startContainer = $this->projectFile('docker/8.5/start-container');

        $this->assertStringContainsString('usermod -u "$WWWUSER" -o sail', $startContainer);
        $this->assertStringContainsString('storage/framework storage/logs bootstrap/cache', $startContainer);
        $this->assertStringContainsString('chown -R sail:"${SAIL_GROUP}"', $startContainer);
        $this->assertStringContainsString('chmod -R ug+rwX', $startContainer);

        $permissionRepairPosition = strpos($startContainer, 'chown -R sail:"${SAIL_GROUP}"');
        $supervisorPosition = strpos($startContainer, 'exec /usr/bin/supervisord');

        $this->assertNotFalse($permissionRepairPosition);
        $this->assertNotFalse($supervisorPosition);
        $this->assertLessThan($supervisorPosition, $permissionRepairPosition);
    }

    public function test_nginx_serves_laravel_public_storage_without_a_host_symlink(): void
    {
        $nginxConfig = $this->projectFile('docker/8.5/nginx.conf');

        $this->assertStringContainsString('location ^~ /storage/', $nginxConfig);
        $this->assertStringContainsString('alias /var/www/html/storage/app/public/;', $nginxConfig);
    }

    public function test_cursor_registers_nntmux_mcp_through_sail_alongside_boost(): void
    {
        $configuration = json_decode(
            $this->projectFile('.cursor/mcp.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame('./vendor/bin/sail', $configuration['mcpServers']['laravel-boost']['command']);
        $this->assertSame(['artisan', 'boost:mcp'], $configuration['mcpServers']['laravel-boost']['args']);
        $this->assertSame('./vendor/bin/sail', $configuration['mcpServers']['newznab-tmux']['command']);
        $this->assertSame(
            ['artisan', 'mcp:start', 'newznab-tmux'],
            $configuration['mcpServers']['newznab-tmux']['args'],
        );
    }

    private function assertServicesUseLatestTags(string $path): void
    {
        $services = Yaml::parse($this->projectFile($path))['services'];

        $this->assertSame('mariadb:latest', $services['mariadb']['image'], $path);
        $this->assertSame('redis:latest', $services['redis']['image'], $path);
        $this->assertSame('manticoresearch/manticore:latest', $services['manticore']['image'], $path);
    }

    private function projectFile(string $path): string
    {
        return (string) file_get_contents(__DIR__."/../../{$path}");
    }
}
