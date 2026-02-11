<?php

namespace App\Services\Categorization;

use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Services\Categorization\Pipes\AbstractCategorizationPipe;
use App\Services\Categorization\Pipes\CategorizationPassable;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;

/**
 * Pipeline-based categorization service using Laravel Pipeline.
 *
 * This service uses Laravel's Pipeline to orchestrate multiple categorizers
 * to determine the best category for a release. Each categorizer (pipe) is
 * responsible for a specific category domain and returns a result with a
 * confidence score.
 */
class CategorizationPipeline
{
    /**
     * @var Collection<AbstractCategorizationPipe>
     */
    protected Collection $pipes; // @phpstan-ignore missingType.generics

    protected bool $categorizeForeign;

    protected bool $catWebDL;

    /**
     * @param  iterable<AbstractCategorizationPipe>  $pipes
     */
    public function __construct(iterable $pipes = [])
    {
        /** @phpstan-ignore argument.templateType */
        $this->pipes = collect($pipes)
            ->sortBy(fn (AbstractCategorizationPipe $p) => $p->getPriority());

        $this->categorizeForeign = (bool) Settings::settingValue('categorizeforeign');
        $this->catWebDL = (bool) Settings::settingValue('catwebdl');
    }

    /**
     * Register a categorizer pipe in the pipeline.
     */
    public function addCategorizer(AbstractCategorizationPipe $pipe): self
    {
        $this->pipes->push($pipe);
        $this->pipes = $this->pipes->sortBy(fn (AbstractCategorizationPipe $p) => $p->getPriority());

        return $this;
    }

    /**
     * Determine the category for a release using Laravel Pipeline.
     *
     * @param  int|string  $groupId  The usenet group ID
     * @param  string  $releaseName  The name of the release
     * @param  string|null  $poster  The poster name
     * @param  bool  $debug  Whether to include debug information
     * @return array<string, mixed> The categorization result
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

        $passable = new CategorizationPassable($context, $debug);

        /** @var CategorizationPassable $result */
        $result = app(Pipeline::class)
            ->send($passable)
            ->through($this->pipes->values()->all())
            ->thenReturn();

        return $result->toArray();
    }

    /**
     * Get all registered categorizers (pipes).
     *
     * @return Collection<AbstractCategorizationPipe>
     */
    public function getCategorizers(): Collection // @phpstan-ignore missingType.generics
    {
        return $this->pipes;
    }

    /**
     * Create a default pipeline with all standard categorizers.
     */
    public static function createDefault(): self
    {
        return new self([
            new Pipes\GroupNamePipe,
            new Pipes\XxxPipe,
            new Pipes\TvPipe,
            new Pipes\MoviePipe,
            new Pipes\BookPipe,
            new Pipes\MusicPipe,
            new Pipes\PcPipe,
            new Pipes\ConsolePipe,
            new Pipes\MiscPipe,
        ]);
    }
}
