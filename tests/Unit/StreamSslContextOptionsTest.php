<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class StreamSslContextOptionsTest extends TestCase
{
    public function test_system_ca_store_is_used_with_secure_defaults(): void
    {
        config()->set('nntmux_ssl', [
            'ssl_cafile' => '',
            'ssl_capath' => '',
            'ssl_verify_peer' => true,
            'ssl_verify_host' => true,
            'ssl_allow_self_signed' => false,
        ]);

        $options = streamSslContextOptions();

        foreach (['tls', 'ssl'] as $transport) {
            $this->assertTrue($options[$transport]['verify_peer']);
            $this->assertTrue($options[$transport]['verify_peer_name']);
            $this->assertFalse($options[$transport]['allow_self_signed']);
            $this->assertArrayNotHasKey('cafile', $options[$transport]);
            $this->assertArrayNotHasKey('capath', $options[$transport]);
        }
    }

    public function test_custom_ca_paths_are_forwarded_without_disabling_verification(): void
    {
        config()->set('nntmux_ssl', [
            'ssl_cafile' => '/etc/ssl/custom-ca.pem',
            'ssl_capath' => '/etc/ssl/custom-certs',
            'ssl_verify_peer' => true,
            'ssl_verify_host' => true,
            'ssl_allow_self_signed' => false,
        ]);

        $options = streamSslContextOptions();

        foreach (['tls', 'ssl'] as $transport) {
            $this->assertSame('/etc/ssl/custom-ca.pem', $options[$transport]['cafile']);
            $this->assertSame('/etc/ssl/custom-certs', $options[$transport]['capath']);
            $this->assertTrue($options[$transport]['verify_peer']);
            $this->assertTrue($options[$transport]['verify_peer_name']);
        }
    }
}
