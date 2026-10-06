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
        $query = $this->input('q', $this->input('search'));

        $this->merge([
            'file' => is_string($file) ? trim($file) : $file,
            'q' => is_string($query) ? trim($query) : $query,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }
}
