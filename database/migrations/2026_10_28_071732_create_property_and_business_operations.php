<?php

use App\Services\PropertyCatalog;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function scope(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
        $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
        $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
        $t->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
        $t->timestamps();
        $t->softDeletes();
        $t->index(['workspace_id', 'deleted_at']);
    }

    private function money(Blueprint $t, string $field = 'amount_minor'): void
    {
        $t->unsignedBigInteger($field)->default(0);
        $t->string('currency', 8)->default('NGN');
        $t->unsignedTinyInteger('minor_unit')->default(2);
    }

    public function up(): void
    {
        Schema::create('development_projects', function (Blueprint $t): void {
            $this->scope($t);
            $t->string('name');
            $t->string('location');
            $t->text('description')->nullable();
            $t->string('status')->default('planning');
            $t->unsignedTinyInteger('progress')->default(0);
            $t->date('starts_on')->nullable();
            $t->date('target_date')->nullable();
            $this->money($t, 'budget_minor');
        });
        Schema::create('properties', function (Blueprint $t): void {
            $this->scope($t);
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('location');
            $t->string('type', 30);
            $t->string('purpose', 20);
            $t->text('description');
            $this->money($t, 'price_minor');
            $t->unsignedSmallInteger('beds')->default(0);
            $t->unsignedSmallInteger('baths')->default(0);
            $t->decimal('area', 14, 2)->default(0);
            $t->boolean('furnished')->default(false);
            $t->json('amenities')->nullable();
            $t->boolean('is_concept')->default(false);
            $t->string('status')->default('available');
            $t->string('publication_status')->default('draft');
            $t->foreignId('project_id')->nullable()->constrained('development_projects')->restrictOnDelete();
            $t->index(['publication_status', 'status']);
        });
        Schema::create('property_media', function (Blueprint $t): void {
            $this->scope($t);
            $t->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('path');
            $t->string('mime_type', 100);
            $t->string('kind', 20)->default('image');
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_cover')->default(false);
            $t->boolean('is_public')->default(false);
        });
        Schema::create('property_units', function (Blueprint $t): void {
            $this->scope($t);
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('status')->default('available');
            $t->text('description')->nullable();
            $this->money($t, 'price_minor');
            $t->unique(['property_id', 'name']);
        });
        Schema::create('contacts', function (Blueprint $t): void {
            $this->scope($t);
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 50)->nullable();
            $t->string('type')->default('tenant');
            $t->text('notes')->nullable();
        });
        Schema::create('leases', function (Blueprint $t): void {
            $this->scope($t);
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained('property_units')->restrictOnDelete();
            $t->foreignId('contact_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('period')->default('monthly');
            $this->money($t, 'rent_minor');
            $t->unsignedBigInteger('deposit_minor')->default(0);
            $t->string('status')->default('draft');
            $t->text('notes')->nullable();
            $t->index(['property_id', 'status', 'starts_on', 'ends_on']);
        });
        Schema::create('maintenance_tickets', function (Blueprint $t): void {
            $this->scope($t);
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained('property_units')->restrictOnDelete();
            $t->string('name');
            $t->text('description');
            $t->string('priority')->default('normal');
            $t->string('status')->default('open');
            $t->date('due_on')->nullable();
            $this->money($t, 'cost_minor');
        });
        Schema::create('inspections', function (Blueprint $t): void {
            $this->scope($t);
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained('property_units')->restrictOnDelete();
            $t->string('name');
            $t->date('inspected_on');
            $t->string('status')->default('scheduled');
            $t->text('findings')->nullable();
        });
        Schema::create('invoices', function (Blueprint $t): void {
            $this->scope($t);
            $t->string('reference')->unique();
            $t->string('name');
            $t->foreignId('contact_id')->constrained()->restrictOnDelete();
            $t->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('lease_id')->nullable()->constrained()->restrictOnDelete();
            $t->json('items');
            $t->json('customer_snapshot')->nullable();
            $t->json('issuer_snapshot')->nullable();
            $this->money($t, 'total_minor');
            $t->unsignedBigInteger('subtotal_minor');
            $t->unsignedBigInteger('tax_minor')->default(0);
            $t->decimal('tax_percent', 7, 4)->default(0);
            $t->date('issued_on');
            $t->date('due_on');
            $t->string('status')->default('draft');
            $t->text('notes')->nullable();
        });
        Schema::create('receipts', function (Blueprint $t): void {
            $this->scope($t);
            $t->string('reference')->unique();
            $t->string('name');
            $t->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $t->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $this->money($t);
            $t->date('received_on');
            $t->text('notes')->nullable();
        });
        Schema::create('staff_members', function (Blueprint $t): void {
            $this->scope($t);
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('position');
            $t->date('started_on');
            $t->string('status')->default('active');
            $this->money($t, 'salary_minor');
        });
        Schema::create('payroll_entries', function (Blueprint $t): void {
            $this->scope($t);
            $t->foreignId('staff_member_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('period', 7);
            $this->money($t, 'gross_minor');
            $t->unsignedBigInteger('allowances_minor')->default(0);
            $t->unsignedBigInteger('deductions_minor')->default(0);
            $t->unsignedBigInteger('net_minor')->default(0);
            $t->string('status')->default('draft');
            $t->date('paid_on')->nullable();
            $t->unique(['staff_member_id', 'period']);
        });
        Schema::create('expenses', function (Blueprint $t): void {
            $this->scope($t);
            $t->string('name');
            $t->string('category');
            $t->date('incurred_on');
            $this->money($t);
            $t->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('payroll_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->string('status')->default('recorded');
            $t->text('notes')->nullable();
        });
        Schema::create('appointments', function (Blueprint $t): void {
            $this->scope($t);
            $t->string('name');
            $t->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('contact_id')->nullable()->constrained()->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->dateTime('reminder_at')->nullable();
            $t->dateTime('reminder_queued_at')->nullable();
            $t->dateTime('reminded_at')->nullable();
            $t->unsignedInteger('reminder_version')->default(1);
            $t->string('status')->default('scheduled');
            $t->text('notes')->nullable();
        });
        Schema::create('business_documents', function (Blueprint $t): void {
            $this->scope($t);
            $t->string('name');
            $t->string('category');
            $t->string('path');
            $t->string('original_name');
            $t->string('mime_type', 120);
            $t->unsignedBigInteger('size');
            $t->date('expires_on')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('lease_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained('development_projects')->restrictOnDelete();
        });
        Schema::create('referrals', function (Blueprint $t): void {
            $this->scope($t);
            $t->string('name');
            $t->foreignId('target_workspace_id')->constrained('workspaces')->restrictOnDelete();
            $t->string('client_name');
            $t->string('client_email')->nullable();
            $t->text('terms');
            $t->text('notes')->nullable();
            $t->decimal('commission_percent', 7, 4)->default(0);
            $t->boolean('sharing_consent');
            $t->string('status')->default('submitted');
        });
        Schema::create('service_listings', function (Blueprint $t): void {
            $this->scope($t);
            $t->string('name');
            $t->string('profession');
            $t->string('location');
            $t->text('description');
            $this->money($t, 'fee_minor');
            $t->string('status')->default('draft');
        });
        Schema::create('service_bookings', function (Blueprint $t): void {
            $this->scope($t);
            $t->foreignId('service_listing_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->text('description');
            $t->date('requested_on');
            $t->string('status')->default('requested');
            $this->money($t, 'quote_minor');
            $t->text('response')->nullable();
        });
        Schema::create('construction_updates', function (Blueprint $t): void {
            $this->scope($t);
            $t->foreignId('project_id')->constrained('development_projects')->restrictOnDelete();
            $t->string('name');
            $t->date('recorded_on');
            $t->unsignedTinyInteger('progress');
            $t->text('description');
            $this->money($t, 'cost_minor');
        });
        app(PropertyCatalog::class)->import();
        (new AccessControlSeeder)->run();
    }

    public function down(): void
    {
        foreach (['construction_updates', 'service_bookings', 'service_listings', 'referrals', 'business_documents', 'appointments', 'expenses', 'payroll_entries', 'staff_members', 'receipts', 'invoices', 'inspections', 'maintenance_tickets', 'leases', 'contacts', 'property_units', 'property_media', 'properties', 'development_projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
