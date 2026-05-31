<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'country_code',
        'full_name',
        'email',
        'phone_number',
        'email_verified_at',
        'verification_code',
        'verification_code_expires_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at'             => 'datetime',
        'verification_code_expires_at'  => 'datetime',
        'password'                      => 'hashed',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    /**
     * All wallets owned by this customer (one per currency).
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /**
     * Convenience: the wallet matching the customer's registered country currency.
     */
    public function primaryWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)
            ->whereColumn('currency_code', 'customers.country_code');
    }

    /**
     * Get a specific wallet by currency code.
     */
    public function walletForCurrency(string $currencyCode): ?Wallet
    {
        return $this->wallets()->where('currency_code', $currencyCode)->first();
    }

    /**
     * All transactions across all of this customer's wallets.
     */
    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Transaction::class,
            Wallet::class,
            'customer_id',
            'wallet_id',
            'id',
            'id',
        );
    }

    /**
     * All wallet activities across all of this customer's wallets.
     */
    public function walletActivities(): HasManyThrough
    {
        return $this->hasManyThrough(
            WalletActivity::class,
            Wallet::class,
            'customer_id',
            'wallet_id',
            'id',
            'id',
        );
    }

    /**
     * Transactions for a specific wallet (by currency).
     */
    public function transactionsForCurrency(string $currencyCode): HasManyThrough
    {
        return $this->transactions()
            ->where('transactions.currency_code', $currencyCode);
    }
}
