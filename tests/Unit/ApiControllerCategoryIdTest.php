<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class ApiControllerCategoryIdTest extends TestCase
{
    public function test_category_id_returns_default_for_missing_cat(): void
    {
        $request = Request::create('/', 'GET');

        $this->assertSame([-1], $this->makeController()->categoryID($request));
    }

    public function test_category_id_returns_default_for_null_cat(): void
    {
        $request = Request::create('/', 'GET', ['cat' => null]);

        $this->assertSame([-1], $this->makeController()->categoryID($request));
    }

    public function test_category_id_decodes_url_encoded_cat_string(): void
    {
        $request = Request::create('/', 'GET', ['cat' => '2030%2C2040']);

        $this->assertSame(['2030', '2040'], $this->makeController()->categoryID($request));
    }

    public function test_category_id_normalizes_array_input(): void
    {
        $request = Request::create('/', 'GET', ['cat' => ['2030', '', null, '2040']]);

        $this->assertSame(['2030', '2040'], $this->makeController()->categoryID($request));
    }

    private function makeController(): ApiController
    {
        return (new \ReflectionClass(ApiController::class))->newInstanceWithoutConstructor();
    }
}
