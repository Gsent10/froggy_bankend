<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'currency_code' => $this->currency_code,
            'balance'       => number_format((float) $this->balance, 2, '.', ''),
            'last_topup_at' => $this->last_topup_at?->toISOString(),
        ];
    }
}
