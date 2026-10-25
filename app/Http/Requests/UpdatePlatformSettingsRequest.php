<?php

namespace App\Http\Requests;

use App\Services\CurrencyService;
use App\Services\Payments\Money;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    public function rules(): array
    {
        $codes = array_keys(app(CurrencyService::class)->catalog());

        return [
            'revision' => 'required|integer|min:0', 'brand_name' => 'required|string|max:60', 'tagline' => 'required|string|max:160', 'company_name' => 'required|string|max:120',
            'support_email' => 'nullable|email|max:255', 'support_phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:500',
            'primary_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/D', 'secondary_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/D', 'background_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/D', 'text_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/D',
            'default_currency' => ['required', Rule::in($codes)], 'pricing_currency' => ['required', Rule::in($codes)],
            'enabled_currencies' => 'required|array|min:1', 'enabled_currencies.*' => ['required', Rule::in($codes)],
            'currency_display' => 'required|in:code,symbol_code', 'decimal_separator' => ['required', Rule::in(['.', ','])], 'thousands_separator' => ['present', 'nullable', Rule::in(['.', ',', ' ', ''])], 'locale' => 'required|in:en_NG,en_US,en_GB,fr_FR,de_DE,ar,ja_JP', 'timezone' => 'required|timezone',
            'payments_enabled' => 'required|boolean', 'pricing_enabled' => 'required|boolean', 'max_payment_amount' => 'required|numeric|min:1|max:999999999999|regex:/^\d{1,12}(?:\.\d{1,8})?$/D',
            'commission_percent' => 'required|numeric|min:0|max:100|decimal:0,4', 'service_fee_percent' => 'required|numeric|min:0|max:100|decimal:0,4', 'tax_percent' => 'required|numeric|min:0|max:100|decimal:0,4', 'flat_fee' => 'required|numeric|min:0|max:1000000000|regex:/^\d{1,12}(?:\.\d{1,8})?$/D', 'pricing_disclaimer' => 'required|string|max:1000',
            'invoice_prefix' => 'required|regex:/^[A-Z0-9-]{1,12}$/D', 'receipt_prefix' => 'required|regex:/^[A-Z0-9-]{1,12}$/D',
            'paystack_currencies' => 'sometimes|array', 'paystack_currencies.*' => 'in:NGN,USD,GHS,ZAR,KES,XOF',
            'flutterwave_currencies' => 'sometimes|array', 'flutterwave_currencies.*' => 'in:NGN,USD,GBP,EUR,CAD,GHS,KES,ZAR,UGX,RWF,TZS,XAF,XOF,EGP,MWK,ZMW',
            'logo_dark' => 'nullable|file|image|mimes:png,jpg,jpeg,webp|max:2048|dimensions:max_width=3000,max_height=2000',
            'logo_light' => 'nullable|file|image|mimes:png,jpg,jpeg,webp|max:2048|dimensions:max_width=3000,max_height=2000',
            'favicon' => 'nullable|file|image|mimes:png|max:256|dimensions:max_width=512,max_height=512',
            'remove_logo_dark' => 'sometimes|boolean', 'remove_logo_light' => 'sometimes|boolean', 'remove_favicon' => 'sometimes|boolean',
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $validator->errors()->has('default_currency') && ! in_array($this->input('default_currency'), (is_array($this->input('enabled_currencies')) ? $this->input('enabled_currencies') : []), true)) {
                $validator->errors()->add('default_currency', 'The default currency must be enabled.');
            }
            if (! $validator->errors()->has('pricing_currency') && ! in_array($this->input('pricing_currency'), (is_array($this->input('enabled_currencies')) ? $this->input('enabled_currencies') : []), true)) {
                $validator->errors()->add('pricing_currency', 'The calculator fee currency must be enabled.');
            }
            if ($this->input('decimal_separator') === $this->input('thousands_separator')) {
                $validator->errors()->add('thousands_separator', 'Decimal and thousands separators must differ.');
            }
            $colors = ['primary_color', 'secondary_color', 'background_color', 'text_color'];
            if (! collect($colors)->contains(fn ($key) => $validator->errors()->has($key))) {
                $settings = app(PlatformSettings::class);
                $a = $this->input('primary_color');
                $b = $this->input('secondary_color');
                $black = min($settings->contrast($a, '#171717'), $settings->contrast($b, '#171717'));
                $white = min($settings->contrast($a, '#FFFFFF'), $settings->contrast($b, '#FFFFFF'));
                if (max($black, $white) < 4.5) {
                    $validator->errors()->add('secondary_color', 'Choose gradient colours that support readable button text.');
                }
                if ($settings->contrast($this->input('background_color'), $this->input('text_color')) < 4.5 || $settings->contrast('#FFFFFF', $this->input('text_color')) < 4.5) {
                    $validator->errors()->add('text_color', 'Choose text that stays readable on the page background and standard white panels.');
                }
            }
            if (! $validator->errors()->has('flat_fee') && ! $validator->errors()->has('pricing_currency')) {
                try {
                    Money::minor($this->input('flat_fee'), $this->input('pricing_currency'));
                } catch (\InvalidArgumentException $e) {
                    $validator->errors()->add('flat_fee', $e->getMessage());
                }
            }
        }];
    }
}
