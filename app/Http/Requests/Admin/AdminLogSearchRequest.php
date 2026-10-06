<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\LogViewer\LogEntryParser;
use App\Services\LogViewer\SearchQuery;
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

        $this->merge([
            'q' => is_string($query) ? trim($query) : $query,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'min:2', 'max:255', 'required_without:levels', 'not_regex:/[\x00-\x1F\x7F]/'],
            'regex' => ['nullable', 'boolean'],
            'case' => ['nullable', 'boolean'],
            'files' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('nntmux.log_viewer.max_files_per_search', 25))],
            'files.*' => ['required', 'string', 'max:255', 'distinct'],
            'levels' => ['nullable', 'array'],
            'levels.*' => ['string', Rule::in(LogEntryParser::LEVELS)],
            'before' => ['nullable', 'integer', 'min:0'],
        ];
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
        );
    }
}
