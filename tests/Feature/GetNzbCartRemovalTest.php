<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\GetNzbController;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GetNzbCartRemovalTest extends TestCase
{
    #[Test]
    public function token_authenticated_downloads_may_remove_items_from_the_cart(): void
    {
        $request = Request::create('/getnzb', 'GET', ['id' => 'guid', 'del' => '1', 'r' => 'token']);

        $this->assertTrue($this->cartRemovalRequested($request));
    }

    #[Test]
    public function a_browser_session_cannot_remove_cart_items_through_a_download_link(): void
    {
        $request = Request::create('/getnzb', 'GET', ['id' => 'guid', 'del' => '1']);
        $request->setUserResolver(fn () => new User);

        $this->assertFalse($this->cartRemovalRequested($request));
    }

    private function cartRemovalRequested(Request $request): bool
    {
        $controller = (new \ReflectionClass(GetNzbController::class))->newInstanceWithoutConstructor();

        return (new \ReflectionMethod($controller, 'cartRemovalRequested'))->invoke($controller, $request);
    }
}
