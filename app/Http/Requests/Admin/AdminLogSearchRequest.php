<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\LogViewer\LogEntryParser;
use App\Services\LogViewer\SearchQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminLogSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $query = $this->input('q');
        $merge = ['q' => is_string($query) ? trim($query) : $query];

        foreach (['from', 'to'] as $key) {
            if ($this->input($key) === '') {
                $merge[$key] = null;
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->filterRules(),
            'q' => [...$this->termRules(), 'required_without_all:levels,channels,from,to'],
            'before' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Rules shared with the facets request: the term, its options, files and filters.
     *
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'q' => $this->termRules(),
            'regex' => ['nullable', 'boolean'],
            'case' => ['nullable', 'boolean'],
            'files' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('nntmux.log_viewer.max_files_per_search', 25))],
            'files.*' => ['required', 'string', 'max:255', 'distinct'],
            'levels' => ['nullable', 'array'],
            'levels.*' => ['string', Rule::in(LogEntryParser::LEVELS)],
            'channels' => ['nullable', 'array', 'max:50'],
            'channels.*' => ['string', 'max:64', 'regex:/^[\w.\-]+$/'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
        ];
    }

    /**
     * @return list<string>
     */
    private function termRules(): array
    {
        return ['nullable', 'string', 'min:2', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $term = $this->input('q');

                if ($this->boolean('regex') && is_string($term) && $term !== '' && ! SearchQuery::isValidRegex($term)) {
                    $validator->errors()->add('q', 'Invalid regular expression.');
                }

                if ($this->filled('before') && count((array) $this->input('files', [])) !== 1) {
                    $validator->errors()->add('before', 'Paging older matches is only supported for a single file.');
                }
            },
        ];
    }

    public function searchQuery(): SearchQuery
    {
        return new SearchQuery(
            term: (string) ($this->validated('q') ?? ''),
            regex: $this->boolean('regex'),
            caseSensitive: $this->boolean('case'),
            levels: array_values(array_unique(array_map('strval', (array) $this->validated('levels', [])))),
            channels: array_values(array_unique(array_map('strval', (array) $this->validated('channels', [])))),
            from: $this->boundary('from'),
            to: $this->boundary('to'),
        );
    }

    /**
     * A validated date input in the application timezone; an upper bound without seconds (or without a
     * time) covers that whole minute (or day), matching how `datetime-local` inputs submit it.
     */
    private function boundary(string $key): ?CarbonImmutable
    {
        $value = $this->validated($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = CarbonImmutable::parse($value);

        if ($key !== 'to') {
            return $date;
        }

        return match (true) {
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 => $date->endOfDay(),
            preg_match('/[T ]\d{2}:\d{2}$/', $value) === 1 => $date->endOfMinute(),
            default => $date,
        };
    }
}
