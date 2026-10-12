<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification {
    /** @param resource $handle */
    function fwrite($handle, string $data): int|false
    {
        return \fwrite($handle, $GLOBALS['write_fault'] === 'write' ? substr($data, 0, 1) : $data);
    }

    /** @param resource $handle */
    function fflush($handle): bool
    {
        return $GLOBALS['write_fault'] !== 'flush' && \fflush($handle);
    }

    /** @param resource $handle */
    function fsync($handle): bool
    {
        return $GLOBALS['write_fault'] !== 'sync' && \fsync($handle);
    }

    function rename(string $from, string $to): bool
    {
        return $GLOBALS['write_fault'] !== 'rename' && \rename($from, $to);
    }
}

namespace {
    use App\Services\Nzb\NzbService;
    use App\Services\Releases\Verification\ManifestStore;
    use App\Services\Releases\Verification\VerifiedNzbWriter;
    use Illuminate\Contracts\Console\Kernel;

    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    /** @var list<string> $arguments */
    $arguments = $_SERVER['argv'] ?? [];
    [$script, $writer, $fault, $root] = $arguments;
    $GLOBALS['write_fault'] = $fault;
    config(['release_verification.storage_path' => $root]);
    $final = $root.'/release.nzb.gz';
    file_put_contents($final, 'previous NZB');
    $failure = null;
    try {
        if ($writer === 'manifest') {
            (new ManifestStore)->write(['files' => []]);
        } else {
            $nzb = new class($final) extends NzbService
            {
                public function __construct(private string $target) {}

                public function getNzbPath(string $releaseGuid, int $levelsToSplit = 0, bool $createIfNotExist = false): string
                {
                    return $this->target;
                }
            };
            (new VerifiedNzbWriter($nzb))->write(['files' => [], 'fromname' => 'poster', 'date' => '2026-10-11'], str_repeat('a', 32));
        }
    } catch (Throwable $error) {
        $failure = $error->getMessage();
    }
    echo json_encode(['failure' => $failure, 'temporary_files' => count(glob($root.'/*.tmp')) + count(glob($root.'/manifests/*.tmp')),
        'previous_nzb_preserved' => file_get_contents($final) === 'previous NZB', 'final_manifests' => count(glob($root.'/manifests/*.json.gz'))], JSON_THROW_ON_ERROR);
}
