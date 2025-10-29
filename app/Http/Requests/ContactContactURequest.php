<?php

namespace App\Http\Requests;

use App\Support\CaptchaHelper;
use Illuminate\Foundation\Http\FormRequest;

class ContactContactURequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'username' => 'required|string|max:255',
            'useremail' => 'required|email|max:255',
            'comment' => 'required|string|min:10',
        ];

        // Merge CAPTCHA validation rules if enabled
        if (CaptchaHelper::isEnabled()) {
            $rules = array_merge($rules, CaptchaHelper::getValidationRules());
        }

        return $rules;
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Please enter your name.',
            'useremail.required' => 'Please enter your email address.',
            'useremail.email' => 'Please enter a valid email address.',
            'comment.required' => 'Please enter your message.',
            'comment.min' => 'Your message must be at least 10 characters long.',
        ];
    }
}
