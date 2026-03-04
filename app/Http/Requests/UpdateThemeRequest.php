<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'theme_preference' => ['sometimes', 'in:light,dark,system'],
            'color_scheme' => ['sometimes', 'in:blue,emerald,violet'],
        ];
    }
}
