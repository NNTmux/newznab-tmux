<?php

declare(strict_types=1);

namespace App\Services\Binaries\Obfuscated;

/**
 * Settings for rebuilding uploads whose every article carries its own random subject.
 */
final readonly class ObfuscatedUploadConfig
{
    public const string POLICY_RANK = 'rank';

    public const string POLICY_DROP = 'drop';

    public const string POLICY_SKIP = 'skip';

    public function __construct(
        public bool $enabled = false,
        public int $quietMinutes = 20,
        public int $minArticles = 50,
        public int $gapMinutes = 30,
        public int $holdMinutes = 360,
        public float $minCompletionPercent = 98.0,
        public string $ambiguousPolicy = self::POLICY_RANK,
        public int $maxPartsPerUpload = 250000,
        public int $maxUploadsPerRun = 200,
        public int $sqlChunkSize = 500,
    ) {}

    public static function fromConfig(): self
    {
        $policy = strtolower((string) config('nntmux.cbp.obfuscated.ambiguous_policy', self::POLICY_RANK));

        return new self(
            enabled: (bool) config('nntmux.cbp.obfuscated.enabled', false),
            quietMinutes: max(1, (int) config('nntmux.cbp.obfuscated.quiet_minutes', 20)),
            minArticles: max(2, (int) config('nntmux.cbp.obfuscated.min_articles', 50)),
            gapMinutes: max(1, (int) config('nntmux.cbp.obfuscated.gap_minutes', 30)),
            holdMinutes: max(1, (int) config('nntmux.cbp.obfuscated.hold_minutes', 360)),
            minCompletionPercent: max(0.0, min(100.0, (float) config('nntmux.cbp.obfuscated.min_completion_percent', 98))),
            ambiguousPolicy: \in_array($policy, [self::POLICY_RANK, self::POLICY_DROP, self::POLICY_SKIP], true) ? $policy : self::POLICY_RANK,
            maxPartsPerUpload: max(1, (int) config('nntmux.cbp.obfuscated.max_parts_per_upload', 250000)),
            maxUploadsPerRun: max(1, (int) config('nntmux.cbp.obfuscated.max_uploads_per_run', 200)),
            sqlChunkSize: max(50, min(1000, (int) config('nntmux.cbp.sql_chunk_size', 500))),
        );
    }
}
