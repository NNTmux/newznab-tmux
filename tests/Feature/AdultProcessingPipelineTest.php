<?php

namespace Tests\Feature;

use App\Services\AdultProcessing\AdultProcessingPassable;
use App\Services\AdultProcessing\AdultProcessingPipeline;
use App\Services\AdultProcessing\AdultProcessingResult;
use App\Services\AdultProcessing\AdultReleaseContext;
use App\Services\AdultProcessing\Pipes\AbstractAdultProviderPipe;
use App\Services\AdultProcessing\Pipes\AdePipe;
use App\Services\AdultProcessing\Pipes\AdmPipe;
use App\Services\AdultProcessing\Pipes\AebnPipe;
use App\Services\AdultProcessing\Pipes\HotmoviesPipe;
use App\Services\AdultProcessing\Pipes\PoppornPipe;
use Tests\TestCase;

class AdultProcessingPipelineTest extends TestCase
{
    /**
     * Test that the pipeline can be instantiated with default pipes.
     */
    public function test_pipeline_can_be_instantiated(): void
    {
        $pipeline = new AdultProcessingPipeline;

        $this->assertInstanceOf(AdultProcessingPipeline::class, $pipeline);
        $this->assertCount(5, $pipeline->getPipes());
    }

    /**
     * Test that pipes are ordered by priority.
     */
    public function test_pipes_are_ordered_by_priority(): void
    {
        $pipeline = new AdultProcessingPipeline;
        $pipes = $pipeline->getPipes()->values();

        // AEBN should be first (priority 10)
        $this->assertInstanceOf(AebnPipe::class, $pipes[0]);
        // PopPorn should be second (priority 20)
        $this->assertInstanceOf(PoppornPipe::class, $pipes[1]);
        // ADM should be third (priority 30)
        $this->assertInstanceOf(AdmPipe::class, $pipes[2]);
        // ADE should be fourth (priority 40)
        $this->assertInstanceOf(AdePipe::class, $pipes[3]);
        // HotMovies should be last (priority 50)
        $this->assertInstanceOf(HotmoviesPipe::class, $pipes[4]);
    }

    /**
     * Test that a pipe can be added to the pipeline.
     */
    public function test_can_add_pipe_to_pipeline(): void
    {
        $pipeline = new AdultProcessingPipeline([]);

        $this->assertCount(5, $pipeline->getPipes()); // Default pipes

        // Create a custom pipe with high priority
        $customPipe = new class extends AbstractAdultProviderPipe
        {
            protected int $priority = 5;

            public function getName(): string
            {
                return 'custom';
            }

            public function getDisplayName(): string
            {
                return 'Custom Provider';
            }

            protected function getBaseUrl(): string
            {
                return 'https://example.com';
            }

            protected function process(AdultProcessingPassable $passable): AdultProcessingResult
            {
                return AdultProcessingResult::notFound($this->getName());
            }

            protected function search(string $movie): array|false
            {
                return false;
            }

            protected function getMovieInfo(): array|false
            {
                return false;
            }
        };

        $pipeline->addPipe($customPipe);

        $this->assertCount(6, $pipeline->getPipes());
    }

    /**
     * Test AdultReleaseContext creation from title.
     */
    public function test_release_context_from_title(): void
    {
        $context = AdultReleaseContext::fromTitle('Test Movie Title');

        $this->assertEquals(0, $context->releaseId);
        $this->assertEquals('Test Movie Title', $context->searchName);
        $this->assertEquals('Test Movie Title', $context->cleanTitle);
        $this->assertNull($context->guid);
    }

    /**
     * Test AdultReleaseContext creation from release array.
     */
    public function test_release_context_from_release(): void
    {
        $release = [
            'id' => 123,
            'searchname' => 'Test.Movie.Title.XXX.2023',
            'guid' => 'abc123',
        ];

        $context = AdultReleaseContext::fromRelease($release, 'Test Movie Title');

        $this->assertEquals(123, $context->releaseId);
        $this->assertEquals('Test.Movie.Title.XXX.2023', $context->searchName);
        $this->assertEquals('Test Movie Title', $context->cleanTitle);
        $this->assertEquals('abc123', $context->guid);
    }

    /**
     * Test AdultProcessingResult creation methods.
     */
    public function test_processing_result_creation(): void
    {
        // Test matched result
        $matchedResult = AdultProcessingResult::matched(
            'Test Movie',
            'aebn',
            ['boxcover' => 'http://example.com/cover.jpg']
        );
        $this->assertEquals(AdultProcessingResult::STATUS_MATCHED, $matchedResult->status);
        $this->assertTrue($matchedResult->isMatched());
        $this->assertFalse($matchedResult->shouldContinueProcessing());
        $this->assertEquals('http://example.com/cover.jpg', $matchedResult->getBoxCover());

        // Test not found result
        $notFoundResult = AdultProcessingResult::notFound('aebn');
        $this->assertEquals(AdultProcessingResult::STATUS_NOT_FOUND, $notFoundResult->status);
        $this->assertFalse($notFoundResult->isMatched());
        $this->assertTrue($notFoundResult->shouldContinueProcessing());

        // Test skipped result
        $skippedResult = AdultProcessingResult::skipped('Provider unavailable', 'aebn');
        $this->assertEquals(AdultProcessingResult::STATUS_SKIPPED, $skippedResult->status);
        $this->assertFalse($skippedResult->isMatched());

        // Test pending result
        $pendingResult = AdultProcessingResult::pending();
        $this->assertEquals(AdultProcessingResult::STATUS_PENDING, $pendingResult->status);
        $this->assertFalse($pendingResult->isMatched());
    }

