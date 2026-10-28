<?php

namespace App\Services;

use App\Models\PropertyMedia;
use Illuminate\Support\Facades\Schema;

class SiteMedia
{
    private ?array $paths = null;

    public function refresh(): void
    {
        $this->paths = null;
    }

    public function url(string $name): string
    {
        if ($this->paths === null) {
            $this->paths = Schema::hasTable('property_media') ? PropertyMedia::whereNull('property_id')->where('is_public', true)->pluck('path', 'name')->all() : [];
        }

        return asset($this->paths[$name] ?? 'media/'.$name);
    }
}
