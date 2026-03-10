<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Settings;
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
                    Settings::REGISTER_STATUS_OPEN,
                    Settings::REGISTER_STATUS_INVITE,
                    Settings::REGISTER_STATUS_CLOSED,
                ]),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
