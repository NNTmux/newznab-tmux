<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactContactURequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return ['g-recaptcha-response' => [
                'required',
                'captcha',
            ],];
    }
}
