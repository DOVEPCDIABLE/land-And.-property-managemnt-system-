<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $t): void {
            $t->id();
            $t->json('values');
            $t->unsignedInteger('revision')->default(0);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        DB::table('platform_settings')->insert(['id' => 1, 'values' => '[]', 'revision' => 0, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('currencies', function (Blueprint $t): void {
            $t->string('code', 8)->primary();
            $t->string('name', 150);
            $t->string('symbol', 16);
            $t->unsignedTinyInteger('minor_unit');
            $t->boolean('is_enabled')->default(true);
            $t->boolean('is_standard')->default(true);
            $t->timestamps();
        });
        $rows = [];
        foreach (config('currencies') as $code => $currency) {
            $rows[] = ['code' => $code, ...$currency, 'is_enabled' => true, 'is_standard' => true, 'created_at' => now(), 'updated_at' => now()];
        }DB::table('currencies')->insert($rows);
        Schema::create('exchange_rates', function (Blueprint $t): void {
            $t->id();
            $t->string('base_currency', 8);
            $t->string('quote_currency', 8);
            $t->decimal('rate', 30, 12);
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['base_currency', 'quote_currency']);
            $t->foreign('base_currency')->references('code')->on('currencies')->restrictOnDelete();
            $t->foreign('quote_currency')->references('code')->on('currencies')->restrictOnDelete();
        });
        Schema::table('payments', function (Blueprint $t): void {
            $t->string('currency', 8)->change();
            $t->unsignedTinyInteger('minor_unit')->default(2);
        });
    }

    public function down(): void
    {
        if (DB::table('payments')->whereRaw('length(currency)>3')->exists()) {
            throw new RuntimeException('Custom-currency records must be exported before rolling back this migration.');
        }Schema::table('payments', function (Blueprint $t): void {
            $t->dropColumn('minor_unit');
            $t->char('currency', 3)->change();
        });
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('platform_settings');
    }
};
