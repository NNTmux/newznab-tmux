<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PopulateAniListService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ReflectionProperty;
use Tests\TestCase;

class AniListServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'nntmux.echocli' => false,
        ]);

        $this->resetRateLimitPause();
    }

    public function test_search_anime_returns_media_results(): void
    {
        $service = $this->makeServiceWithResponses(
            $this->graphqlResponse([
                'data' => [
                    'Page' => [
                        'media' => [[
                            'id' => 9253,
                            'idMal' => 9253,
                            'title' => [
                                'romaji' => 'Steins;Gate',
                                'english' => 'Steins;Gate',
                                'native' => 'Steins;Gate',
                            ],
                            'episodes' => 24,
                            'averageScore' => 91,
                            'format' => 'TV',
                            'genres' => ['Sci-Fi', 'Thriller'],
                        ]],
                    ],
                ],
            ])
        );

        $result = $service->searchAnime('Steins;Gate', 1);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(9253, $result[0]['id']);
        $this->assertSame(9253, $result[0]['idMal']);
        $this->assertSame('Steins;Gate', $result[0]['title']['romaji']);
    }

    public function test_search_anime_returns_empty_array_when_no_matches_exist(): void
    {
        $service = $this->makeServiceWithResponses(
            $this->graphqlResponse([
                'data' => [
                    'Page' => [
                        'media' => [],
                    ],
                ],
            ])
        );

        $result = $service->searchAnime('NonExistentAnime12345');

        $this->assertSame([], $result);
    }

    public function test_get_anime_by_id_returns_media_payload(): void
    {
        $service = $this->makeServiceWithResponses(
            $this->graphqlResponse([
                'data' => [
                    'Media' => [
                        'id' => 5114,
                        'idMal' => 5114,
                        'title' => [
                            'romaji' => 'Fullmetal Alchemist: Brotherhood',
                            'english' => 'Fullmetal Alchemist: Brotherhood',
                            'native' => '鋼の錬金術師 FULLMETAL ALCHEMIST',
                        ],
                        'format' => 'TV',
                    ],
                ],
            ])
        );

        $result = $service->getAnimeById(5114);

        $this->assertIsArray($result);
        $this->assertSame(5114, $result['id']);
        $this->assertSame('TV', $result['format']);
    }

    public function test_get_anime_by_id_returns_false_when_api_returns_errors(): void
    {
        $service = $this->makeServiceWithResponses(
            $this->graphqlResponse([
                'errors' => [
                    ['message' => 'Bad query'],
                ],
            ])
        );

        $result = $service->getAnimeById(999999);

        $this->assertFalse($result);
    }

    private function makeServiceWithResponses(Response ...$responses): PopulateAniListService
    {
        $service = new PopulateAniListService;

        $client = new Client([
            'base_uri' => 'https://graphql.anilist.co',
            'handler' => HandlerStack::create(new MockHandler($responses)),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        $property = new ReflectionProperty($service, 'client');
        $property->setAccessible(true);
        $property->setValue($service, $client);

        return $service;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function graphqlResponse(array $body, int $status = 200): Response
    {
        return new Response(
            $status,
            [
                'X-RateLimit-Remaining' => '19',
                'X-RateLimit-Reset' => (string) (time() + 60),
            ],
            json_encode($body) ?: '{}'
        );
    }

    private function resetRateLimitPause(): void
    {
        $property = new ReflectionProperty(PopulateAniListService::class, 'rateLimitPause');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}
