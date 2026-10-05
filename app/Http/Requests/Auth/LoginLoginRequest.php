<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\CaptchaHelper;
use Illuminate\Foundation\Http\FormRequest;

class LoginLoginRequest extends FormRequest
{
    protected $redirectRoute = 'login';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CaptchaHelper::getValidationRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cf-turnstile-response.required' => 'Please complete the Cloudflare human verification before signing in.',
        ];
    }
}
