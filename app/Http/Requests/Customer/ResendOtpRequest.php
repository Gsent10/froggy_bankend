<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ResendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:customers,email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.exists' => 'No account found with this email address.',
        ];
    }
}
