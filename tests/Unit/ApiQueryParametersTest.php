<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Api\ApiQueryParameters;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ApiQueryParametersTest extends TestCase
{
    private ApiQueryParameters $parameters;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parameters = new ApiQueryParameters;
    }

    public function test_common_defaults_match_both_api_versions(): void
    {
        $request = Request::create('/', 'GET');

        self::assertSame([-1], $this->parameters->categories($request));
        self::assertSame(250, $this->parameters->limit($request));
        self::assertSame(0, $this->parameters->offset($request));
        self::assertSame(0, $this->parameters->minimumSize($request));
        self::assertSame(-1, $this->parameters->maximumAge($request));
        self::assertSame('posted_desc', $this->parameters->sort($request));
        self::assertTrue($this->parameters->hasValidSort($request));
    }

    public function test_numeric_pagination_and_sort_are_normalized(): void
    {
        $request = Request::create('/', 'GET', [
            'limit' => '25',
            'offset' => '50',
            'minsize' => '1024',
            'maxage' => '7',
            'sort' => ' NAME_ASC ',
        ]);

        self::assertSame(25, $this->parameters->limit($request));
        self::assertSame(50, $this->parameters->offset($request));
        self::assertSame(1024, $this->parameters->minimumSize($request));
        self::assertSame(7, $this->parameters->maximumAge($request));
        self::assertSame('name_asc', $this->parameters->sort($request));
        self::assertTrue($this->parameters->hasValidSort($request));
    }

    public function test_limit_is_capped_at_the_configured_maximum(): void
    {
        self::assertSame(500, $this->parameters->limit(Request::create('/', 'GET', ['limit' => '1000'])));

        config(['nntmux.api.max_limit' => 150]);

        self::assertSame(150, $this->parameters->limit(Request::create('/', 'GET', ['limit' => '1000'])));
    }

    public function test_configured_default_never_exceeds_the_maximum(): void
    {
        config(['nntmux.api.max_limit' => 100, 'nntmux.api.default_limit' => 250]);

        self::assertSame(100, $this->parameters->defaultLimit());
        self::assertSame(100, $this->parameters->limit(Request::create('/', 'GET')));
    }

    public function test_invalid_sort_is_reported_without_building_a_response(): void
    {
        $request = Request::create('/', 'GET', ['sort' => 'unexpected']);

        self::assertFalse($this->parameters->hasValidSort($request));
    }
}
