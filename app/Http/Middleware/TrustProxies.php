<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Cloudflare\CloudflareIpRangeService;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Symfony\Component\HttpFoundation\Request;

class TrustProxies extends Middleware
{
    /**
     * The headers used to detect proxy forwarding information.
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PREFIX
        | Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * Resolve trusted proxies from the stored Cloudflare manifest and any
     * manually configured proxy ranges.
     *
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $proxies = app(CloudflareIpRangeService::class)->trustedProxies();

        return $proxies !== [] ? $proxies : null;
    }
}
