<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ShowLinkRequestFormForgotPasswordRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return ['g-recaptcha-response' => [
                'required',
                'captcha',
            ],];
    }
}
