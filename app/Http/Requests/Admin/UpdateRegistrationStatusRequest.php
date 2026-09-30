<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RegistrationStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistrationStatusRequest extends FormRequest
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
            'registerstatus' => [
                'required',
                'integer',
                'in:'.implode(',', [
                    RegistrationStatus::Open->value,
                    RegistrationStatus::Invite->value,
                    RegistrationStatus::Closed->value,
                ]),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
