<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Search;
use App\Models\Predb;
use App\Models\Release;
use App\Services\NameFixing\ConstrainedPredbMatcher;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\PredbIdentityKey;
use Illuminate\Console\Command;
use Throwable;

class ReleaseIdentitySuggestions extends Command
{
    protected $signature = 'releases:identity-suggestions {query? : Filename or partial release title}
        {--release-id= : Read the current search name of this release}
        {--candidate-id=* : Restrict to these known PreDB IDs (maximum 50)}';

    protected $description = 'Report constrained, read-only identity suggestions; never rename or link releases';

    public function handle(ConstrainedPredbMatcher $matcher): int
    {
        $query = (string) ($this->argument('query') ?? '');
        if ($query === '' && ctype_digit((string) $this->option('release-id'))) {
            $query = (string) Release::query()->whereKey((int) $this->option('release-id'))->value('searchname');
        }
        if ($query === '' || strlen($query) > 512 || ! mb_check_encoding($query, 'UTF-8')) {
            $this->error('Provide a release ID or a valid query of at most 512 bytes.');

            return self::FAILURE;
        }
        $ids = $this->option('candidate-id');
        if (! is_array($ids) || count($ids) > 50) {
            $this->error('At most 50 candidate IDs may be supplied.');

            return self::FAILURE;
        }
        foreach ($ids as $id) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                $this->error('Candidate IDs must be positive integers.');

                return self::FAILURE;
            }
        }
        try {
            if ($ids === []) {
                $hits = Search::searchPredb(NameFixingService::predbSearchText($query));
                if (count($hits) > 50) {
                    $this->line(json_encode(['candidate_predb_id' => null, 'reason' => 'candidate_overflow', 'auto_link' => false], JSON_THROW_ON_ERROR));

                    return self::SUCCESS;
                }
                $ids = array_column($hits, 'id');
            }
            $hits = Predb::query()->whereIn('id', $ids)->get(['id', 'title'])->map(static fn (Predb $row): array => [
                'id' => (int) $row->id, 'title' => (string) $row->title,
            ])->all();
            $suggestion = $matcher->suggest($query, $hits);
            $complete = $matcher->completeEvidence($query, $hits);
            $this->line(json_encode(['query' => $query, 'candidate_predb_id' => $suggestion['id'],
                'known_title' => $suggestion['title'] ?? null, 'score' => $suggestion['score'] ?? null,
                'reason' => $suggestion['reason'], 'auto_link' => false,
                'marker_evidence' => $complete['id'] !== null ? 'complete' : 'unresolved_variant',
                'canonical_name_agreement' => isset($suggestion['title']) && PredbIdentityKey::normalize($query) === PredbIdentityKey::normalize($suggestion['title']),
                'candidate_scope' => 'bounded_database_revalidated_candidates',
                'requires' => 'independent exact filename or registered alias corroboration'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } catch (Throwable $error) {
            $this->error('Identity suggestion unavailable: '.$error->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
