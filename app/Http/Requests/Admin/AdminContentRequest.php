<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Content;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => is_string($this->input('action')) ? trim($this->input('action')) : $this->input('action'),
            'title' => is_string($this->input('title')) ? trim($this->input('title')) : $this->input('title'),
            'url' => is_string($this->input('url')) ? trim($this->input('url')) : $this->input('url'),
            'metadescription' => is_string($this->input('metadescription')) ? trim($this->input('metadescription')) : $this->input('metadescription'),
            'metakeywords' => is_string($this->input('metakeywords')) ? trim($this->input('metakeywords')) : $this->input('metakeywords'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (! $this->isMethod('post') || $this->input('action') !== 'submit') {
            return [];
        }

        return [
            'action' => ['required', 'string', Rule::in(['submit'])],
            'id' => $this->filled('id') ? ['required', 'integer', 'exists:content,id'] : ['nullable'],
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string'],
            'metadescription' => ['nullable', 'string', 'max:1000'],
            'metakeywords' => ['nullable', 'string', 'max:1000'],
            'contenttype' => ['required', 'integer', Rule::in([Content::TYPE_USEFUL, Content::TYPE_INDEX])],
            'status' => ['required', 'integer', Rule::in([Content::STATUS_ENABLED, Content::STATUS_DISABLED])],
            'ordinal' => ['nullable', 'integer', 'min:0'],
            'role' => ['required', 'integer', Rule::in([Content::ROLE_EVERYONE, Content::ROLE_LOGGED_IN, Content::ROLE_ADMIN])],
        ];
    }
}
