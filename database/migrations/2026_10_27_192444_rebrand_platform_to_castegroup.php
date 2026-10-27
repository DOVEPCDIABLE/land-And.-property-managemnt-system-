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
                $legacyBrand = ! isset($values['brand_name']) || $values['brand_name'] === 'citiESTATE';
                if ($legacyBrand) {
                    $values['brand_name'] = 'Castegroup';
                    if (! isset($values['company_name']) || $values['company_name'] === 'citiESTATE') {
                        $values['company_name'] = 'CASTÉ Homes Ltd';
                    }
                    foreach (['support_phone' => '+234 802 377 4420', 'address' => 'Plot 4270, Moh’d Namadi Sambo Way, Cadastral Zone C06, Nbora District, Abuja, FCT'] as $key => $default) {
                        if (empty($values[$key])) {
                            $values[$key] = $default;
                        }
                    }
                    DB::table('platform_settings')->where('id', 1)->update(['values' => json_encode($values, JSON_THROW_ON_ERROR), 'revision' => $record->revision + 1, 'updated_at' => now()]);
                }
            }
            DB::table('workspaces')->where('kind', 'platform')->where('name', 'citiESTATE platform')->update(['name' => 'Castegroup platform', 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // Branding is operator-owned data; rollbacks must not overwrite later edits.
    }
};
