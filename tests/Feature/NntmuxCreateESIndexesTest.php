<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\NntmuxCreateESIndexes;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class NntmuxCreateESIndexesTest extends TestCase
{
    public function test_create_missing_preserves_all_existing_indexes(): void
    {
        $history = [];
        $this->bindClient(array_fill(0, 10, $this->response(200)), $history);

        $this->assertSame(0, $this->runCommand(true));
        $this->assertCount(10, $history);
        foreach ($history as $request) {
            $this->assertSame('HEAD', $request['request']->getMethod());
        }
    }

    public function test_create_missing_creates_an_absent_index_without_deleting_any_index(): void
    {
        $history = [];
        $this->bindClient([$this->response(404), $this->response(200), ...array_fill(0, 9, $this->response(200))], $history);

        $this->assertSame(0, $this->runCommand(true));
        $methods = array_map(fn (array $item): string => $item['request']->getMethod(), $history);
        $this->assertContains('PUT', $methods);
        $this->assertNotContains('DELETE', $methods);
    }

    public function test_default_mode_retains_index_recreation_behavior(): void
    {
        $history = [];
        $this->bindClient(array_fill(0, 30, $this->response(200)), $history);

        $this->assertSame(0, $this->runCommand(false));
        $methods = array_map(fn (array $item): string => $item['request']->getMethod(), $history);
        $this->assertSame(10, count(array_filter($methods, fn (string $method): bool => $method === 'DELETE')));
    }

    public function test_failed_creation_returns_failure_and_never_deletes_existing_indexes(): void
    {
        $history = [];
        $this->bindClient([$this->response(404), $this->response(500)], $history);

        $this->assertSame(1, $this->runCommand(true));
        $this->assertCount(2, $history);
        $this->assertSame('PUT', $history[1]['request']->getMethod());
    }

    private function response(int $status): Response
    {
        return new Response($status, ['X-Elastic-Product' => 'Elasticsearch'], '{"acknowledged":true}');
    }

    /**
     * @param  list<Response>  $responses
     * @param  list<array<string, mixed>>  $history
     */
    private function bindClient(array $responses, array &$history): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::tap(function (RequestInterface $request) use (&$history): void {
            $history[] = ['request' => $request];
        }));
        $client = ClientBuilder::create()->setHosts(['http://127.0.0.1:9200'])->setRetries(0)
            ->setHttpClient(new Client(['handler' => $stack]))->build();
        $this->app->instance('elasticsearch', $client);
    }

    private function runCommand(bool $createMissing): int
    {
        $command = new NntmuxCreateESIndexes;
        $command->setLaravel($this->app);

        return $command->run(new ArrayInput($createMissing ? ['--create-missing' => true] : []), new BufferedOutput);
    }
}
