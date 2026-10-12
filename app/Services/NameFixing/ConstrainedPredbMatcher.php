<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

/**
 * Bounded identity suggestions; partial evidence never authorizes a PreDB link.
 *
 * @phpstan-type Parsed array{title: list<string>, markers: array<string, string>, suffix: list<string>}
 */
final class ConstrainedPredbMatcher
{
    /** @return Parsed|null */
    public function parse(string $name): ?array
    {
        if (strlen($name) > 512 || ! mb_check_encoding($name, 'UTF-8')) {
            return null;
        }
        $name = PredbIdentityKey::normalize($name);
        if ($name === null) {
            return null;
        }
        $name = preg_replace('/\.(?:mkv|mp4|avi|rar|nfo|par2)$/', '', $name);
        $markers = [];
        if (preg_match('/-([a-z0-9]{2,20})$/', $name, $m)) {
            $markers['group'] = $m[1];
            $name = substr($name, 0, -strlen($m[0]));
        }
        $name = preg_replace('/[^\pL\pN]+/u', ' ', $name);
        $tokens = preg_split('/\s+/', trim($name));
        if (count($tokens) > 64) {
            return null;
        }
        $title = [];
        $suffix = [];
        $afterBoundary = false;
        foreach ($tokens as $token) {
            $key = null;
            if (preg_match('/^(?:19|20)\d{2}$/', $token)) {
                $key = 'year';
            } elseif (preg_match('/^s\d{1,2}(?:e\d{1,3})+$/', $token)) {
                $key = 'episode';
                $token = preg_replace_callback('/\d+/', static fn (array $m): string => (string) (int) $m[0], $token);
            } elseif (preg_match('/^s\d{1,2}$/', $token)) {
                $key = 'season';
            } elseif (preg_match('/^(?:480|576|720|1080|2160)[pi]$/', $token)) {
                $key = 'resolution';
            } elseif (in_array($token, ['english', 'german', 'french', 'finnish', 'japanese', 'dutch', 'spanish', 'italian', 'multi', 'dual'])) {
                $key = 'language';
            } elseif (in_array($token, ['extended', 'unrated', 'directors', 'theatrical', 'remastered', 'repack', 'proper'])) {
                $key = 'edition';
            } elseif ($token === 'xxx') {
                $key = 'adult';
            }
            if ($key !== null) {
                $markers[$key] = isset($markers[$key]) ? $markers[$key].' '.$token : $token;
                $afterBoundary = true;

                continue;
            }
            if (in_array($token, ['bluray', 'bdrip', 'web', 'webdl', 'webrip', 'hdtv', 'x264', 'x265', 'h264', 'h265', 'hevc', 'avc', 'ddp', 'dts', 'hdr', 'dovi', 'aac', 'mp4'])) {
                $afterBoundary = true;
            }
            if ($afterBoundary) {
                $suffix[] = $token;
            }
            if (! $afterBoundary && ! in_array($token, ['the', 'a', 'an', 'and', 'of'])) {
                $title[] = $token;
            }
        }

        return ['title' => $title, 'markers' => $markers, 'suffix' => $suffix];
    }

    /** @param list<array{id: int, title: string}> $hits
     * @return array<string, mixed>
     */
    public function suggest(string $query, array $hits): array
    {
        if (count($hits) > 50) {
            return ['id' => null, 'reason' => 'candidate_overflow'];
        }
        $q = $this->parse($query);
        if ($q === null || count($q['title']) < 2 || strlen(implode('', $q['title'])) < 9) {
            return ['id' => null, 'reason' => 'insufficient_title'];
        }
        if (! isset($q['markers']['year']) && ! isset($q['markers']['episode'])) {
            return ['id' => null, 'reason' => 'missing_identity_anchor'];
        }
        $ranked = [];
        foreach ($hits as $hit) {
            if ((int) ($hit['id'] ?? 0) <= 0 || ! is_string($hit['title'] ?? null)) {
                continue;
            }
            $c = $this->parse($hit['title']);
            if ($c === null || count($c['title']) !== count($q['title'])) {
                continue;
            }
            foreach ($q['markers'] as $key => $value) {
                if (($c['markers'][$key] ?? null) !== $value) {
                    continue 2;
                }
            }
            // Missing cut/language/adult metadata must not silently select a specific variant.
            if ($q['suffix'] !== [] && $q['suffix'] !== $c['suffix']) {
                continue;
            }
            foreach (['year', 'edition', 'language', 'adult'] as $key) {
                if (($c['markers'][$key] ?? null) !== ($q['markers'][$key] ?? null)) {
                    continue 2;
                }
            }
            $sum = 0.0;
            $exact = 0;
            $edits = 0;
            foreach ($q['title'] as $i => $token) {
                $candidateToken = $c['title'][$i];
                $distance = levenshtein($token, $candidateToken);
                if ($distance === 0) {
                    $exact++;
                    $sum += 1;

                    continue;
                }
                // Numeric sequel identity and short words never receive edit tolerance.
                $length = max(strlen($token), strlen($candidateToken));
                if (ctype_digit($token) || ctype_digit($candidateToken) || min(strlen($token), strlen($candidateToken)) < 5 || $distance > ($length >= 10 ? 2 : 1)) {
                    continue 2;
                }
                $edits += $distance;
                $sum += 1 - $distance / $length;
            }
            $score = 100 * $sum / count($q['title']);
            if ($exact < 1 || $edits > 2 || $score < 90) {
                continue;
            }
            // Keep different row IDs distinct: normalized spelling does not prove a globally unique release.
            $ranked[(int) $hit['id']] = ['id' => (int) $hit['id'], 'score' => round($score, 3), 'title' => $hit['title']];
        }
        usort($ranked, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        if ($ranked === []) {
            return ['id' => null, 'reason' => 'constraints_or_score'];
        }
        if (isset($ranked[1]) && $ranked[0]['score'] - $ranked[1]['score'] < 8) {
            return ['id' => null, 'reason' => 'ambiguous', 'ranked' => array_slice($ranked, 0, 2)];
        }

        return $ranked[0] + ['reason' => 'constrained_match'];
    }

    /** Complete marker/suffix agreement is still only a suggestion, never global identity proof.
     * @param  list<array{id: int, title: string}>  $hits
     * @return array<string, mixed>
     */
    public function completeEvidence(string $query, array $hits): array
    {
        $result = $this->suggest($query, $hits);
        if ($result['id'] === null) {
            return $result;
        }
        $q = $this->parse($query);
        $c = $this->parse($result['title']);
        if ($q === null || $c === null) {
            return ['id' => null, 'reason' => 'invalid_identity'];
        }
        ksort($q['markers']);
        ksort($c['markers']);
        if ($q['markers'] !== $c['markers'] || $q['suffix'] !== $c['suffix']) {
            return ['id' => null, 'reason' => 'unresolved_release_variant', 'suggestion' => $result];
        }

        return $result;
    }
}
