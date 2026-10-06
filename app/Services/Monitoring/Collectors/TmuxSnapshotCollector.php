<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Collectors;

use App\Services\Monitoring\Prometheus\MetricFamily;
use App\Services\Monitoring\TmuxMetricsSnapshot;

/**
 * Exposes the tmux monitor's processing statistics from its published snapshot.
 */
class TmuxSnapshotCollector implements MetricsCollector
{
    private const array TABLES = ['collections_table', 'binaries_table', 'parts_table', 'missed_parts_table'];

    private const array CATEGORIES = ['tv', 'movies', 'audio', 'books', 'console', 'pc', 'xxx', 'misc'];

    public function __construct(private readonly TmuxMetricsSnapshot $snapshot) {}

    public function collect(): iterable
    {
        $snapshot = $this->snapshot->read();

        yield MetricFamily::gauge('nntmux_tmux_snapshot_available', 'Whether a recent tmux monitor snapshot exists.')
            ->add($snapshot === null ? 0 : 1);

        if ($snapshot === null) {
            return;
        }

        $counts = $snapshot['counts'];

        yield MetricFamily::gauge('nntmux_tmux_snapshot_age_seconds', 'Seconds since the tmux monitor last published statistics.')
            ->add(max(0, time() - $snapshot['collected_at']));

        yield MetricFamily::gauge('nntmux_tmux_running', 'Whether tmux processing is running (1) or paused (0).')
            ->add($snapshot['is_running']);

        $backlog = MetricFamily::gauge('nntmux_processing_backlog', 'Releases waiting for post-processing, by type.');
        foreach ($counts as $key => $value) {
            if (str_starts_with($key, 'process')) {
                $backlog->add($value, ['type' => substr($key, strlen('process'))]);
            }
        }
        yield $backlog;

        yield MetricFamily::gauge('nntmux_processing_total_work', 'Sum of all post-processing backlogs.')
            ->add($counts['total_work'] ?? 0);

        yield MetricFamily::gauge('nntmux_additional_backlog', 'Releases waiting for additional processing.')
            ->add($counts['work'] ?? 0);

        yield MetricFamily::gauge('nntmux_additional_backlog_available', 'Additional-processing backlog that is currently claimable.')
            ->add($counts['work_available'] ?? 0);

        $tables = MetricFamily::gauge('nntmux_table_rows', 'Estimated rows in the binary processing tables.');
        foreach (self::TABLES as $table) {
            if (isset($counts[$table])) {
                $tables->add($counts[$table], ['table' => substr($table, 0, -strlen('_table'))]);
            }
        }
        yield $tables;

        yield MetricFamily::gauge('nntmux_releases', 'Total releases.')->add($counts['releases'] ?? 0);

        $categories = MetricFamily::gauge('nntmux_releases_by_category', 'Releases per parent category.');
        foreach (self::CATEGORIES as $category) {
            if (isset($counts[$category])) {
                $categories->add($counts[$category], ['category' => $category]);
            }
        }
        yield $categories;

        yield MetricFamily::gauge('nntmux_predb_entries', 'PreDB entries.')->add($counts['predb'] ?? 0);
        yield MetricFamily::gauge('nntmux_predb_matched_releases', 'Releases matched to a PreDB entry.')->add($counts['predb_matched'] ?? 0);
        yield MetricFamily::gauge('nntmux_releases_with_nfo', 'Releases with an NFO.')->add($counts['nfo'] ?? 0);
        yield MetricFamily::gauge('nntmux_releases_renamed', 'Releases renamed by the name fixer.')->add($counts['renamed'] ?? 0);

        yield MetricFamily::gauge('nntmux_groups', 'Usenet groups by state.')
            ->add($counts['active_groups'] ?? 0, ['state' => 'active'])
            ->add($counts['all_groups'] ?? 0, ['state' => 'all']);

        $connections = MetricFamily::gauge('nntmux_nntp_connections', 'NNTP connections held by this host.');
        foreach ($snapshot['connections'] as $server => $connectionCounts) {
            $connections
                ->add($connectionCounts['active'], ['server' => $server, 'state' => 'active'])
                ->add($connectionCounts['total'], ['server' => $server, 'state' => 'total']);
        }
        yield $connections;

        $timers = MetricFamily::gauge('nntmux_tmux_query_duration_seconds', 'Duration of the tmux monitor statistics queries.');
        foreach ($snapshot['query_timers'] as $query => $seconds) {
            $timers->add($seconds, ['query' => $query]);
        }
        yield $timers;
    }
}
