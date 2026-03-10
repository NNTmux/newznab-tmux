<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminLogViewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $file = $this->input('file');
        $search = $this->input('search');

        $this->merge([
            'file' => is_string($file) ? trim($file) : $file,
            'search' => is_string($search) ? trim($search) : $search,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'lines' => ['nullable', 'integer', 'in:100,200,500,1000'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
