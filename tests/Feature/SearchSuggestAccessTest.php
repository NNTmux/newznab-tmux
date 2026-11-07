<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\SearchSuggestController;
use App\Models\User;
use App\Services\Search\Contracts\SearchServiceInterface;
use Illuminate\Http\Request;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SearchSuggestAccessTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Test]
    public function guests_cannot_read_search_suggestions(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        foreach (['autocomplete', 'suggest', 'assist'] as $endpoint) {
            $this->getJson("/api/search/{$endpoint}?q=movie")->assertUnauthorized();
        }
    }

    #[Test]
    public function autocomplete_always_uses_the_configured_releases_index(): void
    {
        $searchEngine = Mockery::mock(SearchServiceInterface::class);
        $searchEngine->shouldReceive('getReleasesIndex')->andReturn('releases_rt');
        $searchEngine->shouldReceive('isAutocompleteEnabled')->andReturnTrue();
        $searchEngine->shouldReceive('autocomplete')->once()->with('movie', 'releases_rt')->andReturn([['suggest' => 'Movie']]);
        $request = Request::create('/api/search/autocomplete', 'GET', ['q' => 'movie', 'index' => 'users']);
        $request->setUserResolver(fn () => tap(new User, fn (User $user) => $user->id = 7));

        $response = (new SearchSuggestController($searchEngine))->autocomplete($request);

        $this->assertSame(['Movie'], $response->getData(true)['suggestions']);
    }
}
