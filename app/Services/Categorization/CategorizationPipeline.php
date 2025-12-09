<?php

namespace App\Services\Categorization;

use App\Models\Category;
use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Services\Categorization\Contracts\CategorizerInterface;
use Illuminate\Support\Collection;

/**
 * Pipeline-based categorization service.
 *
 * This service orchestrates multiple categorizers to determine the best
 * category for a release. Each categorizer is responsible for a specific
 * category domain and returns a result with a confidence score.
 */
class CategorizationPipeline
{
    /**
     * @var Collection<CategorizerInterface>
     */
    protected Collection $categorizers;

    protected bool $categorizeForeign;
    protected bool $catWebDL;

    /**
     * @param iterable<CategorizerInterface> $categorizers
     */
    public function __construct(iterable $categorizers = [])
    {
        $this->categorizers = collect($categorizers)
            ->sortBy(fn (CategorizerInterface $c) => $c->getPriority());

        $this->categorizeForeign = (bool) Settings::settingValue('categorizeforeign');
        $this->catWebDL = (bool) Settings::settingValue('catwebdl');
    }

    /**
     * Register a categorizer in the pipeline.
     */
    public function addCategorizer(CategorizerInterface $categorizer): self
    {
        $this->categorizers->push($categorizer);
        $this->categorizers = $this->categorizers->sortBy(fn (CategorizerInterface $c) => $c->getPriority());

        return $this;
    }

    /**
     * Determine the category for a release.
     *
     * @param int|string $groupId The usenet group ID
     * @param string $releaseName The name of the release
     * @param string|null $poster The poster name
     * @param bool $debug Whether to include debug information
     * @return array The categorization result
     */
    public function categorize(
        int|string $groupId,
        string $releaseName,
        ?string $poster = '',
        bool $debug = false
    ): array {
        $groupName = UsenetGroup::whereId($groupId)->value('name') ?? '';

        $context = new ReleaseContext(
            releaseName: $releaseName,
            groupId: $groupId,
            groupName: $groupName,
            poster: $poster ?? '',
            categorizeForeign: $this->categorizeForeign,
            catWebDL: $this->catWebDL,
        );

        $bestResult = CategorizationResult::noMatch();
        $allResults = [];

        foreach ($this->categorizers as $categorizer) {
            // Skip if categorizer determines it shouldn't process this release
            if ($categorizer->shouldSkip($context)) {
                continue;
            }

            $result = $categorizer->categorize($context);

            if ($debug) {
                $allResults[$categorizer->getName()] = [
                    'category_id' => $result->categoryId,
                    'confidence' => $result->confidence,
                    'matched_by' => $result->matchedBy,
                ];
            }

            // If this result is better than our current best, use it
            if ($result->isSuccessful() && $result->shouldOverride($bestResult)) {
                $bestResult = $result;

                // If we have a very high confidence match, we can stop early
                if ($result->confidence >= 0.95) {
                    break;
                }
            }
        }

        // Build the return array
        $returnValue = ['categories_id' => $bestResult->categoryId];

        if ($debug) {
            $returnValue['debug'] = [
                'final_category' => $bestResult->categoryId,
                'final_confidence' => $bestResult->confidence,
                'matched_by' => $bestResult->matchedBy,
                'release_name' => $releaseName,
                'group_name' => $groupName,
                'all_results' => $allResults,
                'categorizer_details' => $bestResult->debug,
            ];
        }

        return $returnValue;
    }

    /**
     * Get all registered categorizers.
     *
     * @return Collection<CategorizerInterface>
     */
    public function getCategorizers(): Collection
    {
        return $this->categorizers;
    }

    /**
     * Create a default pipeline with all standard categorizers.
     */
    public static function createDefault(): self
    {
        return new self([
            new Categorizers\GroupNameCategorizer(),
            new Categorizers\XxxCategorizer(),
            new Categorizers\TvCategorizer(),
            new Categorizers\MovieCategorizer(),
            new Categorizers\BookCategorizer(),
            new Categorizers\MusicCategorizer(),
            new Categorizers\PcCategorizer(),
            new Categorizers\ConsoleCategorizer(),
            new Categorizers\MiscCategorizer(),
        ]);
    }
}

