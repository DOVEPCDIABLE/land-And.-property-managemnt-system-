<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'ecosystem:admin {email} {--name=Administrator} {--agency= : Create an agency administrator instead of a platform administrator}';

    protected $description = 'Create an administrator using a hidden password prompt';

    public function handle(): int
    {
        $password = $this->secret('Password (12+ characters, mixed case and numbers)');
        $data = ['email' => $this->argument('email'), 'password' => $password, 'name' => $this->option('name')];
        $validator = Validator::make($data, ['email' => 'required|email|unique:users', 'name' => 'required|string|max:120', 'password' => ['required', Password::min(12)->mixedCase()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data): void {
            (new AccessControlSeeder)->run();
            $user = new User($data);
            if ($name = $this->option('agency')) {
                $agency = Agency::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
                $user->agency_id = $agency->id;
            }
            $user->role_id = Role::where('name', $this->option('agency') ? 'agency_admin' : 'platform_admin')->sole()->id;
            $user->email_verified_at = now();
            $user->save();
            AuditLog::create(['workspace_id' => $user->currentWorkspace()->id, 'agency_id' => $user->currentWorkspace()->agency_id, 'action' => 'administrator.provisioned', 'subject' => (string) $user->id, 'metadata' => ['source' => 'cli', 'role' => $user->role->name]]);
        });
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
