<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class TopUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'         => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'string', 'in:card,bank_transfer,ussd'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }
}
