<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $primaryKey = 'code';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'currency_code',
        'currency_symbol',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'country_code', 'code');
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'currency_code', 'currency_code');
    }
}
