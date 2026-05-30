<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('reference')->unique();
            $table->enum('type', ['topup', 'call_credit', 'payment_sent']);
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3);
            $table->string('payment_method')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'status']);
            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
