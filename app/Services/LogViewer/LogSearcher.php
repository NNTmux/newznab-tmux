<?php

declare(strict_types=1);

namespace App\Services\LogViewer;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Searches log files for matching lines and returns the newest matching entries per file.
 *
 * Matching lines come from GNU grep when it is available (fast, runs outside PHP) and from a
 * streaming PHP scanner otherwise. Each hit is a byte offset that is then resolved to its full
 * entry, so a match inside a stack trace is reported as the entry that owns it.
 */
class LogSearcher
{
    private const int HIT_BUFFER_FACTOR = 4;

    private const int PHP_DEADLINE_CHECK_INTERVAL = 4096;

    private const int PHP_BACKTRACK_LIMIT = 100_000;

    /**
     * @var array{binary: string, gnu: bool, pcre: bool}|null
     */
    private ?array $grep = null;

    public function __construct(private readonly LogReader $reader) {}

    /**
     * @return 'grep'|'php'
     */
    public function engine(bool $needsPcre = false): string
    {
        $grep = $this->grep();

        return $grep['gnu'] && (! $needsPcre || $grep['pcre']) ? 'grep' : 'php';
    }

    /**
     * @param  list<LogFile>  $files
     * @return array{
     *     engine: string,
     *     elapsed_ms: int,
     *     results: list<array{
     *         file: string,
     *         total_matches: int,
     *         has_more: bool,
     *         before: int|null,
     *         timed_out: bool,
     *         error: string|null,
     *         entries: list<array<string, mixed>>
     *     }>
     * }
     */
    public function search(SearchQuery $query, array $files, ?int $before, int $limitPerFile): array
    {
        $startedAt = hrtime(true);
        $deadline = microtime(true) + max(1, (int) config('nntmux.log_viewer.search_timeout', 20));
        $engine = $this->engine($query->needsPcreGrep());
        $results = [];

        foreach ($files as $file) {
            $results[] = $this->searchFile($query, $file, $before, $limitPerFile, $deadline, $engine);
        }

        return [
            'engine' => $engine,
            'elapsed_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'results' => $results,
        ];
    }

    /**
     * @return array{file: string, total_matches: int, has_more: bool, before: int|null, timed_out: bool, error: string|null, entries: list<array<string, mixed>>}
     */
    private function searchFile(SearchQuery $query, LogFile $file, ?int $before, int $limit, float $deadline, string $engine): array
    {
        $bufferSize = max(1, $limit * self::HIT_BUFFER_FACTOR);
        /** @var list<array{int, int}> $hits newest hits as [offset, line number], oldest first */
        $hits = [];
        $totalMatches = 0;
        $eligible = 0;

        $collect = function (int $offset, int $lineNumber) use (&$hits, &$totalMatches, &$eligible, $before, $bufferSize): void {
            $totalMatches++;

            if ($before !== null && $offset >= $before) {
                return;
            }

            $eligible++;
            $hits[] = [$offset, $lineNumber];

            if (count($hits) >= $bufferSize * 2) {
                $hits = array_slice($hits, -$bufferSize);
            }
        };

        if (microtime(true) >= $deadline) {
            $status = ['timed_out' => true, 'error' => null];
        } else {
            $status = $engine === 'grep'
                ? $this->grepHits($query, $file, $deadline, $collect)
                : $this->phpHits($query, $file, $deadline, $collect);
        }

        $hits = array_slice($hits, -$bufferSize);
        [$entries, $examined, $cursor] = $this->resolveEntries($query, $file, $hits, $limit, $status['error'] === null);

        $hasMore = $eligible > $examined;

        return [
            'file' => $file->path,
            'total_matches' => $totalMatches,
            'has_more' => $hasMore,
            'before' => $hasMore ? $cursor : null,
            'timed_out' => $status['timed_out'],
            'error' => $status['error'],
            'entries' => $entries,
        ];
    }

    /**
     * Turn hits (oldest first) into newest-first entries, skipping repeat hits inside the same entry.
     *
     * @param  list<array{int, int}>  $hits
     * @return array{list<array<string, mixed>>, int, int|null} entries, hits examined, cursor for older results
     */
    private function resolveEntries(SearchQuery $query, LogFile $file, array $hits, int $limit, bool $canRead): array
    {
        if ($hits === [] || ! $canRead) {
            return [[], 0, null];
        }

        $entries = [];
        $examined = 0;
        $cursor = null;
        $previous = null;
        $handle = $this->reader->open($file);

        try {
            for ($index = count($hits) - 1; $index >= 0; $index--) {
                [$offset, $lineNumber] = $hits[$index];

                if ($previous !== null && $offset >= $previous->offset && $offset < $previous->end) {
                    $examined++;

                    continue;
                }

                if (count($entries) >= $limit) {
                    break;
                }

                $examined++;
                $entry = $this->reader->entryFromHandle($handle, $file, $offset, null, $lineNumber);

                if ($entry === null) {
                    continue;
                }

                $previous = $entry;
                $cursor = $entry->offset;

                if (! $query->acceptsEntry($entry)) {
                    continue;
                }

                $entries[] = $entry->toArray($query);
            }
        } finally {
            fclose($handle);
        }

        return [$entries, $examined, $cursor];
    }

