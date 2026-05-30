<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'reference'       => $this->reference,
            'type'            => $this->type,
            'status'          => $this->status,
            'amount'          => number_format((float) $this->amount, 2, '.', ''),
            'currency_code'   => $this->currency_code,
            'payment_method'  => $this->payment_method,
            'processed_at'    => $this->processed_at?->toISOString(),
            'created_at'      => $this->created_at->toISOString(),
        ];
    }
}
