<?php

namespace Tests\Unit\Services;

use App\Services\NNTP\NNTPService;
use App\Services\Tmux\Tmux;
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

    public function test_every_retry_after_a_rejected_login_pauses_instead_of_spending_them_back_to_back(): void
    {
        $service = $this->retryProbe(connectSucceeds: true);

        $result = $service->doConnect();

        $this->assertTrue(NNTPService::isError($result));
        $this->assertSame(4, $service->loginAttempts);
        $this->assertSame([1, 2, 3], $service->pauses);
    }

    public function test_every_retry_after_a_failed_connect_pauses_too(): void
    {
        $service = $this->retryProbe(connectSucceeds: false);

        $result = $service->doConnect();

        $this->assertTrue(NNTPService::isError($result));
        $this->assertSame(4, $service->connectAttempts);
        $this->assertSame([1, 2, 3], $service->pauses);
    }

    private function retryProbe(bool $connectSucceeds): NNTPService
    {
        $tmux = $this->getMockBuilder(Tmux::class)->disableOriginalConstructor()->onlyMethods(['getUSPConnections'])->getMock();
        $tmux->method('getUSPConnections')->willReturn(['primary' => ['active' => 0], 'alternate' => ['active' => 0]]);

        $service = new class($tmux, $connectSucceeds) extends NNTPService
        {
            public int $connectAttempts = 0;

            public int $loginAttempts = 0;

            /** @var list<int> */
            public array $pauses = [];

            public function __construct(Tmux $tmux, private bool $connectSucceeds)
            {
                parent::__construct($tmux);
            }

            public function connect(?string $host = null, mixed $encryption = null, ?int $port = null, ?int $timeout = 15, int $socketTimeout = 120): mixed
            {
                $this->connectAttempts++;

                return $this->connectSucceeds ? true : $this->throwError('Connection refused');
            }

            public function authenticate(?string $user = null, ?string $pass = null, bool $encryption = false): mixed
            {
                $this->loginAttempts++;

                return $this->throwError('Authentication rejected');
            }

            protected function pauseBeforeRetry(int $attempt): void
            {
                $this->pauses[] = $attempt;
            }
        };

        foreach (['_nntpRetries' => 4, '_configUsername' => 'user', '_configPassword' => 'secret', '_configServer' => 'news.example.test'] as $property => $value) {
            (new \ReflectionProperty(NNTPService::class, $property))->setValue($service, $value);
        }

        return $service;
    }
}
