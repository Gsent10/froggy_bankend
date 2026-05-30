<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'    => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'unique:customers,email'],
            'phone_number' => ['required', 'string', 'unique:customers,phone_number'],
            'country_code' => ['required', 'string', 'exists:countries,code'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'        => 'An account with this email already exists.',
            'phone_number.unique' => 'An account with this phone number already exists.',
            'country_code.exists' => 'The selected country is not supported.',
            'password.confirmed'  => 'Password confirmation does not match.',
        ];
    }
}
