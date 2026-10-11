<?php

namespace Tests\Unit\Services;

use App\Services\NNTP\NNTPService;
use Tests\TestCase;

final class NNTPServiceConnectFailureTest extends TestCase
{
    /**
     * A provider that accepts the TCP connection but never completes the TLS handshake used to
     * surface as an uncaught ErrorException ("SSL: Handshake timed out") that killed the worker.
     */
    public function test_a_tls_handshake_timeout_is_returned_as_an_nntp_error(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorString);
        $this->assertNotFalse($server, $errorString);
        $port = (int) substr(strrchr((string) stream_socket_get_name($server, false), ':'), 1);

        try {
            $result = (new NNTPService)->connect('127.0.0.1', 'ssl', $port, 1);
        } finally {
            fclose($server);
        }

        $this->assertTrue(NNTPService::isError($result));
        $this->assertStringContainsString('Connection to ssl://127.0.0.1:'.$port.' failed.', $result->getMessage());
    }
}
