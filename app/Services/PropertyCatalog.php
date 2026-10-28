<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\Workspace;
use App\Services\Payments\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PropertyCatalog
{
    public function import(): int
    {
        return DB::transaction(function (): int {
            $workspace = app(WorkspaceProvisioner::class)->platform();
            Workspace::whereKey($workspace->id)->lockForUpdate()->first();
            $count = 0;
            foreach (config('marketplace.properties') as $sample) {
                $property = Property::withTrashed()->firstOrCreate(['slug' => $sample['slug']], [
                    ...collect($sample)->except(['image', 'price'])->all(), 'workspace_id' => $workspace->id,
                    'price_minor' => Money::minor((string) $sample['price'], $sample['currency']), 'minor_unit' => app(CurrencyService::class)->minorUnit($sample['currency']),
                    'is_concept' => true, 'publication_status' => 'published', 'status' => 'available',
                ]);
                if ($property->wasRecentlyCreated) {
                    $count++;
                }
                if ($property->wasRecentlyCreated) {
                    PropertyMedia::firstOrCreate(['property_id' => $property->id, 'path' => 'media/'.$sample['image']], ['workspace_id' => $workspace->id, 'name' => $sample['image'], 'mime_type' => 'image/jpeg', 'kind' => 'image', 'is_cover' => true, 'is_public' => true]);
                }
            }
            foreach (glob(public_path('media/*.{jpg,jpeg,png,webp,mp4}'), GLOB_BRACE) as $path) {
                PropertyMedia::firstOrCreate(['property_id' => null, 'name' => basename($path)], ['workspace_id' => $workspace->id, 'path' => 'media/'.basename($path), 'mime_type' => mime_content_type($path), 'kind' => str_ends_with($path, '.mp4') ? 'video' : 'image', 'is_public' => true]);
            }

            return $count;
        });
    }

    public function properties(?string $currency = null): Collection
    {
        if (! Schema::hasTable('properties')) {
            return collect();
        }
        $target = app(CurrencyService::class)->displayCurrency($currency);

        return Property::with('media')->where('publication_status', 'published')->whereHas('workspace', fn ($q) => $q->where('is_active', true)->where(fn ($w) => $w->where('kind', '!=', 'organisation')->orWhereHas('agency', fn ($a) => $a->where('status', 'active'))))->get()->map(function (Property $property) use ($target): array {
            return app(CurrencyService::class)->listing([
                ...$property->only(['id', 'slug', 'name', 'location', 'type', 'purpose', 'currency', 'beds', 'baths', 'area', 'furnished', 'amenities', 'description', 'is_concept', 'status']),
                'price' => Money::major($property->price_minor, $property->currency, $property->minor_unit),
                'image' => preg_replace('#^media/#', '', $property->media->firstWhere('kind', 'image')?->path ?? 'media/house.jpg'),
                'image_url' => asset($property->media->firstWhere('kind', 'image')?->path ?? 'media/house.jpg'), 'detail_url' => route('properties.show', ['slug' => $property->slug, 'currency' => $target]),
                'gallery' => $property->media->map(fn ($m) => ['url' => asset($m->path), 'kind' => $m->kind, 'name' => $m->name])->all(),
            ], $target);
        });
    }
}
