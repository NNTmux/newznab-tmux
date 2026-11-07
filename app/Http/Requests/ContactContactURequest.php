<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\CaptchaHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactContactURequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Signed-in users reply from their account email unless they tick "use a different email".
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'username' => 'required|string|max:255',
            'use_different_email' => 'nullable|boolean',
            'useremail' => [Rule::excludeIf(fn (): bool => ! $this->wantsCustomReplyAddress()), 'required', 'email', 'max:255'],
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
     *
     * @return array<string, string>
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

    /**
     * The address replies should go to: the typed one for guests or when asked for, else the account email.
     */
    public function replyToAddress(): string
    {
        if ($this->wantsCustomReplyAddress()) {
            return (string) $this->validated('useremail');
        }

        return (string) $this->user()?->email;
    }

    private function wantsCustomReplyAddress(): bool
    {
        return $this->user() === null || $this->boolean('use_different_email');
    }
}
