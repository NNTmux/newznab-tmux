<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TraktService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TraktServiceTest extends TestCase
{
    private TraktService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'nntmux_api.trakttv_api_key' => 'test_trakt_key',
            'nntmux_api.trakttv_timeout' => 30,
            'nntmux_api.trakttv_retry_times' => 1,
            'nntmux_api.trakttv_retry_delay' => 1,
            'cache.default' => 'array',
        ]);

        Cache::flush();
        $this->service = new TraktService;
    }

    public function test_get_show_summary_accepts_numeric_trakt_id(): void
    {
        Http::fake([
            'https://api.trakt.tv/shows/1390*' => Http::response([
                'title' => 'Breaking Bad',
                'ids' => [
                    'trakt' => 1390,
                ],
            ], 200),
        ]);

        $summary = $this->service->getShowSummary(1390, 'full');

        $this->assertNotNull($summary);
        $this->assertSame('Breaking Bad', $summary['title']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://api.trakt.tv/shows/1390')
                && $request['extended'] === 'full';
        });
    }
}
