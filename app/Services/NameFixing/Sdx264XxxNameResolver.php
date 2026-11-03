<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Services\NameFixing\Data\NameFixResult;
use App\Services\Search\Contracts\SearchServiceInterface;
use Throwable;

final class Sdx264XxxNameResolver
{
    private const PREFIX = 'SDX264XXX-';

    /**
     * @var array<string, string>
     */
    private const SOURCE_ALIASES = [
        'W4B' => 'Watch4Beauty',
        'WA' => 'WhippedAss',
        'WAP' => 'WetAndPuffy',
        'WCX' => 'WoodmanCastingX',
        'WFBG' => 'WeFuckBlackGirls',
        'WG' => 'WowGirls',
        'WGP' => 'WhenGirlsPlay',
    ];

    /**
     * Tokens that may surround the meaningful payload in a PreDB title.
     *
     * @var list<string>
     */
    private const TECHNICAL_WRAPPERS = [
        'sdx264xxx',
        'internal',
        'dvdrip',
        'webrip',
        'webdl',
        'proper',
        'repack',
        '576p',
        '480p',
        'h264',
        'x264',
        'mp4',
        'avc',
        'aac',
        'xxx',
        'sd',
    ];

    /**
     * @var array<string, array<array-key, mixed>>
     */
    private array $preDbCandidateCache = [];

    public function __construct(
        private readonly SearchServiceInterface $searchService,
        private readonly PredbMatchGuard $guard = new PredbMatchGuard,
    ) {}

    public function resolve(string $value): ?NameFixResult
    {
        $parsed = $this->parse($value);
        if ($parsed === null) {
            return null;
        }

        $preDbMatch = $this->findPreDbMatch($parsed);
        if ($preDbMatch !== null) {
            return NameFixResult::fromPreDb(
                $this->buildReleaseName($parsed, $preDbMatch['payload']),
                $preDbMatch['id'],
                $preDbMatch['source'],
                'XXX SDX264XXX'
            );
        }

        return NameFixResult::fromMatch(
            $this->buildReleaseName($parsed, $parsed['payload']),
            'SDX264XXX compact fallback',
            'XXX',
            confidence: 0.6
        );
    }

    /**
     * @return array{source: string, studio: string, raw_date: string, year: int, month: int, day: int, payload: string}|null
     */
    private function parse(string $value): ?array
    {
        $pathParts = preg_split('/[\\\\\/]+/', trim($value)) ?: [];
        $filename = (string) ($pathParts[array_key_last($pathParts)] ?? '');
        $filename = preg_replace('/\.(?:mp4|m4v|mkv|avi|wmv|mov|rar|r\d{2,4}|par2?)$/i', '', $filename) ?? $filename;
        $filename = preg_replace('/XXXMP4-SDX264XXX$/i', '', $filename) ?? $filename;

        if (! str_starts_with(strtoupper($filename), self::PREFIX)) {
            return null;
        }

        $body = substr($filename, strlen(self::PREFIX));
        $bodyLength = strlen($body);

        for ($dateOffset = 1; $dateOffset <= $bodyLength - 7; $dateOffset++) {
            $source = substr($body, 0, $dateOffset);
            $rawDate = substr($body, $dateOffset, 6);

            if (! preg_match('/^[A-Z0-9]+$/i', $source) || ! ctype_digit($rawDate)) {
                continue;
            }

            $day = (int) substr($rawDate, 0, 2);
            $month = (int) substr($rawDate, 2, 2);
            $year = 2000 + (int) substr($rawDate, 4, 2);
            if (! checkdate($month, $day, $year)) {
                continue;
            }

            $payload = trim(substr($body, $dateOffset + 6), " .-_\t\r\n");
            if ($payload === '') {
                return null;
            }

            $source = strtoupper($source);

            return [
                'source' => $source,
                'studio' => self::SOURCE_ALIASES[$source] ?? $source,
                'raw_date' => $rawDate,
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'payload' => $payload,
            ];
        }

        return null;
    }

