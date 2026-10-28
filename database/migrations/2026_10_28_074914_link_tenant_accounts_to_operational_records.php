<?php

use Database\Seeders\AccessControlSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->foreignId('linked_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
        foreach (['leases', 'invoices', 'receipts'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('tenant_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }
        Schema::table('service_bookings', function (Blueprint $table): void {
            $table->boolean('sharing_consent')->default(false);
            $table->json('requester_snapshot')->nullable();
        });
        $this->seedPermissions();
    }

    private function seedPermissions(): void
    {
        (new AccessControlSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('service_bookings', function (Blueprint $table): void {
            $table->dropColumn(['sharing_consent', 'requester_snapshot']);
        });
        foreach (['leases', 'invoices', 'receipts'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('tenant_user_id');
            });
        }
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('linked_user_id');
        });
    }
};
