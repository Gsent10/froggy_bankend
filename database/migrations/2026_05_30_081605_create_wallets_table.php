<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->timestamp('last_topup_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'currency_code']);

            $table->foreign('currency_code')->references('currency_code')->on('countries');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
