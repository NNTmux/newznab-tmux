<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidEmailDomain;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email,'.$userId, new ValidEmailDomain],
            'password' => ['nullable', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/'],
            'theme_preference' => ['sometimes', 'in:light,dark,system'],
            'color_scheme' => ['sometimes', 'in:blue,emerald,violet'],
        ];
    }
}
