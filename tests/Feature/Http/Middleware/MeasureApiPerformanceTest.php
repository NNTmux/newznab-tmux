<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\MeasureApiPerformance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class MeasureApiPerformanceTest extends TestCase
{
    public function test_a_sampled_api_request_reports_its_timings_in_a_header_and_logs_nothing(): void
    {
        config(['nntmux.api.metrics_sample_rate' => 1.0]);
        Log::spy();
        $request = Request::create('/api/v1/api', 'GET');

        $response = (new MeasureApiPerformance)->handle($request, static function (Request $request): Response {
            $request->attributes->set('nntmux.api_release_cache', 'hit');

            return new Response('OK');
        });

        $this->assertMatchesRegularExpression(
            '/^app;dur=\d+\.\d{2}, db;dur=\d+\.\d{2};desc="\d+ queries", cache;desc="hit"$/',
            (string) $response->headers->get('Server-Timing'),
        );
        Log::shouldNotHaveReceived('channel');
        Log::shouldNotHaveReceived('info');
    }

    #[DataProvider('unmeasuredRequests')]
    public function test_unsampled_and_non_api_requests_are_not_measured(string $path, float $sampleRate): void
    {
        config(['nntmux.api.metrics_sample_rate' => $sampleRate]);

        $response = (new MeasureApiPerformance)->handle(
            Request::create($path, 'GET'),
            static fn (): Response => new Response('OK'),
        );

        $this->assertFalse($response->headers->has('Server-Timing'));
    }

    /**
     * @return array<string, array{string, float}>
     */
    public static function unmeasuredRequests(): array
    {
        return [
            'sampling disabled' => ['/api/v1/api', 0.0],
            'not an api path' => ['/browse/All', 1.0],
        ];
    }
}
