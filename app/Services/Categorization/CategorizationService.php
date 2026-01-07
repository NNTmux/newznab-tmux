<?php

namespace App\Services\Categorization;

use App\Services\Categorization\Pipes\AbstractCategorizationPipe;

/**
 * Categorization service using Laravel Pipeline.
 *
 * This class is a drop-in replacement for the legacy Blacklight\Categorize
 * with additional features like confidence scoring and debug information.
 */
class CategorizationService
{
    protected CategorizationPipeline $pipeline;

    public function __construct(?CategorizationPipeline $pipeline = null)
    {
        $this->pipeline = $pipeline ?? CategorizationPipeline::createDefault();
    }

    /**
     * Determine category for a release.
     *
     * @param  int|string  $groupId  The usenet group ID
     * @param  string  $releaseName  The name of the release
     * @param  string|null  $poster  The poster name
     * @param  bool  $debug  Whether to include debug information
     * @return array The categorization result with category ID and optional debug info
     */
    public function determineCategory(
        int|string $groupId,
        string $releaseName = '',
        ?string $poster = '',
        bool $debug = false
    ): array {
        return $this->pipeline->categorize($groupId, $releaseName, $poster, $debug);
    }

    /**
     * Batch categorize multiple releases.
     *
     * @param  array  $releases  Array of ['group_id' => x, 'name' => y, 'poster' => z]
     * @return array Array of categorization results
     */
    public function batchCategorize(array $releases): array
    {
        $results = [];

        foreach ($releases as $release) {
            $groupId = $release['group_id'] ?? $release['groupId'] ?? 0;
            $name = $release['name'] ?? $release['releaseName'] ?? '';
            $poster = $release['poster'] ?? '';

            $results[] = [
                'release' => $release,
                'result' => $this->determineCategory($groupId, $name, $poster),
            ];
        }

        return $results;
    }

    /**
     * Get the underlying pipeline.
     */
    public function getPipeline(): CategorizationPipeline
    {
        return $this->pipeline;
    }

    /**
     * Add a custom categorizer pipe to the pipeline.
     */
    public function addCategorizer(AbstractCategorizationPipe $pipe): self
    {
        $this->pipeline->addCategorizer($pipe);

        return $this;
    }

    /**
     * Get statistics about categorizer usage.
     */
    public function getCategorizerStats(): array
    {
        $categorizers = $this->pipeline->getCategorizers();

        return $categorizers->map(function ($categorizer) {
            return [
                'name' => $categorizer->getName(),
                'priority' => $categorizer->getPriority(),
                'class' => get_class($categorizer),
            ];
        })->toArray();
    }
}
