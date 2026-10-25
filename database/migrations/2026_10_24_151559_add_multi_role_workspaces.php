<?php

use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->json('notification_preferences')->nullable());
        Schema::table('agencies', function (Blueprint $t): void {
            $t->string('type')->default('agency');
            $t->string('status')->default('active');
        });
        Schema::table('permission_role', function (Blueprint $t): void {
            $t->string('scope')->default('own');
        });
        Schema::create('workspaces', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('kind');
            $t->foreignId('agency_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('owner_user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $t->json('enabled_modules');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('branches', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->timestamps();
            $t->unique(['workspace_id', 'name']);
        });
        Schema::create('teams', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('name');
            $t->timestamps();
            $t->unique(['workspace_id', 'name']);
        });
        Schema::create('memberships', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('scope')->default('organisation');
            $t->string('profession')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('allow_exports')->default(false);
            $t->timestamps();
            $t->unique(['workspace_id', 'user_id']);
        });
        Schema::create('membership_role', function (Blueprint $t): void {
            $t->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->restrictOnDelete();
            $t->primary(['membership_id', 'role_id']);
        });
        foreach (['payments', 'follow_ups', 'audit_logs'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->foreignId('workspace_id')->nullable()->constrained()->restrictOnDelete();
                $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
                $t->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
                $t->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            });
        }
        User::query()->each(fn ($user) => app(WorkspaceProvisioner::class)->forUser($user));
        foreach (['payments', 'follow_ups', 'audit_logs'] as $table) {
            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $workspace = $row->agency_id ? Workspace::where('agency_id', $row->agency_id)->first() : null;
                    if (! $workspace && $row->user_id) {
                        $user = User::find($row->user_id);
                        $workspace = $user ? app(WorkspaceProvisioner::class)->personal($user) : null;
                    }if ($workspace) {
                        DB::table($table)->where('id', $row->id)->update(['workspace_id' => $workspace->id]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['payments', 'follow_ups', 'audit_logs'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                foreach (['workspace_id', 'branch_id', 'team_id', 'assigned_to'] as $field) {
                    $t->dropConstrainedForeignId($field);
                }
            });
        }
        foreach (['membership_role', 'memberships', 'teams', 'branches', 'workspaces'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('notification_preferences'));
        Schema::table('permission_role', fn (Blueprint $t) => $t->dropColumn('scope'));
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn(['type', 'status']));
    }
};
