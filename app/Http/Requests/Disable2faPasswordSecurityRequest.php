<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Disable2faPasswordSecurityRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['current-password' => [
            'required',
        ], ];
    }
}
