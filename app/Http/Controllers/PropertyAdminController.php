<?php

namespace App\Http\Controllers;

use App\Models\PropertyMedia;
use App\Services\OperationalAccess;
use App\Services\PropertyCatalog;
use App\Services\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PropertyAdminController extends Controller
{
    public function import(Request $request, PropertyCatalog $catalog): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);
        $count = $catalog->import();
        app(Tenancy::class)->audit($request->user(), 'properties.catalog_imported', 'catalog', ['created' => $count]);

        return back()->with('status', $count.' properties imported. Existing changes were preserved.');
    }

    public function media(Request $request, int $property, int $media, OperationalAccess $access): RedirectResponse
    {
        $property = $access->record('properties', $property, $request->user(), 'edit');
        $action = $request->validate(['action' => 'required|in:cover,remove'])['action'];
        DB::transaction(function () use ($property, $media, $action): void {
            $property->newQuery()->whereKey($property->id)->lockForUpdate()->first();
            $item = $property->media()->findOrFail($media);
            if ($action === 'cover') {
                abort_unless($item->kind === 'image', 422);
                $property->media()->update(['is_cover' => false]);
                $item->update(['is_cover' => true]);
            } else {
                $item->delete();
            }
        });
        app(Tenancy::class)->audit($request->user(), 'properties.media_'.$action, (string) $media);

        return back()->with('status', 'Property media updated.');
    }

    public function library(Request $request): View
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        return view('operations.media', ['media' => PropertyMedia::whereNull('property_id')->where('workspace_id', $request->user()->currentWorkspace()->id)->get()]);
    }

    public function replace(Request $request, int $media): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);
        $record = PropertyMedia::whereNull('property_id')->where('workspace_id', $request->user()->currentWorkspace()->id)->findOrFail($media);
        $request->validate(['file' => ['required', 'file', $record->kind === 'video' ? 'mimes:mp4' : 'mimes:jpg,jpeg,png,webp', 'max:30720']]);
        $file = $request->file('file');
        $name = $file->hashName();
        $mime = $file->getMimeType();
        $directory = public_path('media/properties');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $file->move($directory, $name);
        try {
            DB::transaction(function () use ($record, $name, $mime, $request): void {
                $record->update(['path' => 'media/properties/'.$name, 'mime_type' => $mime]);
                app(Tenancy::class)->audit($request->user(), 'site_media.replaced', (string) $record->id);
            });
        } catch (\Throwable $e) {
            @unlink($directory.'/'.$name);
            throw $e;
        }

        return back()->with('status', 'Website image updated.');
    }
}
