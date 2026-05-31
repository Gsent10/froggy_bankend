<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletActivity extends Model
{
    // Immutable ledger — no updates ever
    public $timestamps = false;

    protected $fillable = [
        'wallet_id',
        'transaction_id',
        'type',
        'status',
        'amount',
        'currency_code',
        'description',
        'created_at',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'created_at' => 'datetime',
    ];

    /**
     * Stamp an activity entry for a resolved transaction.
     * Call this after Transaction::markSuccess() or Transaction::markFailed().
     */
    public static function recordFromTransaction(Transaction $transaction, string $description = ''): self
    {
        return static::create([
            'wallet_id' => $transaction->wallet_id,
            'transaction_id' => $transaction->id,
            'type' => $transaction->type,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'currency_code' => $transaction->currency_code,
            'description' => $description ?: ucfirst(str_replace('_', ' ', $transaction->type)),
            'created_at' => now(),
        ]);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
