<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ColorScheme;
use App\Enums\ThemePreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThemeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'theme_preference' => ['sometimes', Rule::in(ThemePreference::values())],
            'color_scheme' => ['sometimes', Rule::in(ColorScheme::values())],
        ];
    }
}
