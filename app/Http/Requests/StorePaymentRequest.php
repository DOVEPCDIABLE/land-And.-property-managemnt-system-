<?php

namespace App\Http\Requests;

use App\Services\CurrencyService;
use App\Services\Payments\Money;
use App\Services\PlatformSettings;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('payments.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency') && is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper(trim($this->input('currency')))]);
        }
    }

    public function rules(): array
    {
        return ['reference' => 'required|uuid', 'user_id' => 'required|integer', 'provider' => 'required|in:manual,paystack,flutterwave,eversend', 'amount' => ['required', function ($attribute, $value, $fail): void {
            if (! is_scalar($value)) {
                $fail('Enter a decimal amount.');

                return;
            }try {
                $currency = $this->input('currency', app(CurrencyService::class)->default());
                if (! is_string($currency) || ! array_key_exists($currency, app(CurrencyService::class)->catalog())) {
                    $fail('Choose a known currency.');

                    return;
                }
                $minor = Money::minor((string) $value, $currency);
                if ($minor <= 0 || BigDecimal::of((string) $value)->isGreaterThan(app(PlatformSettings::class)->get('max_payment_amount'))) {
                    $fail('Amount must be positive and within the configured payment limit.');
                }
            } catch (\InvalidArgumentException $e) {
                $fail($e->getMessage());
            }
        }], 'currency' => ['required', Rule::in(array_keys(app(CurrencyService::class)->enabled()))], 'purpose' => 'required|string|max:255'];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $validator->errors()->has('currency') && ! $validator->errors()->has('provider') && $this->input('provider') !== 'manual' && ! in_array($this->input('currency'), app(CurrencyService::class)->providerCurrencies($this->input('provider', '')), true)) {
                $validator->errors()->add('currency', 'This currency is not enabled for the selected gateway. Use manual recording or a supported currency.');
            }
        }];
    }
}
