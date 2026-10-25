<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('intent', 40);
            $table->text('message')->nullable();
            $table->json('preferences')->nullable();
            $table->string('status')->default('new');
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_inquiries');
    }
};
