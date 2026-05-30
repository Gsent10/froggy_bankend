<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->string('code', 3)->primary();
            $table->string('name');
            $table->string('currency_code', 3);
            $table->string('currency_symbol', 10);
            $table->timestamps();
        });

        DB::table('countries')->insert([
            ['code' => 'GB', 'name' => 'United Kingdom', 'currency_code' => 'GBP', 'currency_symbol' => '£', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'NG', 'name' => 'Nigeria',         'currency_code' => 'NGN', 'currency_symbol' => '₦', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'US', 'name' => 'United States',   'currency_code' => 'USD', 'currency_symbol' => '$', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'GH', 'name' => 'Ghana',           'currency_code' => 'GHS', 'currency_symbol' => '₵', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'KE', 'name' => 'Kenya',           'currency_code' => 'KES', 'currency_symbol' => 'KSh', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};