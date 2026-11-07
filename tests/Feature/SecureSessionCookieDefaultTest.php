<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SecureSessionCookieDefaultTest extends TestCase
{
    /**
     * @return array<string, array{string, bool|null, bool|null}>
     */
    public static function settings(): array
    {
        return [
            'unset on an https site becomes secure' => ['https://indexer.example', null, true],
            'unset on an http site stays unset' => ['http://indexer.example', null, null],
            'an explicit false is kept' => ['https://indexer.example', false, false],
        ];
    }

    #[Test]
    #[DataProvider('settings')]
    public function session_cookie_security_follows_the_site_url_when_not_configured(string $appUrl, ?bool $configured, ?bool $expected): void
    {
        config(['app.url' => $appUrl, 'session.secure' => $configured]);

        (new \ReflectionMethod(AppServiceProvider::class, 'secureSessionCookiesOnHttps'))->invoke(new AppServiceProvider($this->app));

        $this->assertSame($expected, config('session.secure'));
    }
}
