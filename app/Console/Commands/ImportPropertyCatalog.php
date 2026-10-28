<?php

namespace App\Console\Commands;

use App\Services\PropertyCatalog;
use Illuminate\Console\Command;

class ImportPropertyCatalog extends Command
{
    protected $signature = 'properties:import-catalog';

    protected $description = 'Import original catalogue prices and media without overwriting edited properties';

    public function handle(): int
    {
        $this->info(app(PropertyCatalog::class)->import().' properties imported. Existing records were preserved.');

        return self::SUCCESS;
    }
}
