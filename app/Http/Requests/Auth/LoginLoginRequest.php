<?php

namespace App\Http\Requests\Auth;

use App\Support\CaptchaHelper;
use Illuminate\Foundation\Http\FormRequest;

class LoginLoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CaptchaHelper::getValidationRules();
    }
}
