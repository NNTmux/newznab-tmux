<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use SplFileObject;

class RegistrationFailureLogService
{
    private string $logsDirectory;

    public function __construct(?string $logsDirectory = null)
    {
        $this->logsDirectory = rtrim($logsDirectory ?? storage_path('logs'), DIRECTORY_SEPARATOR);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentFailures(int $limit = 10): array
    {
        $entries = [];

        foreach ($this->registrationLogFiles() as $path) {
            $lines = $this->readLines($path);

            for ($index = count($lines) - 1; $index >= 0; $index--) {
                if (! str_contains($lines[$index], 'Registration attempt failed:')) {
                    continue;
                }

                $entry = $this->parseLine($lines[$index]);

                if ($entry === null) {
                    continue;
                }

                $entries[] = $entry;

                if (count($entries) >= $limit) {
                    return $entries;
                }
            }
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function registrationLogFiles(): array
    {
        if (! File::isDirectory($this->logsDirectory)) {
            return [];
        }

        $paths = glob($this->logsDirectory.DIRECTORY_SEPARATOR.'registration*.log*') ?: [];
        $paths = array_values(array_filter($paths, static fn (string $path): bool => is_file($path) && is_readable($path)));

        usort($paths, static function (string $left, string $right): int {
            return filemtime($right) <=> filemtime($left);
        });

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function readLines(string $path): array
    {
        $lines = [];
        $file = new SplFileObject($path, 'r');

        while (! $file->eof()) {
            $line = rtrim($file->fgets(), "\r\n");

            if ($line === '') {
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseLine(string $line): ?array
    {
        $timestamp = null;
        $level = 'INFO';
        $message = $line;
        $context = [];

        if (preg_match(
            '/^\[(?<timestamp>[^\]]+)\]\s+[A-Za-z0-9_.-]+\.(?<level>[A-Z]+):\s+(?<message>.*?)\s+(?<context>\{.*\})(?:\s+\[\])?$/',
            $line,
            $matches
        )) {
            $timestamp = $matches['timestamp'];
            $level = $matches['level'];
            $message = $matches['message'];
            $decodedContext = json_decode($matches['context'], true);
            $context = is_array($decodedContext) ? $decodedContext : [];
        } elseif (preg_match(
            '/^\[(?<timestamp>[^\]]+)\]\s+[A-Za-z0-9_.-]+\.(?<level>[A-Z]+):\s+(?<message>.+)$/',
            $line,
            $matches
        )) {
            $timestamp = $matches['timestamp'];
            $level = $matches['level'];
            $message = $matches['message'];
        }

        if ($timestamp === null) {
            return null;
        }

        return [
            'timestamp' => CarbonImmutable::parse($timestamp),
            'level' => strtolower($level),
            'message' => $message,
            'reason' => $context['reason'] ?? null,
            'username' => $context['username'] ?? null,
            'email' => $context['email'] ?? null,
            'ip' => $context['ip'] ?? null,
            'registration_status' => $context['registration_status'] ?? null,
            'manual_registration_status' => $context['manual_registration_status'] ?? null,
            'context' => $context,
        ];
    }
}
