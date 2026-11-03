<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UsenetGroup;
use App\Services\Binaries\Obfuscated\ObfuscatedAssemblyResult;
use App\Services\Binaries\Obfuscated\ObfuscatedUploadAssembler;
use App\Services\Binaries\Obfuscated\ObfuscatedUploadConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cbp:assemble-obfuscated
    {--group=* : Group name(s) to process (default: all active groups)}
    {--dry-run : Only report the uploads that would be assembled}
    {--limit= : Maximum uploads per group (default: CBP_OBFUSCATED_MAX_UPLOADS_PER_RUN)}')]
#[Description('Rebuild uploads whose every article has its own random subject into one collection each')]
final class AssembleObfuscatedUploads extends Command
{
    public function handle(): int
    {
        $config = ObfuscatedUploadConfig::fromConfig();
        $assembler = new ObfuscatedUploadAssembler($config);
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $groups = $this->groups();
        if ($groups === null) {
            return self::FAILURE;
        }

        $total = new ObfuscatedAssemblyResult;
        foreach ($groups as $id => $name) {
            $result = $assembler->assembleGroup($id, $dryRun, $limit);
            $total->add($result);
            if ($result->planned === [] && $result->held === 0 && $result->released === 0) {
                continue;
            }

            $this->line("<info>{$name}</info>");
            $this->table(
                ['Poster', 'Files', 'Parts', 'Complete %', 'Size MB', 'Rank-ordered files'],
                array_map(static fn ($upload): array => [
                    mb_strimwidth($upload->poster, 0, 48, '…'),
                    \count($upload->files),
                    $upload->articles(),
                    number_format($upload->completionPercent(), 1),
                    number_format($upload->bytes() / 1048576, 1),
                    $upload->ambiguousFiles(),
                ], $result->planned)
            );
        }

        $this->info(sprintf(
            '%s %d upload(s), %d article(s) merged, %d failed, %d held, %d hold(s) released.',
            $dryRun ? 'Dry run: would assemble' : 'Assembled',
            $dryRun ? \count($total->planned) : $total->merged,
            $total->mergedArticles,
            $total->failed,
            $total->held,
            $total->released,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>|null
     */
    private function groups(): ?array
    {
        $names = array_filter((array) $this->option('group'));
        $query = UsenetGroup::query()->orderBy('name');
        $query = $names === [] ? $query->where('active', 1) : $query->whereIn('name', $names);
        $groups = $query->pluck('name', 'id')->map(static fn ($name): string => (string) $name)->all();

        $missing = array_diff($names, $groups);
        if ($missing !== []) {
            $this->error('Unknown group(s): '.implode(', ', $missing));

            return null;
        }

        return $groups;
    }
}
