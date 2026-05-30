<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type,
            'status'        => $this->status,
            'amount'        => number_format((float) $this->amount, 2, '.', ''),
            'currency_code' => $this->currency_code,
            'description'   => $this->description,
            'created_at'    => $this->created_at->toISOString(),
        ];
    }
}
