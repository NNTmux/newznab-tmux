<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Symfony\Component\HttpFoundation\Request;

class TrustProxies extends Middleware
{
    /**
     * Trust the upstream reverse proxy chain.
     *
     * This keeps Cloudflare / CDN deployments working without the external
     * package while remaining compatible with Laravel 12 and 13.
     *
     * @var string|array<int, string>|null
     */
    protected $proxies = '*';

    /**
     * The headers used to detect proxy forwarding information.
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PREFIX
        | Request::HEADER_X_FORWARDED_AWS_ELB;
}
