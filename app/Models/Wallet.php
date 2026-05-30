<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'customer_id',
        'currency_code',
        'balance',
        'last_topup_at',
    ];

    protected $casts = [
        'balance'      => 'decimal:2',
        'last_topup_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WalletActivity::class)->orderByDesc('created_at');
    }

    /**
     * Credit the wallet balance and stamp last_topup_at.
     * Always called inside a DB transaction.
     */
    public function credit(float $amount): void
    {
        $this->increment('balance', $amount);
        $this->update(['last_topup_at' => now()]);
    }

    /**
     * Debit the wallet balance.
     * Always called inside a DB transaction.
     *
     * @throws \Exception if insufficient funds
     */
    public function debit(float $amount): void
    {
        if ($this->balance < $amount) {
            throw new \Exception('Insufficient wallet balance.');
        }

        $this->decrement('balance', $amount);
    }
}
