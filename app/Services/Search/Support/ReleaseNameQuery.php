<?php

declare(strict_types=1);

namespace App\Services\Search\Support;

/**
 * Release-name matching shared by the user-facing search backends.
 */
final readonly class ReleaseNameQuery
{
    public const VERSION = 'partial-release-v2';

    private const RELEASE_DETAILS = '/(?:^|[\s._-])(?:\d{3,4}[pi]|[xh][ ._-]?26[45]|hevc|avc|web[ ._-]?dl|webrip|bluray|hdtv|dvdrip)(?=$|[\s._-])/i';

    /** @param list<self> $children */
    private function __construct(private string $type, private string $text = '', private array $children = []) {}

    public static function fromString(string $input): self
    {
        $input = trim($input);
        if ($input === '' || $input === '*') {
            return new self('none');
        }

        $advanced = preg_match('/["()|!*]|(?:^|\s)-\S/u', $input) === 1;
        if (! $advanced) {
            $input = preg_replace('/\.(?:mkv|mp4|avi|nzb|nfo|rar|zip)$/i', '', $input) ?? $input;
        }

        preg_match_all('/[!-]?"[^"]*"|[!-](?=\()|[()|]|[^\s()|]+/u', $input, $matches);
        $tokens = $matches[0];
        if ($tokens === [] || count($tokens) > 256) {
            return new self('none');
        }

        $hasDetails = preg_match(self::RELEASE_DETAILS, $input) === 1;
        preg_match('/([\p{L}\p{N}]+)$/u', $input, $tail);
        $complete = $hasDetails && isset($tail[1])
            && preg_match('/(?:-|(?:[xh][ ._-]?26[45]|hevc|avc)[\s._-]+)[\p{L}\p{N}]+$/iu', $input) === 1
            && preg_match('/^(?:\d+[pi]?|web|dl|webrip|bluray|hdtv|dvdrip|[xh]26[45]|hevc|avc|aac|dts|ddp?)$/i', $tail[1]) !== 1;
        if (! $advanced && $complete) {
            return new self('phrase', trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($input)) ?? ''));
        }
        $position = 0;
        $query = self::parseAnd($tokens, $position, ! $advanced && ! $hasDetails, ! $advanced && ! $complete);

        return $position === count($tokens) && $query->isValid() ? $query : new self('none');
    }

    private function isValid(): bool
    {
        return $this->type !== 'none' && array_all($this->children, static fn (self $child): bool => $child->isValid());
    }

    /** @param list<string> $tokens */
    private static function parseAnd(array $tokens, int &$position, bool $partial, bool $seasons, int $depth = 0): self
    {
        if ($depth > 32) {
            return new self('none');
        }

        $children = [];
        while (isset($tokens[$position]) && $tokens[$position] !== ')') {
            $alternatives = [self::parseAtom($tokens, $position, $partial, $seasons, $depth)];
            // Manticore gives | higher precedence than implicit AND.
            while (($tokens[$position] ?? null) === '|') {
                $position++;
                $alternatives[] = self::parseAtom($tokens, $position, $partial, $seasons, $depth);
            }
            $children[] = self::combine('or', $alternatives);
        }

        return self::combine('and', $children);
    }

    /** @param list<string> $tokens */
    private static function parseAtom(array $tokens, int &$position, bool $partial, bool $seasons, int $depth): self
    {
        $token = $tokens[$position++] ?? '';
        $negated = str_starts_with($token, '!') || str_starts_with($token, '-');
        if ($negated) {
            $token = substr($token, 1);
        }
        if ($token === '' && $negated) {
            $child = self::parseAtom($tokens, $position, false, false, $depth);
        } elseif ($token === '(') {
            $child = self::parseAnd($tokens, $position, false, false, $depth + 1);
            if (($tokens[$position++] ?? '') !== ')') {
                return new self('none');
            }
        } elseif (str_starts_with($token, '"') && str_ends_with($token, '"')) {
            $text = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($token)) ?? '');
            $child = $text === '' ? new self('none') : new self('phrase', $text);
        } else {
            $words = preg_split('/[^\p{L}\p{N}*]+/u', mb_strtolower($token), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $children = [];
            foreach ($words as $index => $word) {
                if (trim($word, '*') === '') {
                    $children[] = new self('none');
                } elseif ($seasons && ! $negated && preg_match('/^s\d{1,3}$/i', $word) === 1) {
                    $children[] = self::combine('or', [
                        new self('term', $word),
                        ...array_map(static fn (int $digit): self => new self('term', $word.'e'.$digit.'*'), range(0, 9)),
                    ]);
                } else {
                    if ($partial && ! $negated && $position === count($tokens) && $index === count($words) - 1
                        && preg_match('/^[\p{L}\p{N}]{2,}$/u', $word) === 1
                        && preg_match('/\p{L}/u', $word) === 1
                        && preg_match('/^(?:s\d+(?:e\d+)+|[xh]26[45]|ddp?\d*|aac\d*|dts(?:hd|ma)?|flac|mp3)$/i', $word) !== 1) {
                        $word = '*'.$word.'*';
                    }
                    $children[] = new self('term', $word);
                }
            }
            $child = self::combine('and', $children);
        }

        return $negated ? new self('not', children: [$child]) : $child;
    }

    /** @param list<self> $children */
    private static function combine(string $type, array $children): self
    {
        return match (count($children)) {
            0 => new self('none'),
            1 => $children[0],
            default => new self($type, children: $children),
        };
    }

    public function manticore(): string
    {
        return match ($this->type) {
            'term' => $this->text,
            'phrase' => '"'.$this->text.'"',
            'not' => '!('.$this->children[0]->manticore().')',
            'and', 'or' => '('.implode($this->type === 'or' ? ' | ' : ' ', array_map(static fn (self $child): string => $child->manticore(), $this->children)).')',
            default => '',
        };
    }

    /** @return array<string, mixed> */
    public function elasticsearch(): array
    {
        return match ($this->type) {
            'term' => ['query_string' => [
                'query' => $this->text,
                'fields' => ['plainsearchname^2', 'searchname^3'],
                'analyze_wildcard' => true,
                'default_operator' => 'AND',
            ]],
            'phrase' => ['multi_match' => [
                'query' => $this->text,
                'fields' => ['plainsearchname^2', 'searchname^3'],
                'type' => 'phrase',
            ]],
            'not' => ['bool' => ['must_not' => [$this->children[0]->elasticsearch()]]],
            'and' => ['bool' => ['must' => array_map(static fn (self $child): array => $child->elasticsearch(), $this->children)]],
            'or' => ['bool' => [
                'should' => array_map(static fn (self $child): array => $child->elasticsearch(), $this->children),
                'minimum_should_match' => 1,
            ]],
            default => ['match_none' => new \stdClass],
        };
    }

    /** @return array{string, list<string>} */
    public function sqlPredicate(string $column): array
    {
        if (! in_array($column, ['searchname', 'r.searchname'], true)) {
            throw new \InvalidArgumentException('Unsupported release-name field.');
        }

        if ($this->type === 'none') {
            return ['0 = 1', []];
        }
        if ($this->type === 'not') {
            [$sql, $bindings] = $this->children[0]->sqlPredicate($column);

            return ['NOT ('.$sql.')', $bindings];
        }
        if ($this->type === 'and' || $this->type === 'or') {
            $predicates = [];
            $bindings = [];
            foreach ($this->children as $child) {
                [$sql, $values] = $child->sqlPredicate($column);
                $predicates[] = $sql;
                array_push($bindings, ...$values);
            }

            return ['('.implode($this->type === 'or' ? ' OR ' : ' AND ', $predicates).')', $bindings];
        }

        $pattern = preg_quote($this->text, '~');
        $pattern = str_replace(['\\*', ' '], ['[[:alnum:]]*', '[^[:alnum:]]+'], $pattern);

        return ['LOWER('.$column.') REGEXP ?', ['(^|[^[:alnum:]])'.$pattern.'([^[:alnum:]]|$)']];
    }
}
