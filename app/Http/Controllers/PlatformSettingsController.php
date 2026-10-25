<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePlatformSettingsRequest;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Workspace;
use App\Services\CurrencyService;
use App\Services\PlatformSettings;
use App\Services\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformSettingsController extends Controller
{
    public function edit(PlatformSettings $settings, CurrencyService $currencies): View
    {
        return view('platform.settings', ['values' => $settings->all(), 'revision' => $settings->revision(), 'currencies' => $currencies->catalog(), 'rates' => ExchangeRate::latest()->get()]);
    }

    public function update(UpdatePlatformSettingsRequest $request, PlatformSettings $settings, CurrencyService $currencies, Tenancy $tenancy): RedirectResponse
    {
        $data = $request->validated();
        $newFiles = [];
        foreach (['logo_dark', 'logo_light', 'favicon'] as $key) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $directory = public_path('media/branding');
                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }$name = $file->hashName();
                $file->move($directory, $name);
                $data[$key] = 'media/branding/'.$name;
                $newFiles[] = $directory.'/'.$name;
            } elseif ($request->boolean('remove_'.$key)) {
                $data[$key] = null;
            } else {
                unset($data[$key]);
            }
        }
        try {
            DB::transaction(function () use ($request, $data, $settings, $tenancy): void {
                $record = PlatformSetting::whereKey(1)->lockForUpdate()->firstOrFail();
                if ($record->revision !== (int) $data['revision']) {
                    throw ValidationException::withMessages(['revision' => 'Settings were changed by another request. Reload before saving again.']);
                }
                $values = collect($data)->except(['revision', 'enabled_currencies', 'remove_logo_dark', 'remove_logo_light', 'remove_favicon'])->all();
                foreach (['payments_enabled', 'pricing_enabled'] as $key) {
                    $values[$key] = $request->boolean($key);
                }
                foreach (['paystack_currencies', 'flutterwave_currencies'] as $key) {
                    $values[$key] = array_values(array_unique($data[$key] ?? []));
                }
                foreach (['commission_percent', 'service_fee_percent', 'tax_percent', 'flat_fee', 'max_payment_amount'] as $key) {
                    $values[$key] = (string) $values[$key];
                }
                foreach (['support_email', 'support_phone', 'address', 'thousands_separator'] as $key) {
                    $values[$key] = $values[$key] ?? '';
                }
                $oldName = $settings->get('brand_name');
                $merged = [...($record->values ?? []), ...$values];
                $record->update(['values' => $merged, 'revision' => $record->revision + 1, 'updated_by' => $request->user()->id]);
                Currency::query()->update(['is_enabled' => false]);
                Currency::whereIn('code', $data['enabled_currencies'])->update(['is_enabled' => true]);
                Workspace::where('kind', 'platform')->where('name', $oldName.' platform')->update(['name' => $values['brand_name'].' platform']);
                $tenancy->audit($request->user(), 'platform.settings_updated', 'platform', ['changed_keys' => array_keys($values), 'enabled_currency_count' => count($data['enabled_currencies']), 'revision' => $record->revision]);
            });
        } catch (\Throwable $exception) {
            foreach ($newFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }throw $exception;
        }
        $settings->refresh();
        $currencies->refresh();
        $settings->apply();

        return back()->with('status', 'System settings saved. Branding, currencies and calculator defaults are now active.');
    }

    public function currency(Request $request, CurrencyService $currencies, Tenancy $tenancy): RedirectResponse
    {
        $data = $request->validate(['code' => 'required|regex:/^[A-Z][A-Z0-9]{2,7}$/D', 'name' => 'required|string|max:150', 'symbol' => 'required|string|max:16', 'minor_unit' => 'required|integer|min:0|max:8']);
        DB::transaction(function () use ($data, $request, $tenancy): void {
            $settingsRecord = PlatformSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $currency = Currency::whereKey($data['code'])->lockForUpdate()->first();
            if ($currency && $currency->minor_unit !== (int) $data['minor_unit']) {
                abort_if($currency->is_standard || Payment::where('currency', $currency->code)->exists(), 422, 'Decimal units are fixed for ISO currencies and currencies already used in payments.');
            }
            $currency = Currency::updateOrCreate(['code' => $data['code']], [...$data, 'is_standard' => $currency?->is_standard ?? false, 'is_enabled' => $currency?->is_enabled ?? true]);
            $settingsRecord->update(['revision' => $settingsRecord->revision + 1, 'updated_by' => $request->user()->id]);
            $tenancy->audit($request->user(), 'currency.configured', $currency->code, ['minor_unit' => $currency->minor_unit]);
        });
        $currencies->refresh();

        return back()->with('status', 'Currency metadata saved. Existing transaction amounts and precision were preserved.');
    }

    public function exchange(Request $request, CurrencyService $currencies, Tenancy $tenancy): RedirectResponse
    {
        $codes = array_keys($currencies->catalog());
        $data = $request->validate(['base_currency' => ['required', Rule::in($codes)], 'quote_currency' => ['required', 'different:base_currency', Rule::in($codes)], 'rate' => 'required|numeric|gt:0|max:999999999999|regex:/^\d{1,12}(?:\.\d{1,12})?$/D']);
        DB::transaction(function () use ($request, $data, $tenancy): void {
            Currency::whereIn('code', [$data['base_currency'], $data['quote_currency']])->orderBy('code')->lockForUpdate()->get();
            ExchangeRate::where('base_currency', $data['quote_currency'])->where('quote_currency', $data['base_currency'])->delete();
            $rate = ExchangeRate::updateOrCreate(collect($data)->only(['base_currency', 'quote_currency'])->all(), ['rate' => $data['rate'], 'updated_by' => $request->user()->id]);
            $tenancy->audit($request->user(), 'currency.rate_updated', (string) $rate->id, ['base' => $data['base_currency'], 'quote' => $data['quote_currency']]);
        });

        return back()->with('status', 'Manual exchange rate saved. The reverse conversion uses its reciprocal.');
    }

    public function removeRate(Request $request, string $rate, Tenancy $tenancy): RedirectResponse
    {
        $record = ExchangeRate::findOrFail($rate);
        DB::transaction(function () use ($request, $record, $tenancy): void {
            $tenancy->audit($request->user(), 'currency.rate_removed', (string) $record->id, ['base' => $record->base_currency, 'quote' => $record->quote_currency]);
            $record->delete();
        });

        return back()->with('status', 'Exchange rate removed. Original prices and transactions were not changed.');
    }
}
