<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ColorScheme;
use App\Enums\ThemePreference;
use App\Rules\ValidEmailDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'theme_preference' => ['sometimes', Rule::in(ThemePreference::values())],
            'color_scheme' => ['sometimes', Rule::in(ColorScheme::values())],
        ];
    }
}
