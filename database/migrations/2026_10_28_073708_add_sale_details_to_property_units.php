<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_units', function (Blueprint $table): void {
            $table->string('buyer_name')->nullable();
            $table->date('sold_on')->nullable();
            $table->unsignedBigInteger('sale_price_minor')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('property_units', function (Blueprint $table): void {
            $table->dropColumn(['buyer_name', 'sold_on', 'sale_price_minor']);
        });
    }
};
