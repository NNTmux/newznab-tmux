<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\CaptchaHelper;
use Illuminate\Foundation\Http\FormRequest;

class ShowLinkRequestFormForgotPasswordRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return CaptchaHelper::getValidationRules();
    }
}