    /**
     * @param  array{source: string, studio: string, raw_date: string, year: int, month: int, day: int, payload: string}  $parsed
     * @return array{id: int, payload: string, source: string}|null
     */
    private function findPreDbMatch(array $parsed): ?array
    {
        $cacheKey = $parsed['source'].'|'.$parsed['year'].'-'.$parsed['month'].'-'.$parsed['day'];

        if (! array_key_exists($cacheKey, $this->preDbCandidateCache)) {
            try {
                $query = sprintf(
                    '%s %04d %02d %02d',
                    $parsed['studio'],
                    $parsed['year'],
                    $parsed['month'],
                    $parsed['day']
                );
                $this->preDbCandidateCache[$cacheKey] = $this->searchService->searchPredb($query);
            } catch (Throwable) {
                $this->preDbCandidateCache[$cacheKey] = [];
            }
        }

        foreach ($this->preDbCandidateCache[$cacheKey] as $candidate) {
            if (! is_array($candidate) && ! is_object($candidate)) {
                continue;
            }

            /** @var array<string, mixed> $candidateData */
            $candidateData = is_object($candidate) ? get_object_vars($candidate) : $candidate;
            $candidateId = (int) ($candidateData['id'] ?? $candidateData['predb_id'] ?? 0);
            if ($candidateId <= 0) {
                continue;
            }

            $fields = $this->guard->allowsFilenameMatch((string) ($candidateData['source'] ?? '')) ? ['title', 'filename'] : ['title'];
            foreach ($fields as $field) {
                $readablePayload = $this->extractReadablePayload((string) ($candidateData[$field] ?? ''), $parsed);
                if ($readablePayload === null) {
                    continue;
                }

                return [
                    'id' => $candidateId,
                    'payload' => $readablePayload,
                    'source' => (string) ($candidateData['source'] ?? 'predb'),
                ];
            }
        }

        return null;
    }

    /**
     * @param  array{source: string, studio: string, raw_date: string, year: int, month: int, day: int, payload: string}  $parsed
     */
    private function extractReadablePayload(string $candidate, array $parsed): ?string
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', trim($candidate), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $expectedPayload = $this->compact($parsed['payload']);
        if ($tokens === [] || $expectedPayload === '') {
            return null;
        }

        $tokenCount = count($tokens);
        for ($start = 0; $start < $tokenCount; $start++) {
            $joined = '';

            for ($end = $start; $end < $tokenCount; $end++) {
                $joined .= $this->compact($tokens[$end]);
                if (strlen($joined) > strlen($expectedPayload)) {
                    break;
                }

                if ($joined !== $expectedPayload) {
                    continue;
                }

                $outsidePayload = [
                    ...array_slice($tokens, 0, $start),
                    ...array_slice($tokens, $end + 1),
                ];

                if ($this->containsOnlyKnownWrappers($outsidePayload, $parsed)) {
                    return implode('.', array_slice($tokens, $start, $end - $start + 1));
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tokens
     * @param  array{source: string, studio: string, raw_date: string, year: int, month: int, day: int, payload: string}  $parsed
     */
    private function containsOnlyKnownWrappers(array $tokens, array $parsed): bool
    {
        $year = sprintf('%04d', $parsed['year']);
        $shortYear = substr($year, 2);
        $month = sprintf('%02d', $parsed['month']);
        $day = sprintf('%02d', $parsed['day']);
        $studioParts = preg_split(
            '/(?<=[a-z])(?=[A-Z0-9])|(?<=[0-9])(?=[A-Z])/u',
            $parsed['studio'],
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $wrappers = [
            $this->compact($parsed['studio']),
            ...array_map($this->compact(...), $studioParts),
            $this->compact($parsed['source']),
            $parsed['raw_date'],
            $shortYear.$month.$day,
            $year.$month.$day,
            $day.$month.$year,
            $year,
            $shortYear,
            $month,
            $day,
            'web',
            'dl',
            ...self::TECHNICAL_WRAPPERS,
        ];

        $wrappers = array_unique($wrappers);

        foreach ($tokens as $token) {
            if (! in_array($this->compact($token), $wrappers, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{source: string, studio: string, raw_date: string, year: int, month: int, day: int, payload: string}  $parsed
     */
    private function buildReleaseName(array $parsed, string $payload): string
    {
        $payload = preg_replace('/[^\p{L}\p{N}]+/u', '.', trim($payload)) ?? $payload;
        $payload = trim(preg_replace('/\.{2,}/', '.', $payload) ?? $payload, '.');

        return sprintf(
            '%s.%04d.%02d.%02d.%s.XXX.SD.x264-SDX264XXX',
            $parsed['studio'],
            $parsed['year'],
            $parsed['month'],
            $parsed['day'],
            $payload
        );
    }

    private function compact(string $value): string
    {
        return mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? $value);
    }
}
