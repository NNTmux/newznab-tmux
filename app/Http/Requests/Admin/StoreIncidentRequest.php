<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\IncidentImpactEnum;
use App\Enums\IncidentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'service_status_ids' => ['required', 'array', 'min:1'],
            'service_status_ids.*' => ['integer', 'distinct', 'exists:service_statuses,id'],
            'impact' => ['required', 'string', Rule::in(IncidentImpactEnum::values())],
            'status' => ['required', 'string', Rule::in(IncidentStatusEnum::values())],
            'started_at' => ['required', 'date'],
            'resolved_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }
}
