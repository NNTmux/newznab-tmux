<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        if (config('captcha.enabled') === true && (! empty(config('captcha.secret')) && ! empty(config('captcha.sitekey')))) {
            return [
                'g-recaptcha-response' => [
                    'required',
                    'captcha',
                ],
            ];
        } else {
            return [];
        }
    }
}
