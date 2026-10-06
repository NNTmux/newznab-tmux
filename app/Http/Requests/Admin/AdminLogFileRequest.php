<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\LogViewer\LogEntryParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates log viewer actions that target a single file (entries, entry, download, truncate, delete).
 */
class AdminLogFileRequest extends FormRequest
{
    /**
     * @var list<int>
     */
    public const array LIMIT_OPTIONS = [50, 100, 200, 500];

    public const int DEFAULT_LIMIT = 100;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $file = $this->input('file');

        $this->merge([
            'file' => is_string($file) ? trim($file) : $file,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'string', 'max:255'],
            'before' => ['nullable', 'integer', 'min:0'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', Rule::in(self::LIMIT_OPTIONS)],
            'levels' => ['nullable', 'array'],
            'levels.*' => ['string', Rule::in(LogEntryParser::LEVELS)],
        ];
    }

    /**
     * @return list<string>
     */
    public function levels(): array
    {
        return array_values(array_unique(array_map('strval', (array) $this->validated('levels', []))));
    }
}
