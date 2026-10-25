<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('subject', 160);
            $table->text('message');
            $table->json('channels');
            $table->timestamp('due_at');
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_at']);
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('agency_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('provider');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('purpose');
            $table->string('status')->default('pending');
            $table->string('external_reference')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->text('checkout_url')->nullable();
            $table->json('instructions')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_transaction_id']);
            $table->index(['agency_id', 'status']);
        });
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->char('digest', 64);
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'digest']);
            $table->index('status');
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'webhook_events', 'payments', 'follow_ups', 'notifications'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn('is_active');
        });
        foreach (['permission_role', 'permissions', 'roles', 'agencies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
