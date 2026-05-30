<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Transaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'idempotency_key',
        'reference',
        'type',
        'status',
        'amount',
        'currency_code',
        'payment_method',
        'metadata',
        'processed_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'metadata'     => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Find an existing transaction by idempotency key or create a new pending one.
     * This is the single entry-point for all transaction creation — never call
     * Transaction::create() directly from a service.
     *
     * Usage:
     *   [$transaction, $isNew] = Transaction::findOrCreateByIdempotencyKey(
     *       $idempotencyKey,
     *       [...attributes]
     *   );
     *
     *   if (!$isNew) {
     *       return $transaction; // already processed — return cached result
     *   }
     */
    public static function findOrCreateByIdempotencyKey(
        string $key,
        array  $attributes
    ): array {
        $existing = static::where('idempotency_key', $key)->first();

        if ($existing) {
            return [$existing, false];
        }

        $transaction = static::create(array_merge($attributes, [
            'idempotency_key' => $key,
            'status'          => 'pending',
        ]));

        return [$transaction, true];
    }

    public static function generateReference(): string
    {
        return 'TXN-' . strtoupper(Str::random(8)) . '-' . now()->format('Ymd');
    }

    public function markSuccess(array $metadata = []): void
    {
        $this->update([
            'status'       => 'success',
            'metadata'     => array_merge($this->metadata ?? [], $metadata),
            'processed_at' => now(),
        ]);
    }

    public function markFailed(array $metadata = []): void
    {
        $this->update([
            'status'       => 'failed',
            'metadata'     => array_merge($this->metadata ?? [], $metadata),
            'processed_at' => now(),
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function activity(): HasOne
    {
        return $this->hasOne(WalletActivity::class);
    }
}
