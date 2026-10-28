<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $record = DB::table('platform_settings')->where('id', 1)->lockForUpdate()->first();
            if ($record) {
                $values = json_decode($record->values, true, 512, JSON_THROW_ON_ERROR);
                if (! isset($values['brand_name']) || in_array($values['brand_name'], ['Castegroup', 'citiESTATE'], true)) {
                    $values['brand_name'] = 'CASTÉ';
                    DB::table('platform_settings')->where('id', 1)->update(['values' => json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'revision' => $record->revision + 1, 'updated_at' => now()]);
                }
            }
            DB::table('workspaces')->where('kind', 'platform')->whereIn('name', ['Castegroup platform', 'citiESTATE platform'])->update(['name' => 'CASTÉ platform', 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // Preserve operator-owned branding when rolling back.
    }
};