    /**
     * Test AdultProcessingPassable behavior.
     */
    public function test_processing_passable_behavior(): void
    {
        $context = AdultReleaseContext::fromTitle('Test Movie');
        $passable = new AdultProcessingPassable($context, true, 'test_cookie');

        $this->assertEquals('Test Movie', $passable->getCleanTitle());
        $this->assertEquals('test_cookie', $passable->getCookie());
        $this->assertFalse($passable->shouldStopProcessing());

        // Update with a matched result
        $result = AdultProcessingResult::matched('Test Movie', 'aebn', []);
        $passable->updateResult($result, 'aebn');

        $this->assertTrue($passable->shouldStopProcessing());
        $this->assertEquals('aebn', $passable->result->providerName);
    }

    /**
     * Test individual pipe name and priority.
     */
    public function test_pipe_names_and_priorities(): void
    {
        $aebn = new AebnPipe;
        $this->assertEquals('aebn', $aebn->getName());
        $this->assertEquals('Adult Entertainment Broadcast Network', $aebn->getDisplayName());
        $this->assertEquals(10, $aebn->getPriority());

        $popporn = new PoppornPipe;
        $this->assertEquals('pop', $popporn->getName());
        $this->assertEquals('PopPorn', $popporn->getDisplayName());
        $this->assertEquals(20, $popporn->getPriority());

        $adm = new AdmPipe;
        $this->assertEquals('adm', $adm->getName());
        $this->assertEquals('Adult DVD Marketplace', $adm->getDisplayName());
        $this->assertEquals(30, $adm->getPriority());

        $ade = new AdePipe;
        $this->assertEquals('ade', $ade->getName());
        $this->assertEquals('Adult DVD Empire', $ade->getDisplayName());
        $this->assertEquals(40, $ade->getPriority());

        $hotmovies = new HotmoviesPipe;
        $this->assertEquals('hotm', $hotmovies->getName());
        $this->assertEquals('HotMovies', $hotmovies->getDisplayName());
        $this->assertEquals(50, $hotmovies->getPriority());
    }

    /**
     * Test passable to array conversion with debug info.
     */
    public function test_passable_to_array_with_debug(): void
    {
        $context = AdultReleaseContext::fromRelease([
            'id' => 123,
            'searchname' => 'Test.Movie.XXX',
        ], 'Test Movie');

        $passable = new AdultProcessingPassable($context, true);

        $result = AdultProcessingResult::matched('Test Movie', 'aebn', [
            'boxcover' => 'http://example.com/cover.jpg',
        ]);
        $passable->updateResult($result, 'aebn');

        $array = $passable->toArray();

        $this->assertEquals('matched', $array['status']);
        $this->assertEquals('Test Movie', $array['title']);
        $this->assertEquals('aebn', $array['provider']);
        $this->assertArrayHasKey('debug', $array);
        $this->assertEquals(123, $array['debug']['release_id']);
        $this->assertEquals('Test.Movie.XXX', $array['debug']['search_name']);
        $this->assertEquals('Test Movie', $array['debug']['clean_title']);
    }

    /**
     * Test that echo output can be disabled on pipes.
     */
    public function test_pipe_echo_output_can_be_disabled(): void
    {
        $pipe = new AebnPipe;
        $pipe->setEchoOutput(false);

        // This should not throw any exceptions
        $this->assertInstanceOf(AebnPipe::class, $pipe);
    }

    /**
     * Test similarity calculation for title matching.
     */
    public function test_title_similarity_calculation(): void
    {
        $pipe = new class extends AbstractAdultProviderPipe
        {
            public function getName(): string
            {
                return 'test';
            }

            public function getDisplayName(): string
            {
                return 'Test';
            }

            protected function getBaseUrl(): string
            {
                return '';
            }

            protected function process(AdultProcessingPassable $passable): AdultProcessingResult
            {
                return AdultProcessingResult::pending();
            }

            protected function search(string $movie): array|false
            {
                return false;
            }

            protected function getMovieInfo(): array|false
            {
                return false;
            }

            public function publicCalculateSimilarity(string $search, string $result): float
            {
                return $this->calculateSimilarity($search, $result);
            }
        };

        // Exact match should be 100%
        $similarity = $pipe->publicCalculateSimilarity('Test Movie', 'Test Movie');
        $this->assertGreaterThan(99, $similarity);

        // Similar titles should have high similarity
        $similarity = $pipe->publicCalculateSimilarity('Test Movie', 'Test Movie 2023');
        $this->assertGreaterThan(80, $similarity);

        // Different titles should have lower similarity
        $similarity = $pipe->publicCalculateSimilarity('Test Movie', 'Completely Different Title');
        $this->assertLessThan(50, $similarity);
    }
}