    /**
     * @param  callable(int, int): void  $collect
     * @return array{timed_out: bool, error: string|null}
     */
    private function grepHits(SearchQuery $query, LogFile $file, float $deadline, callable $collect): array
    {
        $command = [$this->grep()['binary'], '-a', '-b', '-n', ...$query->grepArguments(), '--', $file->absolutePath];
        $process = new Process($command, null, ['LC_ALL' => 'C'], null, max(1.0, $deadline - microtime(true)));
        $buffer = '';

        try {
            $process->start();

            foreach ($process->getIterator(Process::ITER_SKIP_ERR) as $chunk) {
                $buffer .= $chunk;
                $lastNewline = strrpos($buffer, "\n");

                if ($lastNewline === false) {
                    continue;
                }

                $this->parseGrepLines(substr($buffer, 0, $lastNewline), $collect);
                $buffer = substr($buffer, $lastNewline + 1);
            }

            $this->parseGrepLines($buffer, $collect);
        } catch (ProcessTimedOutException) {
            $process->stop(0);

            return ['timed_out' => true, 'error' => null];
        } catch (Throwable $exception) {
            return ['timed_out' => false, 'error' => 'Search failed: '.$exception->getMessage()];
        }

        if ($process->getExitCode() === 2) {
            $message = trim($process->getErrorOutput());

            return ['timed_out' => false, 'error' => $message !== '' ? $message : 'grep failed to search this file.'];
        }

        return ['timed_out' => false, 'error' => null];
    }

    /**
     * @param  callable(int, int): void  $collect
     */
    private function parseGrepLines(string $output, callable $collect): void
    {
        if ($output === '') {
            return;
        }

        foreach (explode("\n", $output) as $line) {
            // grep -n -b output: LINE:OFFSET:content (offset is the start of the matching line)
            $first = strpos($line, ':');
            $second = $first === false ? false : strpos($line, ':', $first + 1);

            if ($second === false) {
                continue;
            }

            $collect((int) substr($line, $first + 1, $second - $first - 1), (int) substr($line, 0, $first));
        }
    }

    /**
     * @param  callable(int, int): void  $collect
     * @return array{timed_out: bool, error: string|null}
     */
    private function phpHits(SearchQuery $query, LogFile $file, float $deadline, callable $collect): array
    {
        $handle = @fopen($file->absolutePath, 'rb');

        if ($handle === false) {
            return ['timed_out' => false, 'error' => 'Log file could not be opened.'];
        }

        $usesPcre = $query->regex || ! $query->hasTerm();
        $previousBacktrackLimit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', (string) self::PHP_BACKTRACK_LIMIT);
        $lineNumber = 0;

        try {
            while (($offset = ftell($handle)) !== false && ($line = fgets($handle)) !== false) {
                $lineNumber++;

                if ($lineNumber % self::PHP_DEADLINE_CHECK_INTERVAL === 0 && microtime(true) >= $deadline) {
                    return ['timed_out' => true, 'error' => null];
                }

                if ($query->matches(rtrim($line, "\r\n"))) {
                    $collect($offset, $lineNumber);
                }

                if ($usesPcre && preg_last_error() !== PREG_NO_ERROR) {
                    return ['timed_out' => false, 'error' => 'Regular expression is too expensive: '.preg_last_error_msg()];
                }
            }
        } finally {
            fclose($handle);

            if ($previousBacktrackLimit !== false) {
                ini_set('pcre.backtrack_limit', $previousBacktrackLimit);
            }
        }

        return ['timed_out' => false, 'error' => null];
    }

    /**
     * @return array{binary: string, gnu: bool, pcre: bool}
     */
    private function grep(): array
    {
        if ($this->grep !== null) {
            return $this->grep;
        }

        $configured = trim((string) config('nntmux.log_viewer.grep_binary', 'grep'));
        $unavailable = ['binary' => '', 'gnu' => false, 'pcre' => false];

        if ($configured === '' || ! function_exists('proc_open')) {
            return $this->grep = $unavailable;
        }

        $binary = str_contains($configured, DIRECTORY_SEPARATOR)
            ? (is_executable($configured) ? $configured : null)
            : (new ExecutableFinder)->find($configured);

        if ($binary === null) {
            return $this->grep = $unavailable;
        }

        /** @var array{binary: string, gnu: bool, pcre: bool} $probe */
        $probe = Cache::remember('log_viewer:grep:'.md5($binary), now()->addDay(), static function () use ($binary): array {
            try {
                $version = new Process([$binary, '--version'], null, ['LC_ALL' => 'C'], null, 5);
                $version->run();
                $isGnu = $version->isSuccessful() && str_contains($version->getOutput(), 'GNU grep');

                // Exit code 1 means "no match", i.e. -P is compiled in; 2 means it is not supported.
                $pcre = new Process([$binary, '-P', '-q', '-e', 'x', '--', '/dev/null'], null, ['LC_ALL' => 'C'], null, 5);
                $pcre->run();

                return ['binary' => $binary, 'gnu' => $isGnu, 'pcre' => $isGnu && $pcre->getExitCode() === 1];
            } catch (Throwable) {
                return ['binary' => $binary, 'gnu' => false, 'pcre' => false];
            }
        });

        return $this->grep = $probe;
    }
}
