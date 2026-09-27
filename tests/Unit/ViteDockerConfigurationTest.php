<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ViteDockerConfigurationTest extends TestCase
{
    public function test_vite_uses_the_docker_published_port_without_fallback(): void
    {
        $viteConfig = $this->projectFile('vite.config.js');
        $composeConfig = $this->projectFile('docker-compose.yml');

        $this->assertStringContainsString("loadEnv(mode, process.cwd(), '')", $viteConfig);
        $this->assertStringContainsString("environment.VITE_PORT || '5173'", $viteConfig);
        $this->assertStringContainsString("environment.VITE_DEV_SERVER_HOST || 'localhost'", $viteConfig);
        $this->assertStringContainsString("host: '0.0.0.0'", $viteConfig);
        $this->assertStringContainsString('strictPort: true', $viteConfig);
        $this->assertStringContainsString('origin: `http://${viteDevServerHost}:${vitePort}`', $viteConfig);
        $this->assertStringContainsString("'\${VITE_PORT:-5173}:\${VITE_PORT:-5173}'", $composeConfig);
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

    private function projectFile(string $path): string
    {
        return (string) file_get_contents(__DIR__."/../../{$path}");
    }
}
