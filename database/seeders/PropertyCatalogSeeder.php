<?php

namespace Database\Seeders;

use App\Services\PropertyCatalog;
use Illuminate\Database\Seeder;

class PropertyCatalogSeeder extends Seeder
{
    public function run(): void
    {
        app(PropertyCatalog::class)->import();
    }
}
