<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'full_name'    => $this->full_name,
            'email'        => $this->email,
            'phone_number' => $this->phone_number,
            'country'      => [
                'code'            => $this->country->code,
                'name'            => $this->country->name,
                'currency_code'   => $this->country->currency_code,
                'currency_symbol' => $this->country->currency_symbol,
            ],
            'created_at'   => $this->created_at->toDateTimeString(),
        ];
    }
}
